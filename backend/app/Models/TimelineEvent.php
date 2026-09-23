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
class TimelineEvent extends Model
{
    protected $table = 'investigation_timelines';

    protected $fillable = ['event_date', 'title', 'description', 'sort_order'];

    protected function casts(): array
    {
        return ['event_date' => 'date'];
    }

    /** @return BelongsTo<Investigation, $this> */
    public function investigation(): BelongsTo
    {
        return $this->belongsTo(Investigation::class);
    }
}
