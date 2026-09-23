<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\SystemSetting;
use App\Services\Settings;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    public function run(): void
    {
        foreach (Settings::DEFAULTS as $key => $value) {
            SystemSetting::query()->firstOrCreate(['key' => $key], [
                'value_json' => $value,
                'value_type' => is_bool($value) ? 'boolean' : 'integer',
            ]);
        }
    }
}
