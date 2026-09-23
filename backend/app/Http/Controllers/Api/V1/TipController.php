<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\ContactPreference;
use App\Http\Controllers\Controller;
use App\Http\Resources\TipResource;
use App\Models\Tip;
use App\Services\TipService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

/** Tip submission and the tipster's side of the conversation. */
class TipController extends Controller
{
    public function __construct(private readonly TipService $service) {}

    public function store(Request $request): JsonResponse
    {
        // Guests are welcome; signed-in users are resolved from the session if present.
        $user = Auth::guard('web')->user();
        if ($user !== null && ! $user->isActive()) {
            $user = null;
        }
        $maxKb = (int) config('onushondhan.tips.max_attachment_mb') * 1024;
        $data = $request->validate([
            'investigator' => ['required_without:investigation', 'nullable', 'string', 'max:140'],
            'investigation' => ['required_without:investigator', 'nullable', 'string', 'max:24'],
            'subject' => ['required', 'string', 'min:3', 'max:200'],
            'message' => ['required', 'string', 'min:10', 'max:20000'],
            'anonymous_to_investigator' => ['sometimes', 'boolean'],
            'contact_preference' => ['nullable', Rule::enum(ContactPreference::class)],
            'contact_value' => ['nullable', 'required_if:contact_preference,email,phone', 'string', 'max:190'],
            'attachments' => ['sometimes', 'array', 'max:'.(int) config('onushondhan.tips.max_attachments')],
            'attachments.*' => ['file', 'max:'.$maxKb],
            'agree_to_content_policy' => ['required', 'accepted'],
        ]);
        if (($data['contact_preference'] ?? null) === 'email') {
            $request->validate(['contact_value' => ['email']]);
        }

        $result = $this->service->submit($data, $user, $request->file('attachments', []));

        return response()->json([
            'data' => [
                'id' => $result['tip']->public_id,
                'access_code' => $result['access_code'],
                'message' => $result['access_code'] !== null
                    ? 'Your tip was sent. Save this access code now — it is the only way to read replies, and it cannot be recovered.'
                    : 'Your tip was sent. You can follow the conversation from your account.',
            ],
        ], 201);
    }

    public function guestShow(Request $request): TipResource
    {
        $data = $request->validate(['access_code' => ['required', 'string', 'max:40']]);
        $tip = $this->service->findByAccessCode($data['access_code']);
        $tip->load(['investigation', 'investigator', 'messages']);
        $tip->setRelation('attachments', collect());

        return (new TipResource($tip))->as('guest');
    }

    public function guestReply(Request $request): TipResource
    {
        $data = $request->validate(['access_code' => ['required', 'string', 'max:40'], 'body' => ['required', 'string', 'min:1', 'max:10000']]);
        $tip = $this->service->findByAccessCode($data['access_code']);
        $this->service->replyAsTipster($tip, null, $data['body']);
        $tip->load(['investigation', 'investigator', 'messages']);
        $tip->setRelation('attachments', collect());

        return (new TipResource($tip))->as('guest');
    }

    public function mine(Request $request): JsonResponse
    {
        $page = Tip::query()->where('sender_user_id', $request->user()->id)->with(['investigation', 'investigator'])->latest('last_message_at')->paginate(20);

        return response()->json([
            'data' => collect($page->items())->map(fn (Tip $t) => (new TipResource($t))->as('tipster', false)->toArray($request)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
        ]);
    }

    public function showMine(Request $request, Tip $tip): TipResource
    {
        abort_unless($tip->sender_user_id === $request->user()->id, 404);
        $tip->load(['investigation', 'investigator', 'messages', 'attachments']);

        return (new TipResource($tip))->as('tipster');
    }

    public function replyMine(Request $request, Tip $tip): TipResource
    {
        abort_unless($tip->sender_user_id === $request->user()->id, 404);
        $data = $request->validate(['body' => ['required', 'string', 'min:1', 'max:10000']]);
        $this->service->replyAsTipster($tip, $request->user(), $data['body']);
        $tip->load(['investigation', 'investigator', 'messages', 'attachments']);

        return (new TipResource($tip))->as('tipster');
    }
}
