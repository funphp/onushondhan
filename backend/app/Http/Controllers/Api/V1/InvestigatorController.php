<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\FileVault;
use App\Http\Controllers\Controller;
use App\Http\Resources\InvestigatorProfileResource;
use App\Http\Resources\InvestigatorSummaryResource;
use App\Models\Investigator;
use App\Models\InvestigatorFollower;
use App\Models\InvestigatorSupport;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Public investigator directory and profiles. */
class InvestigatorController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $data = $request->validate([
            'q' => ['nullable', 'string', 'max:120'],
            'sort' => ['nullable', Rule::in(['supporters', 'followers', 'published', 'recent', 'views'])],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);
        $query = Investigator::query()->where('status', 'active')->where('published_investigations_count', '>', 0);
        if (! empty($data['q'])) {
            $query->where('public_name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $data['q']).'%');
        }
        match ($data['sort'] ?? 'published') {
            'supporters' => $query->orderByDesc('supporters_count'),
            'followers' => $query->orderByDesc('followers_count'),
            'recent' => $query->orderByDesc('published_at'),
            'views' => $query->orderByDesc('total_views'),
            default => $query->orderByDesc('published_investigations_count'),
        };

        return InvestigatorSummaryResource::collection($query->orderBy('id')->paginate((int) ($data['per_page'] ?? 20)));
    }

    public function show(Request $request, string $slug): InvestigatorProfileResource
    {
        $investigator = Investigator::query()->where('slug', $slug)->where('status', 'active')->firstOrFail();
        $resource = new InvestigatorProfileResource($investigator);
        if ($user = $request->user()) {
            $resource->viewer = [
                'following' => InvestigatorFollower::query()->where('user_id', $user->id)->where('investigator_id', $investigator->id)->exists(),
                'supporting' => InvestigatorSupport::query()->where('user_id', $user->id)->where('investigator_id', $investigator->id)->where('status', 'active')->exists(),
                'is_self' => $investigator->user_id === $user->id,
            ];
        }

        return $resource;
    }

    public function avatar(string $slug, FileVault $vault): StreamedResponse
    {
        $investigator = Investigator::query()->where('slug', $slug)->where('status', 'active')->firstOrFail();
        abort_if($investigator->avatar_path === null || ! $vault->exists($investigator->avatar_path), 404);
        $path = $investigator->avatar_path;

        return response()->stream(function () use ($vault, $path) {
            $stream = $vault->readStream($path);
            fpassthru($stream);
            fclose($stream);
        }, 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=86400',
            'Content-Disposition' => 'inline; filename="avatar.jpg"',
        ]);
    }
}
