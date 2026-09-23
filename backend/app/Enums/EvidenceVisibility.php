<?php

declare(strict_types=1);

namespace App\Enums;

enum EvidenceVisibility: string
{
    use EnumValues;

    case Public = 'public';
    case Restricted = 'restricted';
    case Private = 'private';
}
