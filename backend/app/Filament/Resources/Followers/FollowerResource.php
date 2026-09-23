<?php

declare(strict_types=1);

namespace App\Filament\Resources\Followers;

use App\Filament\Resources\Followers\Pages\ListFollowers;
use App\Models\InvestigatorFollower;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class FollowerResource extends Resource
{
    protected static ?string $model = InvestigatorFollower::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserPlus;

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'follower';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['user:id,username', 'investigator:id,public_name']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('user.username')->label('Follower')->searchable(),
                TextColumn::make('investigator.public_name')->label('Investigator')->searchable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListFollowers::route('/')];
    }
}
