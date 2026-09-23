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
class InvestigationLocation extends Model
{
    protected $table = 'investigation_locations';

    protected $fillable = ['country', 'region', 'district', 'city', 'area', 'latitude', 'longitude', 'is_primary'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean', 'latitude' => 'float', 'longitude' => 'float'];
    }

    /** @return BelongsTo<Investigation, $this> */
    public function investigation(): BelongsTo
    {
        return $this->belongsTo(Investigation::class);
    }
}
