<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum ComplaintStatus: string implements HasLabel
{
    use EnumValues;

    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case ActionRequested = 'action_requested';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
    case Escalated = 'escalated';
}
