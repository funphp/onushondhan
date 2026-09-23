<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ContactPreference: string implements HasLabel
{
    use EnumValues;

    case None = 'none';
    case PlatformMessaging = 'platform_messaging';
    case Email = 'email';
    case Phone = 'phone';
}
