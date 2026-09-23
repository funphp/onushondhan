<?php

declare(strict_types=1);

namespace App\Enums;

enum TipSenderType: string
{
    use EnumValues;

    case Tipster = 'tipster';
    case Investigator = 'investigator';
}
