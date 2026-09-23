<?php

declare(strict_types=1);

namespace App\Enums;

enum EvidenceAccessAction: string
{
    use EnumValues;

    case Preview = 'preview';
    case Download = 'download';
    case Stream = 'stream';
    case MetadataView = 'metadata_view';
}
