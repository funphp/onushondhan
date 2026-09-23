<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ReactionType: string implements HasLabel
{
    use EnumValues;

    case Useful = 'useful';
    case Important = 'important';
    case Interesting = 'interesting';
}
