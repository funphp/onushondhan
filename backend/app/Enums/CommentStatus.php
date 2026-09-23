<?php

declare(strict_types=1);

namespace App\Enums;

enum CommentStatus: string
{
    use EnumValues;

    case Visible = 'visible';
    case Hidden = 'hidden';
    case Locked = 'locked';
    case Removed = 'removed';
    case Pending = 'pending';
}
