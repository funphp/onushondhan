<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\FileVault;
use App\Enums\InvestigationStatus;
use App\Enums\InvestigationVisibility;
use App\Enums\SharePlatform;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvestigationCardResource;
use App\Http\Resources\PublicInvestigationResource;
use App\Models\Bookmark;
use App\Models\Investigation;
use App\Models\Investigator;
use App\Models\InvestigatorFollower;
use App\Models\InvestigatorSupport;
use App\Models\Reaction;
use App\Models\Share;
use App\Services\AnalyticsService;
use App\Services\SearchService;
use App\Support\CoverImageResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Public discovery and reading. Only published, non-private content is reachable here. */
class InvestigationController extends Controller
{
    public function index(Request $request, SearchService $search, AnalyticsService $analytics): AnonymousResourceCollection
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'category' => ['nullable', 'string', 'max:140'],
            'tag' => ['nullable', 'string', 'max:100'],
            'country' => ['nullable', 'string', 'size:2'],
            'location' => ['nullable', 'string', 'max:120'],
            'investigator' => ['nullable', 'string', 'max:140'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'sort' => ['nullable', Rule::in(SearchService::SORTS)],
            'page' => ['nullable', 'integer', 'min:1', 'max:1000'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        if (! empty($filters['q'])) {
            $analytics->record('search_performed', ['user_id' => $request->user()?->id], ['q_length' => mb_strlen($filters['q'])]);
        }

        return InvestigationCardResource::collection($search->search($filters, (int) ($filters['per_page'] ?? 12)));
    }

    public function byInvestigator(Request $request, string $slug, SearchService $search, AnalyticsService $analytics): AnonymousResourceCollection
    {
        Investigator::query()->where('slug', $slug)->where('status', 'active')->firstOrFail();
        $request->merge(['investigator' => $slug]);

        return $this->index($request, $search, $analytics);
    }

    public function show(Request $request, string $id): PublicInvestigationResource
    {
        $inv = $this->findReadable($id);
        $inv->load(['category', 'subcategory', 'tags', 'locations', 'investigator', 'currentVersion']);

        $resource = new PublicInvestigationResource($inv);
        if ($user = $request->user()) {
            $resource->viewer = [
                'bookmarked' => Bookmark::query()->where('user_id', $user->id)->where('investigation_id', $inv->id)->exists(),
                'reaction' => Reaction::query()->where('user_id', $user->id)->where('investigation_id', $inv->id)->first()?->reaction_type->value,
                'following' => InvestigatorFollower::query()->where('user_id', $user->id)->where('investigator_id', $inv->investigator_id)->exists(),
                'supporting' => InvestigatorSupport::query()->where('user_id', $user->id)->where('investigator_id', $inv->investigator_id)->where('status', 'active')->exists(),
                'is_owner' => $user->investigator?->id === $inv->investigator_id,
            ];
        }

        return $resource;
    }

    /** Published header image. Hidden with the content when a report is withdrawn, restricted or removed. */
    public function cover(string $id, FileVault $vault): StreamedResponse
    {
        $inv = $this->findReadable($id);
        abort_unless(in_array($inv->status, [InvestigationStatus::Published, InvestigationStatus::Archived], true), 404);

        return CoverImageResponse::make($vault, $inv->published_cover_path, true);
    }

    /** Public, metadata-only version history (content of superseded versions is not public). */
    public function versions(string $id): JsonResponse
    {
        $inv = $this->findReadable($id);

        return response()->json(['data' => $inv->versions()->get(['version_no', 'change_reason', 'created_at'])->map(fn ($v) => [
            'version' => $v->version_no, 'change_reason' => $v->change_reason, 'created_at' => $v->created_at->toIso8601String(),
        ])]);
    }

    public function related(string $id): AnonymousResourceCollection
    {
        $inv = $this->findReadable($id);
        $tagIds = $inv->tags()->pluck('investigation_tags.id');

        $related = Investigation::query()->publiclyListed()
            ->where('investigations.id', '!=', $inv->id)
            ->where(function ($q) use ($inv, $tagIds) {
                $q->where('category_id', $inv->category_id)
                    ->orWhere('investigator_id', $inv->investigator_id)
                    ->orWhereHas('tags', fn ($t) => $t->whereIn('investigation_tags.id', $tagIds));
            })
            ->with(['category', 'tags', 'investigator'])
            ->orderByDesc('trending_score')->orderByDesc('published_at')
            ->limit(6)->get();

        return InvestigationCardResource::collection($related);
    }

    /** View beacon (called client-side so crawlers and prefetches don't inflate counts). */
    public function recordView(Request $request, string $id, AnalyticsService $analytics): JsonResponse
    {
        $inv = $this->findReadable($id);
        $counted = $inv->status === InvestigationStatus::Published ? $analytics->recordView($inv, $request) : false;

        return response()->json(['counted' => $counted]);
    }

    public function share(Request $request, string $id, AnalyticsService $analytics): JsonResponse
    {
        $inv = $this->findReadable($id);
        $data = $request->validate(['platform' => ['required', Rule::enum(SharePlatform::class)]]);

        DB::transaction(function () use ($request, $inv, $data, $analytics): void {
            Share::query()->create([
                'user_id' => $request->user()?->id,
                'investigation_id' => $inv->id,
                'platform' => $data['platform'],
                'referrer' => null,
                'created_at' => now(),
            ]);
            Investigation::query()->whereKey($inv->id)->increment('shares_count');
            $analytics->record('investigation_share', ['user_id' => $request->user()?->id, 'investigation_id' => $inv->id, 'investigator_id' => $inv->investigator_id], ['platform' => $data['platform']]);
        });

        return response()->json(['message' => __('Share recorded.')]);
    }

    /**
     * Resolve by public id (canonical) or slug. Drafts and private investigations are 404.
     * Withdrawn/restricted/removed resolve to a tombstone so links don't silently break.
     */
    public static function findReadable(string $id): Investigation
    {
        $query = Investigation::query()
            ->whereIn('status', [InvestigationStatus::Published, InvestigationStatus::Archived, InvestigationStatus::Withdrawn, InvestigationStatus::Restricted, InvestigationStatus::Removed])
            ->where('visibility', '!=', InvestigationVisibility::Private)
            ->where('current_version', '>', 0)
            ->whereHas('investigator', fn ($q) => $q->where('status', 'active'));

        $inv = (clone $query)->where('public_id', strtoupper($id))->first()
            ?? $query->where('slug', $id)->orderByDesc('published_at')->first();
        abort_if($inv === null, 404);

        return $inv;
    }
}
