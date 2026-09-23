<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvestigationStatus;
use App\Enums\InvestigationVisibility;
use App\Http\Controllers\Controller;
use App\Models\CmsPage;
use App\Models\Investigation;
use App\Models\InvestigationCategory;
use App\Models\Investigator;
use Illuminate\Http\JsonResponse;

class PageController extends Controller
{
    public function index(): JsonResponse
    {
        $pages = CmsPage::query()->published()->where('page_type', '!=', 'announcement')->orderBy('title')->get();

        return response()->json(['data' => $pages->map(fn (CmsPage $p) => $this->present($p, false))]);
    }

    public function show(string $slug): JsonResponse
    {
        $page = CmsPage::query()->published()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => $this->present($page, true)]);
    }

    public function announcements(): JsonResponse
    {
        $pages = CmsPage::query()->published()->where('page_type', 'announcement')->orderByDesc('published_at')->limit(20)->get();

        return response()->json(['data' => $pages->map(fn (CmsPage $p) => $this->present($p, true))]);
    }

    /** Indexable URLs for the frontend sitemap. Only public, published, indexable content. */
    public function sitemap(): JsonResponse
    {
        $investigations = Investigation::query()
            ->whereIn('status', [InvestigationStatus::Published, InvestigationStatus::Archived])
            ->where('visibility', InvestigationVisibility::Public)
            ->whereHas('investigator', fn ($q) => $q->where('status', 'active'))
            ->orderByDesc('last_published_at')->limit(45000)
            ->get(['public_id', 'slug', 'last_published_at']);

        return response()->json(['data' => [
            'investigations' => $investigations->map(fn ($i) => ['path' => '/investigation/'.$i->public_id.'/'.$i->slug, 'lastmod' => $i->last_published_at?->toIso8601String()]),
            'investigators' => Investigator::query()->where('status', 'active')->where('published_investigations_count', '>', 0)->get(['slug', 'updated_at'])
                ->map(fn ($i) => ['path' => '/investigators/'.$i->slug, 'lastmod' => $i->updated_at?->toIso8601String()]),
            'categories' => InvestigationCategory::query()->where('status', 'active')->get(['slug', 'updated_at'])
                ->map(fn ($c) => ['path' => '/category/'.$c->slug, 'lastmod' => $c->updated_at?->toIso8601String()]),
            'pages' => CmsPage::query()->published()->where('page_type', '!=', 'announcement')->get(['slug', 'updated_at'])
                ->map(fn ($p) => ['path' => '/pages/'.$p->slug, 'lastmod' => $p->updated_at?->toIso8601String()]),
        ]]);
    }

    /** @return array<string, mixed> */
    private function present(CmsPage $p, bool $withBody): array
    {
        return [
            'slug' => $p->slug,
            'title' => $p->title,
            'excerpt' => $p->excerpt,
            'body' => $withBody ? $p->body : null,
            'page_type' => $p->page_type,
            'requires_legal_review' => $p->requires_legal_review,
            'seo_title' => $p->seo_title,
            'seo_description' => $p->seo_description,
            'canonical_url' => $p->canonical_url,
            'published_at' => $p->published_at?->toIso8601String(),
            'updated_at' => $p->updated_at->toIso8601String(),
        ];
    }
}
