<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\InvestigationStatus;
use App\Enums\InvestigationVisibility;
use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Models\InvestigationCategory;
use App\Models\InvestigationTag;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class TaxonomyController extends Controller
{
    public function categories(): AnonymousResourceCollection
    {
        $categories = InvestigationCategory::query()
            ->whereNull('parent_id')->where('status', 'active')
            ->with(['children' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('sort_order')->orderBy('name')->get();

        $counts = $this->publishedCountsByCategory();
        $categories->each(function ($c) use ($counts) {
            $c->investigations_count = ($counts[$c->id] ?? 0);
        });

        return CategoryResource::collection($categories);
    }

    public function category(string $slug): CategoryResource
    {
        $category = InvestigationCategory::query()->where('slug', $slug)->where('status', 'active')
            ->with(['children' => fn ($q) => $q->where('status', 'active'), 'parent'])->firstOrFail();

        return new CategoryResource($category);
    }

    public function tags(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $tags = InvestigationTag::query()
            ->select('investigation_tags.name', 'investigation_tags.slug', DB::raw('COUNT(i.id) as investigations_count'))
            ->join('investigation_tag_map as m', 'm.tag_id', '=', 'investigation_tags.id')
            ->join('investigations as i', 'i.id', '=', 'm.investigation_id')
            ->where('i.status', InvestigationStatus::Published->value)
            ->where('i.visibility', InvestigationVisibility::Public->value)
            ->whereNull('i.deleted_at')
            ->when($q !== '', fn ($query) => $query->where('investigation_tags.name', 'like', str_replace(['%', '_'], ['\%', '\_'], $q).'%'))
            ->groupBy('investigation_tags.id', 'investigation_tags.name', 'investigation_tags.slug')
            ->orderByDesc('investigations_count')
            ->limit(min(100, max(1, $request->integer('limit', 50))))
            ->get();

        return response()->json(['data' => $tags->map(fn ($t) => ['name' => $t->name, 'slug' => $t->slug, 'investigations_count' => (int) $t->investigations_count])]);
    }

    public function tag(string $slug): JsonResponse
    {
        $tag = InvestigationTag::query()->where('slug', $slug)->firstOrFail();

        return response()->json(['data' => ['name' => $tag->name, 'slug' => $tag->slug]]);
    }

    public function locations(): JsonResponse
    {
        $data = Cache::remember('taxonomy.locations', 300, function () {
            $countries = DB::table('investigations')
                ->select('location_country as country', DB::raw('COUNT(*) as investigations_count'))
                ->where('status', InvestigationStatus::Published->value)->where('visibility', InvestigationVisibility::Public->value)
                ->whereNull('deleted_at')->whereNotNull('location_country')
                ->groupBy('location_country')->orderByDesc('investigations_count')->get();
            $districts = DB::table('investigation_locations as l')
                ->join('investigations as i', 'i.id', '=', 'l.investigation_id')
                ->select('l.country', 'l.district', DB::raw('COUNT(DISTINCT i.id) as investigations_count'))
                ->where('i.status', InvestigationStatus::Published->value)->where('i.visibility', InvestigationVisibility::Public->value)
                ->whereNull('i.deleted_at')->whereNotNull('l.district')
                ->groupBy('l.country', 'l.district')->orderByDesc('investigations_count')->limit(200)->get();

            return ['countries' => $countries, 'districts' => $districts];
        });

        return response()->json(['data' => $data]);
    }

    /** @return array<int, int> */
    private function publishedCountsByCategory(): array
    {
        return DB::table('investigations')
            ->select('category_id', DB::raw('COUNT(*) as c'))
            ->where('status', InvestigationStatus::Published->value)->where('visibility', InvestigationVisibility::Public->value)
            ->whereNull('deleted_at')->whereNotNull('category_id')
            ->groupBy('category_id')->pluck('c', 'category_id')->map(fn ($v) => (int) $v)->all();
    }
}
