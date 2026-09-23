<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\SystemSetting;
use Illuminate\Support\Facades\Cache;

/**
 * Non-secret runtime settings stored in `system_settings`, cached briefly.
 */
class Settings
{
    public const DEFAULTS = [
        'comments.require_verified_email' => true,
        'comments.max_depth' => 3,
        'support.require_verified_email' => true,
        'support.min_account_age_hours' => 0,
        'tips.allow_guest' => true,
        'registration.open' => true,
        'moderation.auto_hide_comment_reports' => 5,
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        $all = Cache::remember('system_settings.all', 60, fn () => SystemSetting::query()->pluck('value_json', 'key')->all());

        return array_key_exists($key, $all) ? $all[$key] : ($default ?? self::DEFAULTS[$key] ?? null);
    }

    public function flush(): void
    {
        Cache::forget('system_settings.all');
    }
}
