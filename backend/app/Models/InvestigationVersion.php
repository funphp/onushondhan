<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable published snapshot.
 *
 * @property int $id
 * @property int $investigation_id
 * @property int $version_no
 * @property string $title
 * @property string|null $summary
 * @property string|null $body
 * @property array<int, array<string, mixed>>|null $findings_json
 * @property array<int, string>|null $recommendations_json
 * @property array<int, array<string, mixed>>|null $timeline_json
 * @property array<int, array<string, mixed>>|null $sources_json
 * @property array<int, array<string, mixed>>|null $entities_json
 * @property array<string, mixed>|null $meta_json
 * @property array<string, mixed>|null $identity_snapshot
 * @property string|null $change_reason
 * @property Carbon $created_at
 */
class InvestigationVersion extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'findings_json' => 'array',
            'recommendations_json' => 'array',
            'timeline_json' => 'array',
            'sources_json' => 'array',
            'entities_json' => 'array',
            'meta_json' => 'array',
            'identity_snapshot' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Investigation versions are immutable.'));
    }

    /** @return BelongsTo<Investigation, $this> */
    public function investigation(): BelongsTo
    {
        return $this->belongsTo(Investigation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by');
    }
}
