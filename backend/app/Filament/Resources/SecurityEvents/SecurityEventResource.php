<?php

declare(strict_types=1);

namespace App\Filament\Resources\SecurityEvents;

use App\Filament\Resources\SecurityEvents\Pages\ListSecurityEvents;
use App\Models\SecurityEvent;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SecurityEventResource extends Resource
{
    protected static ?string $model = SecurityEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFingerPrint;

    protected static string|UnitEnum|null $navigationGroup = 'Audit';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('user:id,username'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('user.username')->label('User')->placeholder('—'),
                TextColumn::make('event_type')->badge()->searchable(), TextColumn::make('severity')->badge()->color(fn (string $state) => match ($state) {
                    'critical' => 'danger', 'warning' => 'warning', default => 'gray'
                }), TextColumn::make('ip_address'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSecurityEvents::route('/')];
    }
}
