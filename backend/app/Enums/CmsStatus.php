<?php

declare(strict_types=1);

namespace App\Enums;

enum CmsStatus: string
{
    use EnumValues;

    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';
}
