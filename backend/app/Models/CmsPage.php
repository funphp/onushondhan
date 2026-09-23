<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\CmsStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $slug
 * @property string $title
 * @property string|null $excerpt
 * @property string $body
 * @property string $page_type
 * @property bool $requires_legal_review
 * @property CmsStatus $status
 * @property string|null $seo_title
 * @property string|null $seo_description
 * @property string|null $canonical_url
 * @property \Illuminate\Support\Carbon|null $published_at
 * @property \Illuminate\Support\Carbon $updated_at
 */
class CmsPage extends Model
{
    protected $fillable = [
        'slug', 'title', 'excerpt', 'body', 'page_type', 'requires_legal_review', 'status',
        'seo_title', 'seo_description', 'canonical_url', 'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CmsStatus::class,
            'requires_legal_review' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /** @param Builder<CmsPage> $query */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', CmsStatus::Published)->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()));
    }
}
