<?php

declare(strict_types=1);

namespace App\Filament\Resources\SecurityEvents\Pages;

use App\Filament\Resources\SecurityEvents\SecurityEventResource;
use Filament\Resources\Pages\ListRecords;

class ListSecurityEvents extends ListRecords
{
    protected static string $resource = SecurityEventResource::class;
}
