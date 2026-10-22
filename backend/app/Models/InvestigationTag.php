<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Slug;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $name
 * @property string $slug
 */
class InvestigationTag extends Model
{
    protected $fillable = ['name', 'slug'];

    public static function findOrCreateByName(string $name): self
    {
        $name = trim(preg_replace('/\s+/', ' ', strip_tags($name)) ?? '');
        $slug = Slug::make($name, 80, 'tag');

        return self::query()->firstOrCreate(['slug' => $slug], ['name' => Str::limit($name, 80, '')]);
    }

    /** @return BelongsToMany<Investigation, $this> */
    public function investigations(): BelongsToMany
    {
        return $this->belongsToMany(Investigation::class, 'investigation_tag_map', 'tag_id', 'investigation_id');
    }
}
