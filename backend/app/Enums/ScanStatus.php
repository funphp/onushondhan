<?php

declare(strict_types=1);

namespace App\Enums;

enum ScanStatus: string
{
    use EnumValues;

    case Pending = 'pending';
    case Clean = 'clean';
    case Infected = 'infected';
    case Failed = 'failed';
    case NotScanned = 'not_scanned';
}
