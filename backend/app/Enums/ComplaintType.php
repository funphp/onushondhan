<?php

declare(strict_types=1);

namespace App\Enums;

enum ComplaintType: string
{
    use EnumValues;

    case FactualDispute = 'factual_dispute';
    case Privacy = 'privacy';
    case Copyright = 'copyright';
    case PersonalInformation = 'personal_information';
    case ProhibitedContent = 'prohibited_content';
    case Harassment = 'harassment';
    case Spam = 'spam';
    case OtherTermsViolation = 'other_terms_violation';
}
