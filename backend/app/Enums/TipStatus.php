<?php

declare(strict_types=1);

namespace App\Enums;

enum TipStatus: string
{
    use EnumValues;

    case New = 'new';
    case Read = 'read';
    case Important = 'important';
    case Archived = 'archived';
    case Closed = 'closed';
}
