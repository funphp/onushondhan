<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\FileVault;
use App\Enums\EvidenceVisibility;
use App\Enums\InvestigationStatus;
use App\Enums\ScanStatus;
use App\Exceptions\ApiException;
use App\Jobs\ProcessEvidenceFile;
use App\Models\Evidence;
use App\Models\EvidenceVersion;
use App\Models\Investigation;
use App\Models\User;
use App\Services\Files\FileInspector;
use App\Services\Files\InspectedFile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

class EvidenceService
{
    public function __construct(
        private readonly FileVault $vault,
        private readonly FileInspector $inspector,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array{caption?: string|null, visibility?: string|null} $meta */
    public function upload(Investigation $investigation, UploadedFile $file, array $meta, User $actor): Evidence
    {
        $this->assertInvestigationAcceptsEvidence($investigation);
        $inspected = $this->inspect($file);

        if (Evidence::query()->where('investigation_id', $investigation->id)->where('sha256', $inspected->sha256)->exists()) {
            throw ApiException::conflict('This file has already been attached to this investigation.', 'duplicate_evidence');
        }

        $key = $this->vault->putFile($inspected->path, 'evidence');

        try {
            $evidence = DB::transaction(function () use ($investigation, $inspected, $meta, $actor, $key): Evidence {
                $evidence = new Evidence;
                $evidence->forceFill([
                    'investigation_id' => $investigation->id,
                    'investigator_id' => $investigation->investigator_id,
                    'original_name' => $inspected->safeName,
                    'storage_disk' => $this->vault->diskName(),
                    'storage_path' => $key,
                    'mime_type' => $inspected->mime,
                    'extension' => $inspected->extension,
                    'kind' => $inspected->kind,
                    'size_bytes' => $inspected->size,
                    'sha256' => $inspected->sha256,
                    'scan_status' => ScanStatus::Pending,
                    'metadata_json' => $inspected->metadata,
                    'visibility' => $meta['visibility'] ?? EvidenceVisibility::Private->value,
                    'caption' => isset($meta['caption']) ? InvestigationService::cleanText((string) $meta['caption']) : null,
                    'sort_order' => (int) Evidence::query()->where('investigation_id', $investigation->id)->max('sort_order') + 1,
                    'current_version' => 1,
                    'uploaded_at' => now(),
                ]);
                $evidence->save();
                $this->recordVersion($evidence, $inspected, $key, 1, $actor);

                $this->audit->log('evidence.uploaded', $evidence, null, [
                    'investigation' => $investigation->public_id, 'sha256' => $inspected->sha256, 'mime' => $inspected->mime,
                    'size' => $inspected->size, 'visibility' => $evidence->visibility->value,
                ], $actor);

                return $evidence;
            });
        } catch (\Throwable $e) {
            $this->vault->delete($key);
            throw $e;
        }

        ProcessEvidenceFile::dispatch($evidence->id);

        return $evidence->refresh();
    }

    public function replaceFile(Evidence $evidence, UploadedFile $file, User $actor): Evidence
    {
        $this->assertInvestigationAcceptsEvidence($evidence->investigation);
        $inspected = $this->inspect($file);
        if ($inspected->sha256 === $evidence->sha256) {
            throw ApiException::conflict('The new file is identical to the current version.', 'duplicate_evidence');
        }
        $key = $this->vault->putFile($inspected->path, 'evidence');

        $evidence = DB::transaction(function () use ($evidence, $inspected, $key, $actor): Evidence {
            /** @var Evidence $locked */
            $locked = Evidence::query()->lockForUpdate()->findOrFail($evidence->id);
            $before = ['sha256' => $locked->sha256, 'version' => $locked->current_version];
            $version = $locked->current_version + 1;
            $locked->forceFill([
                'original_name' => $inspected->safeName,
                'storage_path' => $key,
                'preview_path' => null,
                'mime_type' => $inspected->mime,
                'extension' => $inspected->extension,
                'kind' => $inspected->kind,
                'size_bytes' => $inspected->size,
                'sha256' => $inspected->sha256,
                'scan_status' => ScanStatus::Pending,
                'scan_message' => null,
                'scanned_at' => null,
                'metadata_json' => $inspected->metadata,
                'current_version' => $version,
            ])->save();
            $this->recordVersion($locked, $inspected, $key, $version, $actor);
            $this->audit->log('evidence.replaced', $locked, $before, ['sha256' => $inspected->sha256, 'version' => $version], $actor);

            return $locked;
        });

        ProcessEvidenceFile::dispatch($evidence->id);

        return $evidence->refresh();
    }

    /** @param array{caption?: string|null, visibility?: string, sort_order?: int} $data */
    public function update(Evidence $evidence, array $data, User $actor): Evidence
    {
        return DB::transaction(function () use ($evidence, $data, $actor): Evidence {
            $before = $evidence->only(['caption', 'visibility', 'sort_order']);
            if (isset($data['caption'])) {
                $data['caption'] = InvestigationService::cleanText((string) $data['caption']);
            }
            if (($data['visibility'] ?? null) === EvidenceVisibility::Public->value && $evidence->scan_status === ScanStatus::Infected) {
                throw ApiException::invalidState('Quarantined files cannot be made public.', 'evidence_quarantined');
            }
            $evidence->fill($data)->save();
            $this->audit->log('evidence.updated', $evidence, $before, $evidence->only(['caption', 'visibility', 'sort_order']), $actor);

            return $evidence;
        });
    }

    public function delete(Evidence $evidence, User $actor): void
    {
        DB::transaction(function () use ($evidence, $actor): void {
            $this->audit->log('evidence.deleted', $evidence, ['sha256' => $evidence->sha256, 'visibility' => $evidence->visibility->value], null, $actor);
            $evidence->delete();
        });
    }

    private function inspect(UploadedFile $file): InspectedFile
    {
        return $this->inspector->inspect(
            $file,
            config('onushondhan.evidence.allowed'),
            (int) config('onushondhan.evidence.max_upload_mb') * 1024 * 1024,
        );
    }

    private function recordVersion(Evidence $evidence, InspectedFile $inspected, string $key, int $version, User $actor): void
    {
        EvidenceVersion::query()->create([
            'evidence_id' => $evidence->id,
            'version_no' => $version,
            'storage_path' => $key,
            'original_name' => $inspected->safeName,
            'size_bytes' => $inspected->size,
            'sha256' => $inspected->sha256,
            'mime_type' => $inspected->mime,
            'metadata_json' => $inspected->metadata,
            'scan_status' => ScanStatus::Pending->value,
            'uploaded_by' => $actor->id,
            'created_at' => now(),
        ]);
    }

    private function assertInvestigationAcceptsEvidence(Investigation $investigation): void
    {
        if (in_array($investigation->status, [InvestigationStatus::Restricted, InvestigationStatus::Removed, InvestigationStatus::Archived], true)) {
            throw ApiException::invalidState('Evidence cannot be changed on this investigation in its current state.', 'not_editable');
        }
    }
}
