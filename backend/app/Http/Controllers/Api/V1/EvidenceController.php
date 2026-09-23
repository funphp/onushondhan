<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\EvidenceAccessAction;
use App\Enums\EvidenceVisibility;
use App\Http\Controllers\Controller;
use App\Http\Resources\EvidenceResource;
use App\Models\Evidence;
use App\Models\Investigation;
use App\Models\User;
use App\Services\EvidenceDelivery;
use App\Services\EvidenceService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class EvidenceController extends Controller
{
    public function __construct(private readonly EvidenceService $service, private readonly EvidenceDelivery $delivery) {}

    // --- Owner management ----------------------------------------------------

    public function index(Request $request, Investigation $investigation): JsonResponse
    {
        $this->assertOwnsInvestigation($request->user(), $investigation);

        return response()->json(['data' => $investigation->evidence()->get()->map(fn ($e) => (new EvidenceResource($e))->owner()->toArray($request))]);
    }

    public function store(Request $request, Investigation $investigation): JsonResponse
    {
        $this->assertOwnsInvestigation($request->user(), $investigation);
        $this->authorize('update', $investigation);
        $maxKb = (int) config('onushondhan.evidence.max_upload_mb') * 1024;
        $data = $request->validate([
            'file' => ['required', 'file', 'max:'.$maxKb],
            'caption' => ['nullable', 'string', 'max:1000'],
            'visibility' => ['nullable', Rule::enum(EvidenceVisibility::class)],
        ]);
        $evidence = $this->service->upload($investigation, $request->file('file'), $data, $request->user());

        return response()->json(['data' => (new EvidenceResource($evidence))->owner()->toArray($request)], 201);
    }

    public function update(Request $request, Evidence $evidence): JsonResponse
    {
        $this->assertOwnsEvidence($request->user(), $evidence);
        $this->authorize('update', $evidence);
        $data = $request->validate([
            'caption' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'visibility' => ['sometimes', Rule::enum(EvidenceVisibility::class)],
            'sort_order' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ]);

        return response()->json(['data' => (new EvidenceResource($this->service->update($evidence, $data, $request->user())))->owner()->toArray($request)]);
    }

    public function replace(Request $request, Evidence $evidence): JsonResponse
    {
        $this->assertOwnsEvidence($request->user(), $evidence);
        $this->authorize('update', $evidence);
        $request->validate(['file' => ['required', 'file', 'max:'.((int) config('onushondhan.evidence.max_upload_mb') * 1024)]]);

        return response()->json(['data' => (new EvidenceResource($this->service->replaceFile($evidence, $request->file('file'), $request->user())))->owner()->toArray($request)]);
    }

    public function versions(Request $request, Evidence $evidence): JsonResponse
    {
        $this->assertOwnsEvidence($request->user(), $evidence);

        return response()->json(['data' => $evidence->versions()->get()->map(fn ($v) => [
            'version' => $v->version_no, 'original_name' => $v->original_name, 'size_bytes' => $v->size_bytes,
            'sha256' => $v->sha256, 'mime_type' => $v->mime_type, 'scan_status' => $v->scan_status,
            'created_at' => $v->created_at?->toIso8601String(),
        ])]);
    }

    public function accessLog(Request $request, Evidence $evidence): JsonResponse
    {
        $this->assertOwnsEvidence($request->user(), $evidence);
        $page = $evidence->accessLogs()->latest('id')->paginate(50);

        // Owners see when/how their evidence was accessed, but not by whom (reader privacy).
        return response()->json([
            'data' => collect($page->items())->map(fn ($l) => ['action' => $l->action, 'success' => $l->success, 'authenticated' => $l->user_id !== null, 'created_at' => $l->created_at?->toIso8601String()]),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function destroy(Request $request, Evidence $evidence): JsonResponse
    {
        $this->assertOwnsEvidence($request->user(), $evidence);
        $this->authorize('delete', $evidence);
        $this->service->delete($evidence, $request->user());

        return response()->json(['message' => 'Evidence deleted.']);
    }

    // --- Access (public + authorised) ----------------------------------------

    public function show(Request $request, Evidence $evidence): JsonResponse
    {
        $user = $request->user();
        if (! $this->delivery->authorize($user, $evidence, EvidenceAccessAction::MetadataView)) {
            throw new ModelNotFoundException;
        }
        $resource = new EvidenceResource($evidence);
        if ($user !== null && app(\App\Policies\EvidencePolicy::class)->isOwnerOrStaff($user, $evidence)) {
            $resource->owner();
        }

        return response()->json(['data' => $resource->toArray($request)]);
    }

    public function downloadLink(Request $request, Evidence $evidence): JsonResponse
    {
        $user = $request->user();
        if (! $this->delivery->authorize($user, $evidence, EvidenceAccessAction::MetadataView)) {
            throw new ModelNotFoundException;
        }

        return response()->json(['data' => [
            'download_url' => $this->delivery->signedLink($evidence, $user),
            'preview_url' => $this->delivery->signedLink($evidence, $user, 'preview'),
            'expires_in' => (int) config('onushondhan.evidence.download_link_ttl_minutes', 10) * 60,
        ]]);
    }

    public function download(Request $request, Evidence $evidence): Response
    {
        $user = $this->resolveUser($request);
        if (! $this->delivery->authorize($user, $evidence, EvidenceAccessAction::Download)) {
            throw new ModelNotFoundException;
        }

        return $this->delivery->download($evidence, $user);
    }

    public function preview(Request $request, Evidence $evidence): Response
    {
        $user = $this->resolveUser($request);
        if (! $this->delivery->authorize($user, $evidence, EvidenceAccessAction::Preview)) {
            throw new ModelNotFoundException;
        }

        return $this->delivery->preview($evidence, $user);
    }

    /**
     * A valid signed link carries the user it was issued to; permissions are re-checked
     * for that user now, so revoking access also invalidates outstanding links.
     */
    private function resolveUser(Request $request): ?User
    {
        if ($request->has('signature')) {
            abort_unless($request->hasValidSignature(false), 403, 'This link is invalid or has expired.');
            $uid = (string) $request->query('uid');

            return $uid === 'guest' ? null : User::query()->where('public_id', $uid)->first();
        }

        return $request->user();
    }

    private function assertOwnsInvestigation(User $user, Investigation $investigation): void
    {
        if ($user->investigator === null || $investigation->investigator_id !== $user->investigator->id) {
            throw new ModelNotFoundException;
        }
    }

    private function assertOwnsEvidence(User $user, Evidence $evidence): void
    {
        if ($user->investigator === null || $evidence->investigator_id !== $user->investigator->id) {
            throw new ModelNotFoundException;
        }
    }
}
