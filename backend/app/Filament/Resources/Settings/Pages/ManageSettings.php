<?php

declare(strict_types=1);

namespace App\Filament\Resources\Settings\Pages;

use App\Filament\Resources\Settings\SettingResource;
use Filament\Resources\Pages\ManageRecords;

class ManageSettings extends ManageRecords
{
    protected static string $resource = SettingResource::class;
}
