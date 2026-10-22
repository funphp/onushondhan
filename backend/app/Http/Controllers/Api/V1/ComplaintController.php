<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ComplaintType;
use App\Http\Controllers\Controller;
use App\Models\Complaint;
use App\Services\ComplaintService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class ComplaintController extends Controller
{
    public function store(Request $request, ComplaintService $service): JsonResponse
    {
        $user = Auth::guard('web')->user();
        $data = $request->validate([
            'complaint_type' => ['required', Rule::enum(ComplaintType::class)],
            'description' => ['required', 'string', 'min:20', 'max:10000'],
            'investigation' => ['nullable', 'string', 'max:24'],
            'comment' => ['nullable', 'string', 'size:26'],
            'evidence' => ['nullable', 'string', 'size:26'],
            'reporter_email' => [$user === null ? 'required' : 'nullable', 'email', 'max:190'],
            'good_faith' => ['required', 'accepted'],
        ]);
        $complaint = $service->submit($data, $user?->isActive() ? $user : null);

        $note = $complaint->complaint_type === ComplaintType::FactualDispute
            ? __('Onushondhan is a publishing platform and does not independently verify or adjudicate the facts in reports. We will record your dispute, may ask the investigator to review it, and will act where our content policy is breached.')
            : __('Our moderation team will review your complaint against the content policy.');

        return response()->json(['data' => ['id' => $complaint->public_id, 'status' => $complaint->status->value, 'message' => $note]], 201);
    }

    public function mine(Request $request): JsonResponse
    {
        $page = Complaint::query()->where('reporter_user_id', $request->user()->id)->with('investigation:id,public_id,published_title')->latest()->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn (Complaint $c) => [
                'id' => $c->public_id,
                'complaint_type' => $c->complaint_type->value,
                'status' => $c->status->value,
                'resolution' => $c->resolution,
                'investigation' => $c->investigation ? ['id' => $c->investigation->public_id, 'title' => $c->investigation->published_title] : null,
                'created_at' => $c->created_at->toIso8601String(),
                'reviewed_at' => $c->reviewed_at?->toIso8601String(),
            ]),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }
}
