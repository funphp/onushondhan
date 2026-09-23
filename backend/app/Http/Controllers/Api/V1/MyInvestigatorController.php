<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\IdentityMode;
use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvestigatorProfileResource;
use App\Models\Investigation;
use App\Models\Investigator;
use App\Models\User;
use App\Services\InvestigatorAnalyticsService;
use App\Services\InvestigatorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** The authenticated user's own investigator profile. */
class MyInvestigatorController extends Controller
{
    public function __construct(private readonly InvestigatorService $service) {}

    public function onboard(Request $request): JsonResponse
    {
        $data = $request->validate([
            'public_name' => ['required', 'string', 'min:2', 'max:120'],
            'bio' => ['nullable', 'string', 'max:5000'],
            'website_url' => ['nullable', 'string', 'max:500', 'url:http,https'],
            'identity_mode' => ['required', Rule::enum(IdentityMode::class)],
            'accept_investigator_agreement' => ['required', 'accepted'],
            'private_identity' => ['nullable', 'array'],
            'private_identity.legal_or_private_name' => ['nullable', 'string', 'max:200'],
            'private_identity.private_email' => ['nullable', 'email', 'max:190'],
            'private_identity.private_phone' => ['nullable', 'string', 'max:32'],
            'private_identity.notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $investigator = $this->service->onboard($request->user(), $data);

        return (new InvestigatorProfileResource($investigator))->response()->setStatusCode(201);
    }

    public function show(Request $request): InvestigatorProfileResource
    {
        return new InvestigatorProfileResource($this->mine($request->user()));
    }

    public function update(Request $request): InvestigatorProfileResource
    {
        $investigator = $this->mine($request->user());
        $this->authorize('update', $investigator);
        $data = $request->validate([
            'public_name' => ['sometimes', 'string', 'min:2', 'max:120'],
            'bio' => ['sometimes', 'nullable', 'string', 'max:5000'],
            'website_url' => ['sometimes', 'nullable', 'string', 'max:500', 'url:http,https'],
            'identity_mode' => ['sometimes', Rule::enum(IdentityMode::class)],
        ]);

        return new InvestigatorProfileResource($this->service->updateProfile($investigator, $data, $request->user()));
    }

    public function avatar(Request $request): InvestigatorProfileResource
    {
        $investigator = $this->mine($request->user());
        $this->authorize('update', $investigator);
        $request->validate(['avatar' => ['required', 'file', 'max:5120']]);
        $this->service->updateAvatar($investigator, $request->file('avatar'), $request->user());

        return new InvestigatorProfileResource($investigator->fresh());
    }

    public function privateIdentity(Request $request): JsonResponse
    {
        $investigator = $this->mine($request->user());
        $this->authorize('viewPrivateIdentity', $investigator);
        $identity = $investigator->privateIdentity;

        return response()->json(['data' => [
            'legal_or_private_name' => $identity?->legal_or_private_name,
            'private_email' => $identity?->private_email,
            'private_phone' => $identity?->private_phone,
            'notes' => $identity?->notes,
            'notice' => 'This information is visible only to you and to specifically authorised platform staff. Every staff access is logged. Onushondhan cannot promise absolute anonymity (for example, in response to lawful orders).',
        ]]);
    }

    public function updatePrivateIdentity(Request $request): JsonResponse
    {
        $investigator = $this->mine($request->user());
        $this->authorize('managePrivateIdentity', $investigator);
        $data = $request->validate([
            'legal_or_private_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'private_email' => ['sometimes', 'nullable', 'email', 'max:190'],
            'private_phone' => ['sometimes', 'nullable', 'string', 'max:32'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:2000'],
        ]);
        $this->service->updatePrivateIdentity($investigator, $data, $request->user());

        return $this->privateIdentity($request);
    }

    public function analytics(Request $request, InvestigatorAnalyticsService $analytics): JsonResponse
    {
        $investigator = $this->mine($request->user());
        $this->authorize('viewAnalytics', $investigator);
        $investigation = null;
        if ($id = $request->query('investigation')) {
            $investigation = Investigation::query()->where('public_id', $id)->where('investigator_id', $investigator->id)->firstOrFail();
        }

        return response()->json(['data' => $analytics->summary($investigator, $request->integer('days', 30), $investigation)]);
    }

    public function followers(Request $request): JsonResponse
    {
        $investigator = $this->mine($request->user());
        $page = $investigator->followers()->orderByPivot('created_at', 'desc')->paginate(30, ['users.id', 'users.username']);

        return response()->json([
            'data' => collect($page->items())->map(fn ($u) => ['username' => $u->username, 'since' => $u->pivot->created_at]),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    public function supporters(Request $request): JsonResponse
    {
        $investigator = $this->mine($request->user());
        $page = $investigator->supports()->where('status', 'active')->with('user:id,username')->latest('updated_at')->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn ($s) => ['username' => $s->user?->username, 'since' => $s->updated_at?->toIso8601String()]),
            'meta' => ['total' => $page->total(), 'current_page' => $page->currentPage(), 'last_page' => $page->lastPage()],
        ]);
    }

    private function mine(User $user): Investigator
    {
        return $user->investigator ?? throw new ApiException('You do not have an investigator profile yet.', 'not_investigator', 404);
    }
}
