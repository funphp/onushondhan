<?php

declare(strict_types=1);

namespace App\Enums;

enum ReactionType: string
{
    use EnumValues;

    case Useful = 'useful';
    case Important = 'important';
    case Interesting = 'interesting';
}
