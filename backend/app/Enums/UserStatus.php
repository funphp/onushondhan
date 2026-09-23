<?php

declare(strict_types=1);

namespace App\Enums;

enum UserStatus: string
{
    use EnumValues;

    case Active = 'active';
    case Suspended = 'suspended';
    case Blocked = 'blocked';
    case Deactivated = 'deactivated';
}
