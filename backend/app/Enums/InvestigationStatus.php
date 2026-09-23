<?php

declare(strict_types=1);

namespace App\Enums;

enum InvestigationStatus: string
{
    use EnumValues;

    case Draft = 'draft';
    case Published = 'published';
    case Withdrawn = 'withdrawn';
    case Archived = 'archived';
    case Restricted = 'restricted';
    case Removed = 'removed';
}
