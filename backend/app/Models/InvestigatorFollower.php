<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int|null $user_id
 * @property \Illuminate\Support\Carbon $created_at
 */
class InvestigatorFollower extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'investigator_followers';

    protected $guarded = ['id'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Investigator, $this> */
    public function investigator(): BelongsTo
    {
        return $this->belongsTo(Investigator::class);
    }
}
