<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\InvestigationCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int|null $parent_id
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property int $sort_order
 * @property string $status
 * @property int|null $investigations_count
 */
class InvestigationCategory extends Model
{
    /** @use HasFactory<InvestigationCategoryFactory> */
    use HasFactory;

    protected $fillable = ['parent_id', 'name', 'slug', 'description', 'sort_order', 'status'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    /** @return BelongsTo<InvestigationCategory, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<InvestigationCategory, $this> */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }
}
