<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

final class RequestId
{
    private static ?string $current = null;

    public static function get(): string
    {
        return self::$current ??= (string) Str::uuid();
    }

    public static function set(string $id): void
    {
        self::$current = $id;
    }

    public static function reset(): void
    {
        self::$current = null;
    }
}
