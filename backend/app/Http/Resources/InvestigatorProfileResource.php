<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Investigator;
use Illuminate\Http\Request;

class InvestigatorProfileResource extends InvestigatorSummaryResource
{
    /** @var array{following: bool, supporting: bool}|null */
    public ?array $viewer = null;

    public function toArray(Request $request): array
    {
        /** @var Investigator $inv */
        $inv = $this->resource;

        return parent::toArray($request) + [
            'bio' => $inv->bio,
            'website_url' => $inv->website_url,
            'total_views' => $inv->total_views,
            'joined_at' => ($inv->published_at ?? $inv->created_at)->toIso8601String(),
            'viewer' => $this->viewer,
            'canonical_url' => config('onushondhan.frontend_url').'/investigators/'.$inv->slug,
        ];
    }
}
