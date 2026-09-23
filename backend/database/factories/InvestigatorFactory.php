<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Investigator;
use App\Models\User;
use App\Services\AgreementService;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Investigator>
 */
class InvestigatorFactory extends Factory
{
    protected $model = Investigator::class;

    public function definition(): array
    {
        return [
            'public_name' => fake()->unique()->company().' Watch',
            'bio' => fake()->paragraph(),
            'identity_mode' => 'protected_public_identity',
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (Investigator $inv) {
            if (! $inv->user_id) {
                $inv->user_id = User::factory()->create()->id;
            }
            $inv->slug ??= Investigator::uniqueSlug($inv->public_name);
            $inv->published_at ??= now();
        })->afterCreating(function (Investigator $inv) {
            $user = $inv->user;
            $user->assignRole('investigator');
            $inv->privateIdentity()->create(['legal_or_private_name' => 'Private '.fake()->name(), 'private_email' => fake()->safeEmail()]);
            app(AgreementService::class)->acceptCurrent($user->fresh(), array_merge(config('onushondhan.agreements.user'), config('onushondhan.agreements.investigator')), 'factory');
        });
    }

    public function realName(): static
    {
        return $this->state(['identity_mode' => 'real_name']);
    }
}
