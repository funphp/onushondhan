<?php

declare(strict_types=1);

namespace App\Filament\Resources\TermsVersions\Pages;

use App\Filament\Resources\TermsVersions\TermsVersionResource;
use Filament\Resources\Pages\ManageRecords;

class ManageTermsVersions extends ManageRecords
{
    protected static string $resource = TermsVersionResource::class;
}
