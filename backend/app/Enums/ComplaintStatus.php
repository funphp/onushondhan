<?php

declare(strict_types=1);

namespace App\Enums;

enum ComplaintStatus: string
{
    use EnumValues;

    case Submitted = 'submitted';
    case UnderReview = 'under_review';
    case ActionRequested = 'action_requested';
    case Resolved = 'resolved';
    case Dismissed = 'dismissed';
    case Escalated = 'escalated';
}
