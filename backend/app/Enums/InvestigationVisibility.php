<?php

declare(strict_types=1);

namespace App\Enums;

enum InvestigationVisibility: string
{
    use EnumValues;

    case Public = 'public';
    case Unlisted = 'unlisted';
    case Private = 'private';
}
