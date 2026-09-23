<?php

declare(strict_types=1);

namespace App\Filament\Resources\Followers\Pages;

use App\Filament\Resources\Followers\FollowerResource;
use Filament\Resources\Pages\ListRecords;

class ListFollowers extends ListRecords
{
    protected static string $resource = FollowerResource::class;
}
