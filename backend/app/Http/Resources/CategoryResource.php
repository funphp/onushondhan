<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\InvestigationCategory;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin InvestigationCategory */
class CategoryResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'name' => $this->name,
            'slug' => $this->slug,
            'description' => $this->description,
            'parent' => $this->parent_id !== null ? $this->whenLoaded('parent', fn () => $this->parent?->slug) : null,
            'children' => CategoryResource::collection($this->whenLoaded('children')),
            'investigations_count' => $this->when(isset($this->investigations_count), fn () => (int) $this->investigations_count),
        ];
    }
}
