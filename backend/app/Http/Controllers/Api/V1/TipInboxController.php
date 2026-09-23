<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\FileVault;
use App\Enums\ScanStatus;
use App\Enums\TipStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\TipResource;
use App\Models\Tip;
use App\Models\TipAttachment;
use App\Policies\TipPolicy;
use App\Services\AuditLogger;
use App\Services\TipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** The investigator's tip inbox. Every lookup is scoped to the caller's own tips. */
class TipInboxController extends Controller
{
    public function __construct(private readonly TipService $service, private readonly TipPolicy $policy) {}

    public function index(Request $request): JsonResponse
    {
        $investigator = $request->user()->investigator;
        abort_if($investigator === null, 404);
        $status = $request->query('status');
        $query = Tip::query()->where('investigator_id', $investigator->id)->with(['investigation', 'investigator', 'sender:id,username']);
        match ($status) {
            'unread' => $query->where('status', TipStatus::New),
            'important', 'archived', 'closed', 'read', 'new' => $query->where('status', $status),
            default => $query->whereNotIn('status', [TipStatus::Archived, TipStatus::Closed]),
        };
        $page = $query->latest('last_message_at')->paginate(min(50, $request->integer('per_page', 20)));

        return response()->json([
            'data' => collect($page->items())->map(fn (Tip $t) => (new TipResource($t))->as('investigator', false)->toArray($request)),
            'meta' => [
                'current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(),
                'unread' => Tip::query()->where('investigator_id', $investigator->id)->where('status', TipStatus::New)->count(),
            ],
        ]);
    }

    public function show(Request $request, Tip $tip): TipResource
    {
        $this->assertRecipient($request, $tip);
        $this->service->markReadByInvestigator($tip);

        return (new TipResource($tip->fresh(['investigation', 'investigator', 'sender:id,username', 'messages', 'attachments'])))->as('investigator');
    }

    public function update(Request $request, Tip $tip): TipResource
    {
        $this->assertRecipient($request, $tip);
        $data = $request->validate(['status' => ['required', Rule::enum(TipStatus::class)]]);
        $this->service->setStatus($tip, TipStatus::from($data['status']), $request->user());

        return (new TipResource($tip->fresh(['investigation', 'investigator', 'sender:id,username', 'messages', 'attachments'])))->as('investigator');
    }

    public function reply(Request $request, Tip $tip): TipResource
    {
        $this->assertRecipient($request, $tip);
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:10000']]);
        $this->service->replyAsInvestigator($tip, $request->user(), $data['body']);

        return (new TipResource($tip->fresh(['investigation', 'investigator', 'sender:id,username', 'messages', 'attachments'])))->as('investigator');
    }

    public function downloadAttachment(Request $request, TipAttachment $attachment, FileVault $vault, AuditLogger $audit): StreamedResponse
    {
        $tip = $attachment->tip;
        $user = $request->user();
        abort_unless($this->policy->view($user, $tip), 404);
        abort_if($attachment->scan_status === ScanStatus::Infected, 403, 'This file has been quarantined.');
        abort_unless($vault->exists($attachment->storage_path), 404);
        if (! $this->policy->isRecipient($user, $tip) && ! $this->policy->isSender($user, $tip)) {
            $audit->log('tip.attachment_accessed_by_staff', $tip, null, ['attachment' => $attachment->public_id], $user);
        }
        $path = $attachment->storage_path;
        $fallback = preg_replace('/[^A-Za-z0-9._-]/', '_', $attachment->original_name) ?: 'attachment';

        return response()->stream(function () use ($vault, $path) {
            $s = $vault->readStream($path);
            fpassthru($s);
            fclose($s);
        }, 200, [
            'Content-Type' => $attachment->mime_type,
            'Content-Disposition' => HeaderUtils::makeDisposition('attachment', $attachment->original_name, $fallback),
            'Cache-Control' => 'private, no-store',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ]);
    }

    private function assertRecipient(Request $request, Tip $tip): void
    {
        // 404 (not 403) so the existence of other investigators' tips is not revealed.
        abort_unless($this->policy->manage($request->user(), $tip), 404);
    }
}
