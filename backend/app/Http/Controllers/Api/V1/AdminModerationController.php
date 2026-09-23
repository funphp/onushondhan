<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\CommentStatus;
use App\Enums\ComplaintStatus;
use App\Enums\ModerationActionType;
use App\Enums\ScanStatus;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Comment;
use App\Models\CommentReport;
use App\Models\Complaint;
use App\Models\Evidence;
use App\Models\Investigation;
use App\Models\Investigator;
use App\Models\User;
use App\Services\ComplaintService;
use App\Services\IdentityAccessService;
use App\Services\ModerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Staff moderation API. Every route is permission-gated in routes/api.php. */
class AdminModerationController extends Controller
{
    public function queue(): JsonResponse
    {
        return response()->json(['data' => [
            'open_complaints' => Complaint::query()->whereIn('status', [ComplaintStatus::Submitted, ComplaintStatus::UnderReview, ComplaintStatus::Escalated])->count(),
            'open_comment_reports' => CommentReport::query()->where('status', 'open')->count(),
            'pending_comments' => Comment::query()->where('status', CommentStatus::Pending)->count(),
            'quarantined_evidence' => Evidence::query()->where('scan_status', ScanStatus::Infected)->count(),
            'failed_scans' => Evidence::query()->where('scan_status', ScanStatus::Failed)->count(),
        ]]);
    }

    public function complaints(Request $request): JsonResponse
    {
        $status = $request->query('status');
        $page = Complaint::query()
            ->when($status, fn ($q) => $q->where('status', $status))
            ->with(['investigation:id,public_id,published_title', 'comment:id,public_id', 'evidence:id,public_id'])
            ->latest()->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (Complaint $c) => $this->presentComplaint($c)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function complaint(Complaint $complaint): JsonResponse
    {
        $complaint->load(['investigation:id,public_id,published_title', 'comment:id,public_id,body', 'evidence:id,public_id', 'moderationActions']);

        return response()->json(['data' => $this->presentComplaint($complaint, true)]);
    }

    public function updateComplaint(Request $request, Complaint $complaint, ComplaintService $service): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(ComplaintStatus::class)],
            'resolution' => ['nullable', 'string', 'max:5000'],
            'internal_notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $service->updateStatus($complaint, ComplaintStatus::from($data['status']), $request->user(), $data['resolution'] ?? null, $data['internal_notes'] ?? null);

        return $this->complaint($complaint->refresh());
    }

    public function act(Request $request, ModerationService $moderation): JsonResponse
    {
        $data = $request->validate([
            'target_type' => ['required', Rule::in(['investigation', 'comment', 'evidence', 'user', 'investigator'])],
            'target_id' => ['required', 'string', 'max:40'],
            'action' => ['required', Rule::enum(ModerationActionType::class)],
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'details' => ['nullable', 'array'],
            'details.public_note' => ['nullable', 'string', 'max:500'],
            'details.days' => ['nullable', 'integer', 'min:1', 'max:3650'],
            'complaint_id' => ['nullable', 'string', 'size:26'],
        ]);

        $target = match ($data['target_type']) {
            'investigation' => Investigation::query()->where('public_id', strtoupper($data['target_id']))->firstOrFail(),
            'comment' => Comment::withTrashed()->where('public_id', $data['target_id'])->firstOrFail(),
            'evidence' => Evidence::query()->where('public_id', $data['target_id'])->firstOrFail(),
            'user' => User::query()->where('public_id', $data['target_id'])->firstOrFail(),
            default => Investigator::query()->where('public_id', $data['target_id'])->firstOrFail(),
        };
        $complaint = ! empty($data['complaint_id']) ? Complaint::query()->where('public_id', $data['complaint_id'])->firstOrFail() : null;

        $record = $moderation->apply($request->user(), $data['target_type'], $target, ModerationActionType::from($data['action']), $data['reason'], $data['details'] ?? [], $complaint);

        return response()->json(['data' => ['id' => $record->id, 'action' => $record->action->value, 'target_type' => $record->target_type, 'created_at' => $record->created_at->toIso8601String()]], 201);
    }

    public function auditLogs(Request $request): JsonResponse
    {
        $page = AuditLog::query()
            ->when($request->query('action'), fn ($q, $a) => $q->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], (string) $a).'%'))
            ->when($request->query('entity_type'), fn ($q, $t) => $q->where('entity_type', $t))
            ->with('actor:id,username')
            ->orderByDesc('id')->paginate(50);

        return response()->json([
            'data' => collect($page->items())->map(fn (AuditLog $l) => [
                'id' => $l->id, 'action' => $l->action, 'entity_type' => $l->entity_type, 'entity_id' => $l->entity_id,
                'actor' => $l->actor?->username, 'before' => $l->before_json, 'after' => $l->after_json,
                'ip_address' => $l->ip_address, 'request_id' => $l->request_id, 'hash' => $l->hash,
                'created_at' => $l->created_at->toIso8601String(),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function revealIdentity(Request $request, string $investigatorPublicId, IdentityAccessService $identity): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        $investigator = Investigator::query()->where('public_id', $investigatorPublicId)->firstOrFail();

        return response()->json(['data' => $identity->reveal($request->user(), $investigator, $data['reason'])]);
    }

    /** @return array<string, mixed> */
    private function presentComplaint(Complaint $c, bool $detailed = false): array
    {
        $out = [
            'id' => $c->public_id,
            'complaint_type' => $c->complaint_type->value,
            'status' => $c->status->value,
            'description' => $c->description,
            'investigation' => $c->investigation ? ['id' => $c->investigation->public_id, 'title' => $c->investigation->published_title] : null,
            'comment' => $c->comment?->public_id,
            'evidence' => $c->evidence?->public_id,
            'has_reporter_account' => $c->reporter_user_id !== null,
            'resolution' => $c->resolution,
            'created_at' => $c->created_at->toIso8601String(),
            'reviewed_at' => $c->reviewed_at?->toIso8601String(),
        ];
        if ($detailed) {
            $out['reporter_email'] = $c->reporter_email;
            $out['internal_notes'] = $c->internal_notes;
            $out['comment_body'] = $c->comment?->body;
            $out['actions'] = $c->moderationActions->map(fn ($a) => ['action' => $a->action->value, 'target_type' => $a->target_type, 'reason' => $a->reason, 'created_at' => $a->created_at->toIso8601String()]);
        }

        return $out;
    }
}
