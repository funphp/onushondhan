<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\FileVault;
use App\Contracts\MalwareScanner;
use App\Enums\ScanStatus;
use App\Models\TipAttachment;
use App\Services\SecurityLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ScanTipAttachment implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public function __construct(public readonly int $attachmentId) {}

    public function handle(FileVault $vault, MalwareScanner $scanner, SecurityLogger $security): void
    {
        $attachment = TipAttachment::query()->find($this->attachmentId);
        if ($attachment === null || ! $vault->exists($attachment->storage_path)) {
            return;
        }
        $result = $vault->withLocalPath($attachment->storage_path, fn (string $p) => $scanner->scan($p));
        $attachment->forceFill(['scan_status' => $result->status])->save();
        if ($result->status === ScanStatus::Infected) {
            $security->event('tip_attachment.infected', null, 'critical', ['attachment' => $attachment->public_id, 'sha256' => $attachment->sha256]);
        }
    }
}
