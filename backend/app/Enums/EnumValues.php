<?php

declare(strict_types=1);

namespace App\Enums;

trait EnumValues
{
    /** @return list<string> */
    public static function values(): array
    {
        return array_map(static fn (self $c): string => $c->value, self::cases());
    }

    /** @return array<string, string> value => human label */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = ucfirst(str_replace('_', ' ', $case->value));
        }

        return $out;
    }
}
