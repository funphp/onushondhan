<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum IdentityMode: string implements HasLabel
{
    use EnumValues;

    case RealName = 'real_name';
    case ProtectedPublicIdentity = 'protected_public_identity';
}
