<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ReactionType;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvestigationCardResource;
use App\Http\Resources\InvestigatorSummaryResource;
use App\Models\Investigation;
use App\Models\Investigator;
use App\Services\EngagementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

class EngagementController extends Controller
{
    public function __construct(private readonly EngagementService $service) {}

    public function bookmark(Request $request, string $id): JsonResponse
    {
        $inv = InvestigationController::findReadable($id);
        $this->service->bookmark($request->user(), $inv);

        return $this->investigationState($inv, ['bookmarked' => true]);
    }

    public function unbookmark(Request $request, string $id): JsonResponse
    {
        $inv = InvestigationController::findReadable($id);
        $this->service->unbookmark($request->user(), $inv);

        return $this->investigationState($inv, ['bookmarked' => false]);
    }

    public function react(Request $request, string $id): JsonResponse
    {
        $inv = InvestigationController::findReadable($id);
        $data = $request->validate(['type' => ['required', Rule::enum(ReactionType::class)]]);
        $this->service->react($request->user(), $inv, ReactionType::from($data['type']));

        return $this->investigationState($inv, ['reaction' => $data['type']]);
    }

    public function unreact(Request $request, string $id): JsonResponse
    {
        $inv = InvestigationController::findReadable($id);
        $this->service->unreact($request->user(), $inv);

        return $this->investigationState($inv, ['reaction' => null]);
    }

    public function follow(Request $request, string $slug): JsonResponse
    {
        $investigator = $this->investigator($slug);
        $this->service->follow($request->user(), $investigator);

        return $this->investigatorState($investigator, ['following' => true]);
    }

    public function unfollow(Request $request, string $slug): JsonResponse
    {
        $investigator = $this->investigator($slug);
        $this->service->unfollow($request->user(), $investigator);

        return $this->investigatorState($investigator, ['following' => false]);
    }

    public function support(Request $request, string $slug): JsonResponse
    {
        $investigator = $this->investigator($slug);
        $this->service->support($request->user(), $investigator);

        return $this->investigatorState($investigator, ['supporting' => true]);
    }

    public function revokeSupport(Request $request, string $slug): JsonResponse
    {
        $investigator = $this->investigator($slug);
        $this->service->revokeSupport($request->user(), $investigator);

        return $this->investigatorState($investigator, ['supporting' => false]);
    }

    public function bookmarks(Request $request): AnonymousResourceCollection
    {
        $page = Investigation::query()
            ->join('bookmarks', 'bookmarks.investigation_id', '=', 'investigations.id')
            ->where('bookmarks.user_id', $request->user()->id)
            ->publiclyReadable()
            ->select('investigations.*')
            ->with(['category', 'tags', 'investigator'])
            ->orderByDesc('bookmarks.created_at')
            ->paginate(20);

        return InvestigationCardResource::collection($page);
    }

    public function following(Request $request): AnonymousResourceCollection
    {
        return InvestigatorSummaryResource::collection(
            $request->user()->followedInvestigators()->where('investigators.status', 'active')->orderByPivot('created_at', 'desc')->paginate(30)
        );
    }

    public function supporting(Request $request): AnonymousResourceCollection
    {
        $page = Investigator::query()
            ->join('investigator_supports as s', 's.investigator_id', '=', 'investigators.id')
            ->where('s.user_id', $request->user()->id)->where('s.status', 'active')
            ->where('investigators.status', 'active')
            ->select('investigators.*')->orderByDesc('s.updated_at')->paginate(30);

        return InvestigatorSummaryResource::collection($page);
    }

    private function investigator(string $slug): Investigator
    {
        return Investigator::query()->where('slug', $slug)->where('status', 'active')->firstOrFail();
    }

    /** @param array<string, mixed> $viewer */
    private function investigationState(Investigation $inv, array $viewer): JsonResponse
    {
        $inv->refresh();

        return response()->json(['data' => ['viewer' => $viewer, 'counts' => ['bookmarks' => $inv->bookmarks_count, 'reactions' => $inv->reactions_count]]]);
    }

    /** @param array<string, mixed> $viewer */
    private function investigatorState(Investigator $investigator, array $viewer): JsonResponse
    {
        $investigator->refresh();

        return response()->json(['data' => ['viewer' => $viewer, 'counts' => ['supporters' => $investigator->supporters_count, 'followers' => $investigator->followers_count]]]);
    }
}
