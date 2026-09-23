<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Investigation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Str;

/** Listing card built from the public projection (never the working copy). */
class InvestigationCardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Investigation $inv */
        $inv = $this->resource;

        return [
            'id' => $inv->public_id,
            'slug' => $inv->slug,
            'title' => $inv->published_title,
            'summary' => Str::limit((string) $inv->published_summary, 320),
            'status' => $inv->status->value,
            'category' => $inv->relationLoaded('category') && $inv->category ? ['name' => $inv->category->name, 'slug' => $inv->category->slug] : null,
            'tags' => $inv->relationLoaded('tags') ? $inv->tags->map(fn ($t) => ['name' => $t->name, 'slug' => $t->slug])->values() : [],
            'location_name' => $inv->location_name,
            'location_country' => $inv->location_country,
            'investigator' => $inv->relationLoaded('investigator') ? new InvestigatorSummaryResource($inv->investigator) : null,
            'published_at' => $inv->published_at?->toIso8601String(),
            'updated_at' => $inv->last_published_at?->toIso8601String(),
            'version' => $inv->current_version,
            'counts' => [
                'views' => $inv->views_count,
                'shares' => $inv->shares_count,
                'comments' => $inv->comments_count,
                'bookmarks' => $inv->bookmarks_count,
                'reactions' => $inv->reactions_count,
            ],
            'url' => '/investigation/'.$inv->public_id.'/'.$inv->slug,
        ];
    }
}
