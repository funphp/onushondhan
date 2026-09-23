<?php

declare(strict_types=1);

namespace App\Filament\Resources\Investigations\Pages;

use App\Filament\Resources\Investigations\InvestigationResource;
use Filament\Resources\Pages\ListRecords;

class ListInvestigations extends ListRecords
{
    protected static string $resource = InvestigationResource::class;
}
