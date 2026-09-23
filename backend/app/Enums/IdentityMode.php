<?php

declare(strict_types=1);

namespace App\Enums;

enum IdentityMode: string
{
    use EnumValues;

    case RealName = 'real_name';
    case ProtectedPublicIdentity = 'protected_public_identity';
}
