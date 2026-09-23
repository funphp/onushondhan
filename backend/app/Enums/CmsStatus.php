<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CmsStatus: string implements HasLabel
{
    use EnumValues;

    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
