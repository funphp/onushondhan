<?php

declare(strict_types=1);

namespace App\Filament\Resources\Complaints\Pages;

use App\Filament\Resources\Complaints\ComplaintResource;
use Filament\Resources\Pages\ListRecords;

class ListComplaints extends ListRecords
{
    protected static string $resource = ComplaintResource::class;
}
