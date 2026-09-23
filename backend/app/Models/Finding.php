<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $investigation_id
 * @property int $sort_order
 */
class Finding extends Model
{
    protected $table = 'findings';

    protected $fillable = ['title', 'body', 'sort_order', 'status'];

    protected function casts(): array
    {
        return [];
    }

    /** @return BelongsTo<Investigation, $this> */
    public function investigation(): BelongsTo
    {
        return $this->belongsTo(Investigation::class);
    }
}
