<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Evidence;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Evidence as seen by the public (default) or by its owner/staff ($ownerView).
 * Storage paths are never included. Public viewers never see the original filename
 * (it can reveal sources) — only a generated one.
 */
class EvidenceResource extends JsonResource
{
    public bool $ownerView = false;

    public function owner(bool $ownerView = true): static
    {
        $this->ownerView = $ownerView;

        return $this;
    }

    public function toArray(Request $request): array
    {
        /** @var Evidence $e */
        $e = $this->resource;
        $meta = $e->metadata_json ?? [];
        $publicMeta = array_intersect_key($meta, array_flip(['width', 'height', 'pages']));

        $base = [
            'id' => $e->public_id,
            'kind' => $e->kind,
            'mime_type' => $e->mime_type,
            'extension' => $e->extension,
            'size_bytes' => $e->size_bytes,
            'sha256' => $e->sha256,
            'caption' => $e->caption,
            'display_name' => self::displayName($e),
            'uploaded_at' => $e->uploaded_at->toIso8601String(),
            'version' => $e->current_version,
            'metadata' => (object) $publicMeta,
            'has_preview' => $e->preview_path !== null,
            'preview_url' => $e->preview_path !== null ? '/api/v1/evidence/'.$e->public_id.'/preview' : null,
            'download_url' => '/api/v1/evidence/'.$e->public_id.'/download',
        ];

        if (! $this->ownerView) {
            return $base;
        }

        return $base + [
            'original_name' => $e->original_name,
            'visibility' => $e->visibility->value,
            'scan_status' => $e->scan_status->value,
            'scan_message' => $e->scan_message,
            'moderation_status' => $e->moderation_status,
            'sort_order' => $e->sort_order,
            'private_metadata' => (object) $meta,
            'investigation_id' => $e->investigation?->public_id,
        ];
    }

    public static function displayName(Evidence $e): string
    {
        return 'evidence-'.strtolower(substr($e->public_id, -8)).'.'.$e->extension;
    }
}
