<?php

declare(strict_types=1);

namespace Tests;

use App\Models\Investigation;
use App\Models\Investigator;
use App\Models\User;
use App\Services\InvestigationService;
use App\Support\RequestId;
use Database\Seeders\BaselineSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    protected string $seeder = BaselineSeeder::class;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('vault');
        Cache::flush();
        RequestId::reset();
        // Requests come from the SPA origin, so Sanctum treats them as stateful (session + CSRF).
        // Existing assertions use the English source texts; LocalizationTest covers Bangla.
        $this->withHeaders(['Origin' => 'http://localhost:3000', 'Accept' => 'application/json', 'X-Locale' => 'en']);
    }

    protected function member(array $attributes = []): User
    {
        return User::factory()->acceptedAgreements()->create($attributes);
    }

    protected function staff(string $role): User
    {
        return User::factory()->acceptedAgreements()->withRole($role)->create();
    }

    protected function investigator(array $attributes = []): Investigator
    {
        return Investigator::factory()->create($attributes);
    }

    protected function publishedInvestigation(?Investigator $investigator = null, array $attributes = []): Investigation
    {
        $investigator ??= $this->investigator();

        return Investigation::factory()->published()->create(['investigator_id' => $investigator->id] + $attributes)->fresh();
    }

    protected function confirmations(): array
    {
        return array_fill_keys(array_keys(InvestigationService::CONFIRMATION_STATEMENTS), true);
    }

    protected function clearRateLimits(): void
    {
        RateLimiter::clear('');
        Cache::flush();
    }
}
