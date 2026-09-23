<?php

declare(strict_types=1);

namespace App\Enums;

enum TermsStatus: string
{
    use EnumValues;

    case Draft = 'draft';
    case Published = 'published';
    case Retired = 'retired';
}
