<?php

declare(strict_types=1);

namespace App\Enums;

enum ContactPreference: string
{
    use EnumValues;

    case None = 'none';
    case PlatformMessaging = 'platform_messaging';
    case Email = 'email';
    case Phone = 'phone';
}
