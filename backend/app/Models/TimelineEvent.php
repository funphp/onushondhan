<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $investigation_id
 * @property int $sort_order
 * @property Carbon|null $event_date
 * @property string $title
 * @property string|null $description
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
