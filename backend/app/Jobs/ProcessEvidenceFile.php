<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Contracts\FileVault;
use App\Contracts\MalwareScanner;
use App\Enums\EvidenceVisibility;
use App\Enums\ScanStatus;
use App\Models\Evidence;
use App\Models\EvidenceVersion;
use App\Services\AuditLogger;
use App\Services\Files\ImageSanitizer;
use App\Services\SecurityLogger;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Malware scan -> (if clean/not scanned) metadata-free image rendition.
 * Infected files are quarantined: forced private and never served.
 */
class ProcessEvidenceFile implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public int $timeout = 300;

    public function __construct(public readonly int $evidenceId) {}

    public function handle(FileVault $vault, MalwareScanner $scanner, ImageSanitizer $sanitizer, AuditLogger $audit, SecurityLogger $security): void
    {
        $evidence = Evidence::query()->withTrashed()->find($this->evidenceId);
        if ($evidence === null || ! $vault->exists($evidence->storage_path)) {
            return;
        }
        $path = $evidence->storage_path;

        $result = $vault->withLocalPath($path, fn (string $local) => $scanner->scan($local));

        $evidence->forceFill([
            'scan_status' => $result->status,
            'scan_message' => $result->message,
            'scanned_at' => now(),
        ]);
        EvidenceVersion::query()->where('evidence_id', $evidence->id)->where('version_no', $evidence->current_version)->update(['scan_status' => $result->status->value]);

        if ($result->status === ScanStatus::Infected) {
            $evidence->forceFill(['visibility' => EvidenceVisibility::Private, 'moderation_status' => 'quarantined'])->save();
            $security->event('evidence.infected', null, 'critical', ['evidence' => $evidence->public_id, 'sha256' => $evidence->sha256]);
            $audit->log('evidence.quarantined', $evidence, null, ['scan_message' => $result->message]);

            return;
        }

        if ($evidence->kind === 'image') {
            $clean = $vault->withLocalPath($path, fn (string $local) => $sanitizer->sanitize($local, $evidence->mime_type));
            if ($clean !== null) {
                try {
                    $previewKey = $vault->putFile($clean['path'], 'previews');
                } finally {
                    @unlink($clean['path']);
                }
                $old = $evidence->preview_path;
                $evidence->preview_path = $previewKey;
                $evidence->metadata_json = array_merge($evidence->metadata_json ?? [], [
                    'width' => $clean['source_metadata']['width'] ?? null,
                    'height' => $clean['source_metadata']['height'] ?? null,
                    'preview_mime' => $clean['mime'],
                    'source' => $clean['source_metadata'],
                ]);
                if ($old !== null && $old !== $previewKey) {
                    $vault->delete($old);
                }
            }
        }

        $evidence->save();
    }
}
