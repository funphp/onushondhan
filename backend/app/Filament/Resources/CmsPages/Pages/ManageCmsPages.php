<?php

declare(strict_types=1);

namespace App\Filament\Resources\CmsPages\Pages;

use App\Filament\Resources\CmsPages\CmsPageResource;
use Filament\Resources\Pages\ManageRecords;

class ManageCmsPages extends ManageRecords
{
    protected static string $resource = CmsPageResource::class;
}
