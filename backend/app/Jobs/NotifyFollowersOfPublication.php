<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Investigation;
use App\Models\User;
use App\Notifications\PlatformNotification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class NotifyFollowersOfPublication implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(public readonly int $investigationId) {}

    public function handle(): void
    {
        $inv = Investigation::query()->with('investigator')->find($this->investigationId);
        if ($inv === null || ! $inv->isPubliclyReadable()) {
            return;
        }
        $inv->investigator->followers()->where('users.status', 'active')->select('users.*')->chunkById(500, function ($users) use ($inv) {
            /** @var User $user */
            foreach ($users as $user) {
                $user->notify(new PlatformNotification('new_investigation_from_followed', 'New investigation from '.$inv->investigator->public_name, [
                    'investigation_id' => $inv->public_id,
                    'investigation_title' => $inv->published_title,
                    'investigator' => $inv->investigator->public_name,
                ], '/investigation/'.$inv->public_id.'/'.$inv->slug, emailCategory: 'followed_investigators'));
            }
        }, 'users.id', 'id');
    }
}
