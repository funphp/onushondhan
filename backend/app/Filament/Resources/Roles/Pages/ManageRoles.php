<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles\Pages;

use App\Filament\Resources\Roles\RoleResource;
use Filament\Resources\Pages\ManageRecords;

class ManageRoles extends ManageRecords
{
    protected static string $resource = RoleResource::class;
}
