<?php

declare(strict_types=1);

namespace App\Filament\Resources\LoginLogs\Pages;

use App\Filament\Resources\LoginLogs\LoginLogResource;
use Filament\Resources\Pages\ListRecords;

class ListLoginLogs extends ListRecords
{
    protected static string $resource = LoginLogResource::class;
}
