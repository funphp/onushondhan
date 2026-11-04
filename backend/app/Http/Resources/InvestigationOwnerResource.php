<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Investigation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** Owner/staff working-copy view. Never used for public endpoints. */
class InvestigationOwnerResource extends JsonResource
{
    public bool $detailed = true;

    public function toArray(Request $request): array
    {
        /** @var Investigation $inv */
        $inv = $this->resource;

        $base = [
            'id' => $inv->public_id,
            'slug' => $inv->slug,
            'title' => $inv->title,
            'status' => $inv->status->value,
            'visibility' => $inv->visibility->value,
            'revision' => $inv->draft_revision,
            'current_version' => $inv->current_version,
            'has_unpublished_changes' => $inv->has_unpublished_changes,
            'published_at' => $inv->published_at?->toIso8601String(),
            'last_published_at' => $inv->last_published_at?->toIso8601String(),
            'withdrawn_at' => $inv->withdrawn_at?->toIso8601String(),
            'archived_at' => $inv->archived_at?->toIso8601String(),
            'public_status_note' => $inv->public_status_note,
            'comments_locked' => $inv->comments_locked,
            'updated_at' => $inv->updated_at->toIso8601String(),
            'created_at' => $inv->created_at->toIso8601String(),
            'counts' => [
                'views' => $inv->views_count, 'shares' => $inv->shares_count, 'comments' => $inv->comments_count,
                'bookmarks' => $inv->bookmarks_count, 'reactions' => $inv->reactions_count, 'tips' => $inv->tips_count,
            ],
            'public_url' => $inv->published_at !== null ? '/investigation/'.$inv->public_id.'/'.$inv->slug : null,
        ];

        if (! $this->detailed) {
            return $base;
        }

        return $base + [
            'summary' => $inv->summary,
            'body' => $inv->body,
            'cover_url' => $inv->draftCoverUrl(),
            'published_cover_url' => $inv->publishedCoverUrl(),
            'category' => $inv->category?->slug,
            'subcategory' => $inv->subcategory?->slug,
            'tags' => $inv->tags->pluck('name')->values(),
            'location_name' => $inv->location_name,
            'location_country' => $inv->location_country,
            'location_lat' => $inv->location_lat !== null ? (float) $inv->location_lat : null,
            'location_lng' => $inv->location_lng !== null ? (float) $inv->location_lng : null,
            'period_start' => $inv->period_start?->toDateString(),
            'period_end' => $inv->period_end?->toDateString(),
            'recommendations' => $inv->recommendations ?? [],
            'entities' => $inv->entities ?? [],
            'findings' => $inv->findings->map(fn ($f) => ['title' => $f->title, 'body' => $f->body])->values(),
            'timeline' => $inv->timeline->map(fn ($t) => ['event_date' => $t->event_date?->toDateString(), 'title' => $t->title, 'description' => $t->description])->values(),
            'sources' => $inv->sources->map(fn ($s) => [
                'title' => $s->title, 'source_type' => $s->source_type, 'publisher' => $s->publisher, 'url' => $s->url,
                'citation_text' => $s->citation_text, 'accessed_at' => $s->accessed_at?->toDateString(), 'is_public' => $s->is_public,
            ])->values(),
            'locations' => $inv->locations->map(fn ($l) => $l->only(['country', 'region', 'district', 'city', 'area', 'latitude', 'longitude', 'is_primary']))->values(),
            'evidence' => $inv->evidence->map(fn ($e) => (new EvidenceResource($e))->owner()->toArray($request))->values(),
        ];
    }
}
