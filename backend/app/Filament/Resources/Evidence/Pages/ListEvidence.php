<?php

declare(strict_types=1);

namespace App\Filament\Resources\Evidence\Pages;

use App\Filament\Resources\Evidence\EvidenceResource;
use Filament\Resources\Pages\ListRecords;

class ListEvidence extends ListRecords
{
    protected static string $resource = EvidenceResource::class;
}
