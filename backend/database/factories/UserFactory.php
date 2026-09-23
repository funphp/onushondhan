<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\User;
use App\Services\AgreementService;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => 'u_'.Str::lower(Str::random(10)),
            'email' => fake()->unique()->safeEmail(),
            'password' => static::$password ??= Hash::make('Password-123'),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (User $user) {
            $user->email_verified_at ??= now();
            $user->remember_token ??= Str::random(10);
        })->afterCreating(function (User $user) {
            if ($user->roles()->doesntExist()) {
                $user->assignRole('user');
            }
        });
    }

    public function unverified(): static
    {
        return $this->afterMaking(fn (User $user) => $user->email_verified_at = null);
    }

    public function withRole(string $role): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole($role));
    }

    /** Accept all currently published agreements (as registration would). */
    public function acceptedAgreements(): static
    {
        return $this->afterCreating(function (User $user) {
            app(AgreementService::class)->acceptCurrent($user->fresh(), array_merge(config('onushondhan.agreements.user'), config('onushondhan.agreements.investigator')), 'factory');
        });
    }
}
