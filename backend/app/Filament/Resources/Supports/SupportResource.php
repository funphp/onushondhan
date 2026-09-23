<?php

declare(strict_types=1);

namespace App\Filament\Resources\Supports;

use App\Filament\Resources\Supports\Pages\ListSupports;
use App\Models\InvestigatorSupport;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

/** Read-only view for anti-abuse review. Support is engagement, never an accuracy signal. */
class SupportResource extends Resource
{
    protected static ?string $model = InvestigatorSupport::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHandThumbUp;

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'supporter';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user:id,username,created_at,email_verified_at,phone_verified_at', 'investigator:id,public_name']))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('user.username')->label('Supporter')->searchable(),
                TextColumn::make('investigator.public_name')->label('Investigator')->searchable(),
                TextColumn::make('status')->badge(),
                TextColumn::make('toggle_count')->label('Toggles')->sortable()->badge()->color(fn (int $state) => $state >= 5 ? 'warning' : 'gray'),
                TextColumn::make('user.created_at')->label('Account created')->since(),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('revoked_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(['active' => 'Active', 'revoked' => 'Revoked']),
                Filter::make('frequent_toggling')->query(fn ($query) => $query->where('toggle_count', '>=', 5)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListSupports::route('/')];
    }
}
