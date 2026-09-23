<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipSenderType: string implements HasLabel
{
    use EnumValues;

    case Tipster = 'tipster';
    case Investigator = 'investigator';
}
