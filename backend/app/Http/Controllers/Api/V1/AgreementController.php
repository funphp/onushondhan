<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\MeResource;
use App\Models\TermsVersion;
use App\Services\AgreementService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgreementController extends Controller
{
    public function __construct(private readonly AgreementService $agreements) {}

    /** Current published versions of every agreement type (public). */
    public function current(): JsonResponse
    {
        $data = [];
        foreach ($this->agreements->currentVersions() as $type => $version) {
            if ($version === null) {
                continue;
            }
            $data[] = $this->present($version, true);
        }

        return response()->json(['data' => $data]);
    }

    public function show(string $type): JsonResponse
    {
        $version = TermsVersion::currentFor($type);
        abort_if($version === null, 404);

        return response()->json(['data' => $this->present($version, true)]);
    }

    public function accept(Request $request): MeResource
    {
        $data = $request->validate([
            'terms_version_ids' => ['required', 'array', 'min:1', 'max:10'],
            'terms_version_ids.*' => ['integer', 'exists:terms_versions,id'],
        ]);
        $this->agreements->accept($request->user(), array_map('intval', $data['terms_version_ids']), 'explicit');

        return new MeResource($request->user()->fresh());
    }

    /** @return array<string, mixed> */
    private function present(TermsVersion $version, bool $withContent): array
    {
        return [
            'id' => $version->id,
            'agreement_type' => $version->agreement_type->value,
            'version' => $version->version,
            'title' => $version->title,
            'content' => $withContent ? $version->content : null,
            'effective_at' => $version->effective_at?->toIso8601String(),
            'published_at' => $version->published_at?->toIso8601String(),
        ];
    }
}
