<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EvidenceVisibility;
use App\Enums\ScanStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;

/**
 * storage_path / preview_path are internal and never serialised to clients.
 *
 * @property int $id
 * @property string $public_id
 * @property int $investigation_id
 * @property int $investigator_id
 * @property string $original_name
 * @property string $storage_disk
 * @property string $storage_path
 * @property string|null $preview_path
 * @property string $mime_type
 * @property string $extension
 * @property string $kind
 * @property int $size_bytes
 * @property string $sha256
 * @property ScanStatus $scan_status
 * @property string|null $scan_message
 * @property array<string, mixed>|null $metadata_json
 * @property EvidenceVisibility $visibility
 * @property string $moderation_status
 * @property string|null $caption
 * @property int $sort_order
 * @property int $current_version
 * @property Carbon $uploaded_at
 * @property Carbon|null $scanned_at
 * @property-read Investigation $investigation
 * @property-read Investigator $investigator
 */
class Evidence extends Model
{
    use HasUlids;
    use SoftDeletes;

    protected $table = 'evidence';

    protected $fillable = ['caption', 'visibility', 'sort_order'];

    protected $hidden = ['storage_disk', 'storage_path', 'preview_path'];

    protected $attributes = [
        'scan_status' => 'pending',
        'visibility' => 'private',
        'moderation_status' => 'visible',
    ];

    protected function casts(): array
    {
        return [
            'scan_status' => ScanStatus::class,
            'visibility' => EvidenceVisibility::class,
            'metadata_json' => 'array',
            'uploaded_at' => 'datetime',
            'scanned_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function isScanAcceptableForPublic(): bool
    {
        if ($this->scan_status === ScanStatus::Clean) {
            return true;
        }

        return $this->scan_status === ScanStatus::NotScanned && ! config('onushondhan.evidence.require_clean_scan');
    }

    public function isHiddenByModeration(): bool
    {
        return $this->moderation_status !== 'visible';
    }

    /** @return BelongsTo<Investigation, $this> */
    public function investigation(): BelongsTo
    {
        return $this->belongsTo(Investigation::class)->withTrashed();
    }

    /** @return BelongsTo<Investigator, $this> */
    public function investigator(): BelongsTo
    {
        return $this->belongsTo(Investigator::class);
    }

    /** @return HasMany<EvidenceVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(EvidenceVersion::class)->orderByDesc('version_no');
    }

    /** @return HasMany<EvidenceAccessLog, $this> */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(EvidenceAccessLog::class);
    }
}
