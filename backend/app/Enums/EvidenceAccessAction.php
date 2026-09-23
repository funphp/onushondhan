<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasLabel;

enum EvidenceAccessAction: string implements HasLabel
{
    use EnumValues;

    case Preview = 'preview';
    case Download = 'download';
    case Stream = 'stream';
    case MetadataView = 'metadata_view';
}
