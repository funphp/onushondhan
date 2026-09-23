<?php

declare(strict_types=1);

namespace App\Enums;

enum AgreementType: string
{
    use EnumValues;

    case TermsOfUse = 'terms_of_use';
    case PrivacyPolicy = 'privacy_policy';
    case ContentPolicy = 'content_policy';
    case InvestigatorResponsibility = 'investigator_responsibility';
}
