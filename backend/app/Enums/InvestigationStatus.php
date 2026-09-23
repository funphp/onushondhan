<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum InvestigationStatus: string implements HasLabel
{
    use EnumValues;

    case Draft = 'draft';
    case Published = 'published';
    case Withdrawn = 'withdrawn';
    case Archived = 'archived';
    case Restricted = 'restricted';
    case Removed = 'removed';
}
