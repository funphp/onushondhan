<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TipSenderType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $tip_id
 * @property int|null $sender_user_id
 * @property TipSenderType $sender_type
 * @property string $body
 * @property Carbon $created_at
 * @property Carbon|null $read_at
 */
class TipMessage extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['sender_type' => TipSenderType::class, 'read_at' => 'datetime'];
    }

    /** @return BelongsTo<Tip, $this> */
    public function tip(): BelongsTo
    {
        return $this->belongsTo(Tip::class);
    }
}
