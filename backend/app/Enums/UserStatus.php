<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum UserStatus: string implements HasLabel
{
    use EnumValues;

    case Active = 'active';
    case Suspended = 'suspended';
    case Blocked = 'blocked';
    case Deactivated = 'deactivated';
}
