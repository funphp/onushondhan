<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum TipStatus: string implements HasLabel
{
    use EnumValues;

    case New = 'new';
    case Read = 'read';
    case Important = 'important';
    case Archived = 'archived';
    case Closed = 'closed';
}
