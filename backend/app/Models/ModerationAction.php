<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ModerationActionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int|null $moderator_user_id
 * @property string $target_type
 * @property int $target_id
 * @property ModerationActionType $action
 * @property string $reason
 * @property array<string, mixed>|null $details
 * @property int|null $complaint_id
 * @property Carbon $created_at
 */
class ModerationAction extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['action' => ModerationActionType::class, 'details' => 'array'];
    }

    protected static function booted(): void
    {
        static::updating(fn () => throw new \LogicException('Moderation actions are append-only.'));
        static::deleting(fn () => throw new \LogicException('Moderation actions are append-only.'));
    }

    /** @return BelongsTo<User, $this> */
    public function moderator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'moderator_user_id');
    }

    /** @return BelongsTo<Complaint, $this> */
    public function complaint(): BelongsTo
    {
        return $this->belongsTo(Complaint::class);
    }
}
