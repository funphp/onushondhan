<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum CommentStatus: string implements HasLabel
{
    use EnumValues;

    case Visible = 'visible';
    case Hidden = 'hidden';
    case Locked = 'locked';
    case Removed = 'removed';
    case Pending = 'pending';
}
