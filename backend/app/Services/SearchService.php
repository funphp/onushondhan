<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\InvestigationStatus;
use App\Enums\InvestigationVisibility;
use App\Models\Investigation;
use App\Models\InvestigationCategory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Public discovery over MySQL (full-text + structured filters). Kept behind this
 * service so a dedicated search engine can replace it later without API changes.
 */
class SearchService
{
    public const SORTS = ['latest', 'trending', 'most_read', 'most_shared', 'most_discussed', 'relevance', 'oldest'];

    /** @param array<string, mixed> $filters */
    public function search(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $query = Investigation::query()
            ->where('investigations.visibility', InvestigationVisibility::Public)
            ->whereHas('investigator', fn ($i) => $i->where('status', 'active'))
            ->with(['category', 'tags', 'investigator']);

        $q = isset($filters['q']) ? trim((string) $filters['q']) : '';
        $sort = in_array($filters['sort'] ?? null, self::SORTS, true) ? $filters['sort'] : ($q !== '' ? 'relevance' : 'latest');

        // Discovery rails exclude archived reports; search and profiles include them.
        $statuses = [InvestigationStatus::Published];
        if ($q !== '' || ! empty($filters['investigator']) || ($filters['include_archived'] ?? false)) {
            $statuses[] = InvestigationStatus::Archived;
        }
        $query->whereIn('investigations.status', $statuses);

        if ($q !== '') {
            $this->applyText($query, $q, $sort === 'relevance');
        }

        if (! empty($filters['category'])) {
            $category = InvestigationCategory::query()->where('slug', $filters['category'])->first();
            if ($category === null) {
                $query->whereRaw('1 = 0');
            } else {
                $query->where(fn ($w) => $w->where('category_id', $category->id)->orWhere('subcategory_id', $category->id));
            }
        }
        if (! empty($filters['tag'])) {
            $query->whereHas('tags', fn ($t) => $t->where('slug', $filters['tag']));
        }
        if (! empty($filters['country'])) {
            $country = strtoupper((string) $filters['country']);
            $query->where(fn ($w) => $w->where('location_country', $country)->orWhereHas('locations', fn ($l) => $l->where('country', $country)));
        }
        if (! empty($filters['location'])) {
            $loc = (string) $filters['location'];
            $query->where(fn ($w) => $w->where('location_name', 'like', '%'.$this->escapeLike($loc).'%')
                ->orWhereHas('locations', fn ($l) => $l->where('district', $loc)->orWhere('city', $loc)->orWhere('region', $loc)->orWhere('area', $loc)));
        }
        if (! empty($filters['investigator'])) {
            $query->whereHas('investigator', fn ($i) => $i->where('slug', $filters['investigator']));
        }
        if (! empty($filters['from'])) {
            $query->where('published_at', '>=', $filters['from']);
        }
        if (! empty($filters['to'])) {
            $query->where('published_at', '<', date('Y-m-d', strtotime($filters['to'].' +1 day')));
        }

        match ($sort) {
            'trending' => $query->orderByDesc('trending_score')->orderByDesc('published_at'),
            'most_read' => $query->orderByDesc('views_count')->orderByDesc('published_at'),
            'most_shared' => $query->orderByDesc('shares_count')->orderByDesc('published_at'),
            'most_discussed' => $query->orderByDesc('comments_count')->orderByDesc('published_at'),
            'oldest' => $query->orderBy('published_at'),
            'relevance' => $q !== '' ? $query->orderByDesc('relevance')->orderByDesc('published_at') : $query->orderByDesc('published_at'),
            default => $query->orderByDesc('published_at')->orderByDesc('investigations.id'),
        };

        return $query->paginate(min(50, max(1, $perPage)))->withQueryString();
    }

    /** @param Builder<Investigation> $query */
    private function applyText(Builder $query, string $q, bool $withRelevance): void
    {
        $terms = $this->booleanTerms($q);
        if ($terms === '') {
            $like = '%'.$this->escapeLike($q).'%';
            $query->where(fn ($w) => $w->where('published_title', 'like', $like)->orWhere('published_summary', 'like', $like));
            if ($withRelevance) {
                $query->select('investigations.*')->selectRaw('0 as relevance');
            }

            return;
        }
        $query->whereRaw('MATCH(published_title, published_summary, published_body) AGAINST (? IN BOOLEAN MODE)', [$terms]);
        if ($withRelevance) {
            $query->select('investigations.*')
                ->selectRaw('MATCH(published_title, published_summary, published_body) AGAINST (? IN BOOLEAN MODE) as relevance', [$terms]);
        }
    }

    /**
     * Build a safe boolean-mode query: user operators are stripped; every term of 3+
     * characters is required and prefix-matched.
     */
    private function booleanTerms(string $q): string
    {
        $clean = preg_replace('/[+\-><()~*"@]+/u', ' ', $q) ?? '';
        $words = preg_split('/\s+/u', mb_substr($clean, 0, 200), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $words = array_slice(array_filter($words, fn ($w) => mb_strlen($w) >= 3), 0, 10);

        return implode(' ', array_map(fn ($w) => '+'.$w.'*', $words));
    }

    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }
}
