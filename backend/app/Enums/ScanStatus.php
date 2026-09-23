<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ScanStatus: string implements HasLabel
{
    use EnumValues;

    case Pending = 'pending';
    case Clean = 'clean';
    case Infected = 'infected';
    case Failed = 'failed';
    case NotScanned = 'not_scanned';
}
