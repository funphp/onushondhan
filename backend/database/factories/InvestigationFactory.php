<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Investigation;
use App\Models\InvestigationCategory;
use App\Models\Investigator;
use App\Services\InvestigationService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investigation>
 */
class InvestigationFactory extends Factory
{
    protected $model = Investigation::class;

    public function definition(): array
    {
        return [
            'title' => rtrim(fake()->sentence(8), '.'),
            'summary' => fake()->paragraph(3),
            'body' => implode("\n\n", fake()->paragraphs(6)),
            'recommendations' => [fake()->sentence(), fake()->sentence()],
            'location_name' => fake()->city(),
            'location_country' => 'BD',
            'visibility' => 'public',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Investigation $inv) {
            if (! $inv->investigator_id) {
                $inv->investigator_id = Investigator::factory()->create()->id;
            }
            if (! $inv->category_id) {
                $inv->category_id = InvestigationCategory::query()->whereNull('parent_id')->value('id')
                    ?? InvestigationCategory::factory()->create()->id;
            }
        });
    }

    /** Publish through the real service so a version snapshot and confirmation exist. */
    public function published(): static
    {
        return $this->afterCreating(function (Investigation $inv) {
            $inv->findings()->create(['title' => 'Key finding', 'body' => fake()->paragraph(), 'sort_order' => 0]);
            $inv->sources()->create(['title' => 'Public record', 'url' => 'https://example.org/record', 'is_public' => true, 'sort_order' => 0]);
            $inv->sources()->create(['title' => 'Confidential source notes', 'is_public' => false, 'sort_order' => 1]);
            app(InvestigationService::class)->publish(
                $inv->fresh(),
                $inv->investigator->user,
                array_fill_keys(array_keys(InvestigationService::CONFIRMATION_STATEMENTS), true),
                null,
            );
        });
    }
}
