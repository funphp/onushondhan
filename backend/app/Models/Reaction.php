<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ReactionType;
use Illuminate\Database\Eloquent\Model;

/**
 * @property ReactionType $reaction_type
 */
class Reaction extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['reaction_type' => ReactionType::class];
    }
}
