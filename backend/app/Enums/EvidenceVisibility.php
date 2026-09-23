<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EvidenceVisibility: string implements HasLabel
{
    use EnumValues;

    case Public = 'public';
    case Restricted = 'restricted';
    case Private = 'private';
}
