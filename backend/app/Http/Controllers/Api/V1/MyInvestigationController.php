<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Investigation\SaveInvestigationRequest;
use App\Http\Resources\InvestigationOwnerResource;
use App\Http\Resources\PublicInvestigationResource;
use App\Models\Investigation;
use App\Models\InvestigationVersion;
use App\Models\Investigator;
use App\Models\User;
use App\Services\InvestigationService;
use App\Support\TextDiff;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/** Investigator-owned investigations (working copies, lifecycle, versions). */
class MyInvestigationController extends Controller
{
    private const WITH = ['category', 'subcategory', 'tags', 'findings', 'timeline', 'sources', 'locations', 'evidence', 'investigator'];

    public function __construct(private readonly InvestigationService $service) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $investigator = $this->investigatorOf($request->user());
        $query = $investigator->investigations()->latest('updated_at');
        if ($status = $request->string('status')->value()) {
            $query->where('status', $status);
        }
        $page = $query->paginate(min(50, $request->integer('per_page', 20)));

        return InvestigationOwnerResource::collection($page->through(function (Investigation $i) {
            $r = new InvestigationOwnerResource($i);
            $r->detailed = false;

            return $r;
        }));
    }

    public function store(SaveInvestigationRequest $request): JsonResponse
    {
        $this->authorize('create', Investigation::class);
        $user = $request->user();
        $investigation = $this->service->createDraft($this->investigatorOf($user), $request->validated(), $user);

        return (new InvestigationOwnerResource($investigation->load(self::WITH)))->response()->setStatusCode(201);
    }

    public function show(Request $request, Investigation $investigation): InvestigationOwnerResource
    {
        $this->authorizeOwner($request->user(), $investigation, 'view');

        return new InvestigationOwnerResource($investigation->load(self::WITH));
    }

    public function update(SaveInvestigationRequest $request, Investigation $investigation): InvestigationOwnerResource
    {
        $this->authorizeOwner($request->user(), $investigation, 'update');
        $saved = $this->service->saveWorkingCopy($investigation, $request->validated(), $request->user());

        return new InvestigationOwnerResource($saved->load(self::WITH));
    }

    /** Render the unsaved working copy exactly as the public page would, for the owner only. */
    public function preview(Request $request, Investigation $investigation): PublicInvestigationResource
    {
        $this->authorizeOwner($request->user(), $investigation, 'view');
        $investigation->load(self::WITH);

        $draft = new InvestigationVersion([
            'version_no' => $investigation->current_version + 1,
            'title' => $investigation->title,
            'summary' => $investigation->summary,
            'body' => $investigation->body,
            'findings_json' => $investigation->findings->map(fn ($f) => ['title' => $f->title, 'body' => $f->body])->all(),
            'recommendations_json' => $investigation->recommendations ?? [],
            'timeline_json' => $investigation->timeline->map(fn ($t) => ['event_date' => $t->event_date?->toDateString(), 'title' => $t->title, 'description' => $t->description])->all(),
            'sources_json' => $investigation->sources->where('is_public', true)->map(fn ($s) => [
                'title' => $s->title, 'source_type' => $s->source_type, 'publisher' => $s->publisher, 'url' => $s->url,
                'citation_text' => $s->citation_text, 'accessed_at' => $s->accessed_at?->toDateString(),
            ])->values()->all(),
            'entities_json' => $investigation->entities ?? [],
            'meta_json' => [
                'location_name' => $investigation->location_name,
                'location_country' => $investigation->location_country,
                'period_start' => $investigation->period_start?->toDateString(),
                'period_end' => $investigation->period_end?->toDateString(),
            ],
        ]);

        $resource = new PublicInvestigationResource($investigation);
        $resource->previewVersion = $draft;
        // Preview shows the evidence exactly as the public would see it after publishing.
        $resource->previewEvidence = $investigation->evidence
            ->filter(fn ($e) => $e->visibility->value === 'public' && $e->moderation_status === 'visible' && $e->isScanAcceptableForPublic())
            ->values();

        return $resource;
    }

    /** Confirmation statements the client must present before publishing. */
    public function confirmationStatements(): JsonResponse
    {
        return response()->json(['data' => collect(InvestigationService::CONFIRMATION_STATEMENTS)->map(fn ($text, $key) => ['key' => $key, 'text' => __($text)])->values()]);
    }

    public function publish(Request $request, Investigation $investigation): InvestigationOwnerResource
    {
        $this->authorizeOwner($request->user(), $investigation, 'publish');
        $data = $request->validate([
            'confirmations' => ['required', 'array'],
            'confirmations.*' => ['boolean'],
            'change_reason' => ['nullable', 'string', 'max:1000'],
        ]);
        $published = $this->service->publish($investigation, $request->user(), array_map('boolval', $data['confirmations']), $data['change_reason'] ?? null);

        return new InvestigationOwnerResource($published->load(self::WITH));
    }

    public function withdraw(Request $request, Investigation $investigation): InvestigationOwnerResource
    {
        $this->authorizeOwner($request->user(), $investigation, 'withdraw');
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        return new InvestigationOwnerResource($this->service->withdraw($investigation, $request->user(), $data['reason'])->load(self::WITH));
    }

    public function archive(Request $request, Investigation $investigation): InvestigationOwnerResource
    {
        $this->authorizeOwner($request->user(), $investigation, 'archive');

        return new InvestigationOwnerResource($this->service->archive($investigation, $request->user())->load(self::WITH));
    }

    public function destroy(Request $request, Investigation $investigation): JsonResponse
    {
        $this->authorizeOwner($request->user(), $investigation, 'delete');
        $this->service->deleteDraft($investigation, $request->user());

        return response()->json(['message' => __('Draft deleted.')]);
    }

    public function versions(Request $request, Investigation $investigation): JsonResponse
    {
        $this->authorizeOwner($request->user(), $investigation, 'view');

        return response()->json(['data' => $investigation->versions()->get(['version_no', 'title', 'change_reason', 'created_at'])->map(fn ($v) => [
            'version' => $v->version_no, 'title' => $v->title, 'change_reason' => $v->change_reason, 'created_at' => $v->created_at->toIso8601String(),
        ])]);
    }

    public function version(Request $request, Investigation $investigation, int $versionNo): JsonResponse
    {
        $this->authorizeOwner($request->user(), $investigation, 'view');
        $v = $investigation->versions()->where('version_no', $versionNo)->firstOrFail();

        return response()->json(['data' => [
            'version' => $v->version_no, 'title' => $v->title, 'summary' => $v->summary, 'body' => $v->body,
            'findings' => $v->findings_json, 'recommendations' => $v->recommendations_json, 'timeline' => $v->timeline_json,
            'sources' => $v->sources_json, 'entities' => $v->entities_json, 'meta' => $v->meta_json,
            'change_reason' => $v->change_reason, 'created_at' => $v->created_at->toIso8601String(),
        ]]);
    }

    public function diff(Request $request, Investigation $investigation): JsonResponse
    {
        $this->authorizeOwner($request->user(), $investigation, 'view');
        $data = $request->validate(['from' => ['required', 'integer', 'min:1'], 'to' => ['required', 'integer', 'min:1']]);
        $from = $investigation->versions()->where('version_no', $data['from'])->firstOrFail();
        $to = $investigation->versions()->where('version_no', $data['to'])->firstOrFail();

        $fields = [];
        foreach (['title', 'summary', 'body'] as $f) {
            $fields[$f] = ['changed' => $from->{$f} !== $to->{$f}, 'lines' => $from->{$f} !== $to->{$f} ? TextDiff::lines($from->{$f}, $to->{$f}) : []];
        }
        foreach (['findings_json' => 'findings', 'recommendations_json' => 'recommendations', 'timeline_json' => 'timeline', 'sources_json' => 'sources', 'entities_json' => 'entities'] as $col => $name) {
            $fields[$name] = ['changed' => json_encode($from->{$col}) !== json_encode($to->{$col}), 'from' => $from->{$col}, 'to' => $to->{$col}];
        }

        return response()->json(['data' => ['from' => $from->version_no, 'to' => $to->version_no, 'fields' => $fields]]);
    }

    private function investigatorOf(User $user): Investigator
    {
        $investigator = $user->investigator;
        if ($investigator === null) {
            throw new ApiException('Create an investigator profile first.', 'not_investigator', 403);
        }

        return $investigator;
    }

    /**
     * Owner endpoints are only for the owner (staff use the admin panel). A foreign
     * investigation returns 404 so its existence is not disclosed.
     */
    private function authorizeOwner(User $user, Investigation $investigation, string $ability): void
    {
        if ($user->investigator === null || $investigation->investigator_id !== $user->investigator->id) {
            throw new ModelNotFoundException;
        }
        $this->authorize($ability, $investigation);
    }
}
