<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\InvestigationCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<InvestigationCategory>
 */
class InvestigationCategoryFactory extends Factory
{
    protected $model = InvestigationCategory::class;

    public function definition(): array
    {
        $name = ucfirst(fake()->unique()->words(2, true));

        return ['name' => $name, 'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)), 'status' => 'active', 'sort_order' => 0];
    }
}
