<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CommentReportReason;
use App\Enums\CommentStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\CommentVote;
use App\Services\CommentService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CommentController extends Controller
{
    public function __construct(private readonly CommentService $service) {}

    public function index(Request $request, string $id): JsonResponse
    {
        $inv = InvestigationController::findReadable($id);
        abort_unless(in_array($inv->status->value, ['published', 'archived'], true), 404);
        $sort = $request->query('sort') === 'new' ? 'new' : 'top';

        // Visible roots, plus removed/hidden roots that still have replies (as placeholders).
        $visibleOrHasReplies = function (Builder $q) {
            $q->where('status', CommentStatus::Visible)
                ->orWhere(fn ($w) => $w->where('replies_count', '>', 0));
        };
        $roots = Comment::withTrashed()
            ->where('investigation_id', $inv->id)->whereNull('parent_id')
            ->where($visibleOrHasReplies)
            ->with(['user:id,username', 'replies' => fn ($q) => $this->replyQuery($q), 'replies.replies' => fn ($q) => $this->replyQuery($q)])
            ->orderByDesc('is_pinned')
            ->when($sort === 'top', fn ($q) => $q->orderByDesc('score'))
            ->orderByDesc('created_at')
            ->paginate(min(50, max(1, $request->integer('per_page', 20))));

        $this->prepareResourceContext($request, $inv, collect($roots->items()));

        return response()->json([
            'data' => CommentResource::collection($roots->items())->toArray($request),
            'meta' => ['current_page' => $roots->currentPage(), 'last_page' => $roots->lastPage(), 'total' => $inv->comments_count, 'locked' => $inv->comments_locked || $inv->status->value !== 'published'],
        ]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $inv = InvestigationController::findReadable($id);
        $this->authorize('create', Comment::class);
        $data = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:5000'],
            'parent_id' => ['nullable', 'string', 'size:26'],
        ]);
        $comment = $this->service->create($inv, $request->user(), $data['body'], $data['parent_id'] ?? null);
        $comment->load(['user:id,username', 'parent:id,public_id']);
        $this->prepareResourceContext($request, $inv, collect([$comment]));

        return response()->json(['data' => (new CommentResource($comment))->toArray($request)], 201);
    }

    public function update(Request $request, Comment $comment): JsonResponse
    {
        $this->authorize('update', $comment);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:5000']]);
        $comment = $this->service->update($comment, $data['body']);
        $comment->load(['user:id,username', 'parent:id,public_id', 'investigation.investigator']);
        $this->prepareResourceContext($request, $comment->investigation, collect([$comment]));

        return response()->json(['data' => (new CommentResource($comment))->toArray($request)]);
    }

    public function destroy(Request $request, Comment $comment): JsonResponse
    {
        abort_unless($comment->user_id === $request->user()->id, 404);
        $this->authorize('delete', $comment);
        $this->service->deleteOwn($comment);

        return response()->json(['message' => 'Comment deleted.']);
    }

    public function vote(Request $request, Comment $comment): JsonResponse
    {
        $this->assertPubliclyVisible($comment);
        $data = $request->validate(['value' => ['required', 'integer', Rule::in([1, -1])]]);
        $comment = $this->service->vote($comment, $request->user(), (int) $data['value']);

        return response()->json(['data' => ['score' => $comment->score, 'vote' => (int) $data['value']]]);
    }

    public function unvote(Request $request, Comment $comment): JsonResponse
    {
        $this->assertPubliclyVisible($comment);
        $comment = $this->service->unvote($comment, $request->user());

        return response()->json(['data' => ['score' => $comment->score, 'vote' => 0]]);
    }

    public function report(Request $request, Comment $comment): JsonResponse
    {
        $this->assertPubliclyVisible($comment);
        $data = $request->validate([
            'reason' => ['required', Rule::enum(CommentReportReason::class)],
            'details' => ['nullable', 'string', 'max:2000'],
        ]);
        $this->service->report($comment, $request->user(), CommentReportReason::from($data['reason']), $data['details'] ?? null);

        return response()->json(['message' => 'Thank you. Our moderators will review this comment.'], 201);
    }

    private function assertPubliclyVisible(Comment $comment): void
    {
        $inv = $comment->investigation;
        abort_if($inv === null || ! $inv->isPubliclyReadable() || $comment->status !== CommentStatus::Visible, 404);
    }

    private function replyQuery($q)
    {
        return $q->withTrashed()
            ->where(fn ($w) => $w->where('status', CommentStatus::Visible)->orWhere('replies_count', '>', 0))
            ->with('user:id,username')->orderBy('created_at');
    }

    private function prepareResourceContext(Request $request, $inv, $comments): void
    {
        $inv->loadMissing('investigator');
        CommentResource::$ownerUserId = $inv->investigator->user_id;
        CommentResource::$ownerIdentity = ['public_name' => $inv->investigator->public_name, 'slug' => $inv->investigator->slug];
        CommentResource::$viewerId = $request->user()?->id;
        CommentResource::$viewerVotes = [];
        if ($request->user() !== null) {
            $ids = $comments->flatMap(fn ($c) => collect([$c])->merge($c->relationLoaded('replies') ? $c->replies->flatMap(fn ($r) => collect([$r])->merge($r->relationLoaded('replies') ? $r->replies : [])) : []))->pluck('id');
            CommentResource::$viewerVotes = CommentVote::query()->where('user_id', $request->user()->id)->whereIn('comment_id', $ids)->pluck('value', 'comment_id')->map(fn ($v) => (int) $v)->all();
        }
    }
}
