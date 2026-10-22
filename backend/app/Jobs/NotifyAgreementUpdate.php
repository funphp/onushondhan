<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\TermsVersion;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/** Tells affected users that an agreement changed and needs re-acceptance. */
class NotifyAgreementUpdate implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $termsVersionId) {}

    public function handle(): void
    {
        $version = TermsVersion::query()->find($this->termsVersionId);
        if ($version === null) {
            return;
        }
        $investigatorOnly = in_array($version->agreement_type->value, config('onushondhan.agreements.investigator'), true);

        User::query()->where('status', 'active')
            ->when($investigatorOnly, fn ($q) => $q->whereHas('investigator'))
            ->chunkById(500, function ($users) use ($version) {
                foreach ($users as $user) {
                    $user->notify(new PlatformNotification('agreement_updated', 'Updated :title — please review and accept', [
                        'agreement_type' => $version->agreement_type->value,
                        'version' => $version->version,
                    ], '/agreements', emailCategory: null, titleParams: ['title' => $version->title]));
                }
            });
    }
}
