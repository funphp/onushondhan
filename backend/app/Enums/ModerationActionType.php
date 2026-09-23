<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ModerationActionType: string implements HasLabel
{
    use EnumValues;

    case NoAction = 'no_action';
    case RequestCorrection = 'request_correction';
    case Warn = 'warn';
    case Restrict = 'restrict';
    case Hide = 'hide';
    case Remove = 'remove';
    case Restore = 'restore';
    case LockComments = 'lock_comments';
    case UnlockComments = 'unlock_comments';
    case SuspendUser = 'suspend_user';
    case BanUser = 'ban_user';
    case ReinstateUser = 'reinstate_user';
    case Escalate = 'escalate';
}
