<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ScanStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property int $id
 * @property string $public_id
 * @property int $tip_id
 * @property string $storage_disk
 * @property string $storage_path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property ScanStatus $scan_status
 * @property-read Tip $tip
 */
class TipAttachment extends Model
{
    use HasUlids;
    use SoftDeletes;

    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected $hidden = ['storage_disk', 'storage_path'];

    protected function casts(): array
    {
        return ['scan_status' => ScanStatus::class];
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

    /** @return BelongsTo<Tip, $this> */
    public function tip(): BelongsTo
    {
        return $this->belongsTo(Tip::class);
    }
}
