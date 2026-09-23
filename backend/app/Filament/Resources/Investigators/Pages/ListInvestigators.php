<?php

declare(strict_types=1);

namespace App\Filament\Resources\Investigators\Pages;

use App\Filament\Resources\Investigators\InvestigatorResource;
use Filament\Resources\Pages\ListRecords;

class ListInvestigators extends ListRecords
{
    protected static string $resource = InvestigatorResource::class;
}
