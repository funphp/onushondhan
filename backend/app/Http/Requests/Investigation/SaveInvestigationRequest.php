<?php

declare(strict_types=1);

namespace App\Http\Requests\Investigation;

use App\Enums\InvestigationVisibility;
use App\Enums\SourceType;
use App\Models\InvestigationCategory;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Working-copy save (create + autosave). Everything is optional so partial autosaves
 * work; publish-time completeness is enforced separately by InvestigationService.
 */
class SaveInvestigationRequest extends FormRequest
{
    protected function prepareForValidation(): void
    {
        // Categories are addressed by slug from the client; resolve to ids server-side.
        foreach (['category' => 'category_id', 'subcategory' => 'subcategory_id'] as $in => $out) {
            if ($this->has($in)) {
                $slug = $this->input($in);
                $this->merge([$out => $slug === null || $slug === '' ? null : (InvestigationCategory::query()->where('slug', $slug)->where('status', 'active')->value('id') ?? -1)]);
            }
        }
    }

    public function rules(): array
    {
        $httpUrl = ['nullable', 'string', 'max:2000', 'url:http,https'];

        return [
            'revision' => ['sometimes', 'integer', 'min:1'],
            'title' => ['sometimes', 'string', 'max:250'],
            'body' => ['sometimes', 'nullable', 'string', 'max:200000'],
            'category_id' => ['sometimes', 'nullable', 'integer', Rule::exists('investigation_categories', 'id')->whereNull('parent_id')],
            'subcategory_id' => ['sometimes', 'nullable', 'integer', Rule::exists('investigation_categories', 'id')->where('parent_id', $this->input('category_id'))],
            'visibility' => ['sometimes', Rule::enum(InvestigationVisibility::class)],
            'tags' => ['sometimes', 'array', 'max:15'],
            'tags.*' => ['string', 'max:60'],
            'location_name' => ['sometimes', 'nullable', 'string', 'max:200'],
            'location_country' => ['sometimes', 'nullable', 'string', 'size:2', 'alpha'],
            'location_lat' => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            'location_lng' => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
            'period_start' => ['sometimes', 'nullable', 'date'],
            'period_end' => ['sometimes', 'nullable', 'date', 'after_or_equal:period_start'],
            'recommendations' => ['sometimes', 'array', 'max:50'],
            'recommendations.*' => ['string', 'max:2000'],
            'entities' => ['sometimes', 'array', 'max:100'],
            'entities.*.name' => ['required', 'string', 'max:200'],
            'entities.*.type' => ['required', Rule::in(['person', 'organization', 'government_body', 'company', 'place', 'other'])],
            'entities.*.role' => ['nullable', 'string', 'max:200'],
            'findings' => ['sometimes', 'array', 'max:100'],
            'findings.*.title' => ['required', 'string', 'max:250'],
            'findings.*.body' => ['nullable', 'string', 'max:20000'],
            'timeline' => ['sometimes', 'array', 'max:200'],
            'timeline.*.event_date' => ['nullable', 'date'],
            'timeline.*.title' => ['required', 'string', 'max:250'],
            'timeline.*.description' => ['nullable', 'string', 'max:5000'],
            'sources' => ['sometimes', 'array', 'max:200'],
            'sources.*.title' => ['required', 'string', 'max:250'],
            'sources.*.source_type' => ['nullable', Rule::enum(SourceType::class)],
            'sources.*.publisher' => ['nullable', 'string', 'max:200'],
            'sources.*.url' => $httpUrl,
            'sources.*.citation_text' => ['nullable', 'string', 'max:5000'],
            'sources.*.accessed_at' => ['nullable', 'date'],
            'sources.*.is_public' => ['nullable', 'boolean'],
            'locations' => ['sometimes', 'array', 'max:20'],
            'locations.*.country' => ['nullable', 'string', 'size:2', 'alpha'],
            'locations.*.region' => ['nullable', 'string', 'max:120'],
            'locations.*.district' => ['nullable', 'string', 'max:120'],
            'locations.*.city' => ['nullable', 'string', 'max:120'],
            'locations.*.area' => ['nullable', 'string', 'max:120'],
            'locations.*.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'locations.*.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'locations.*.is_primary' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return ['category_id.exists' => 'Choose a valid category.', 'subcategory_id.exists' => 'Choose a valid subcategory for the selected category.'];
    }
}
