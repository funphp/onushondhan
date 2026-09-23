<?php

declare(strict_types=1);

namespace App\Filament\Resources\Investigations\Pages;

use App\Filament\Resources\Investigations\InvestigationResource;
use Filament\Actions\ActionGroup;
use Filament\Resources\Pages\ViewRecord;

class ViewInvestigation extends ViewRecord
{
    protected static string $resource = InvestigationResource::class;

    protected function getHeaderActions(): array
    {
        return [ActionGroup::make(InvestigationResource::moderationActions())->label('Moderate')->button()];
    }
}
