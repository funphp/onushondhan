<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum SharePlatform: string implements HasLabel
{
    use EnumValues;

    case Facebook = 'facebook';
    case X = 'x';
    case Linkedin = 'linkedin';
    case Whatsapp = 'whatsapp';
    case Telegram = 'telegram';
    case Messenger = 'messenger';
    case Email = 'email';
    case CopyLink = 'copy_link';
    case Other = 'other';
}
