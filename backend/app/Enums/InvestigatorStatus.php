<?php

declare(strict_types=1);

namespace App\Enums;

enum InvestigatorStatus: string
{
    use EnumValues;

    case Active = 'active';
    case Suspended = 'suspended';
    case Removed = 'removed';
}
