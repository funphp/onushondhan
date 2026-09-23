<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/** Reference data without demo accounts (used by the test suite). */
class BaselineSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([RolesAndPermissionsSeeder::class, CategorySeeder::class, SettingsSeeder::class, ContentSeeder::class]);
    }
}
