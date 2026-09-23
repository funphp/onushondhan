<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Baseline data required in every environment. Demo data (with well-known local
     * passwords) is only seeded outside production — see DemoSeeder.
     */
    public function run(): void
    {
        $this->call([
            RolesAndPermissionsSeeder::class,
            CategorySeeder::class,
            SettingsSeeder::class,
            ContentSeeder::class,
        ]);

        if (! app()->isProduction() && config('onushondhan.seed_demo_data')) {
            $this->call(DemoSeeder::class);
        }
    }
}
