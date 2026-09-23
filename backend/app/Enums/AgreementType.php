<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum AgreementType: string implements HasLabel
{
    use EnumValues;

    case TermsOfUse = 'terms_of_use';
    case PrivacyPolicy = 'privacy_policy';
    case ContentPolicy = 'content_policy';
    case InvestigatorResponsibility = 'investigator_responsibility';
}
