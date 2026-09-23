<?php

declare(strict_types=1);

namespace App\Filament\Resources\LoginLogs;

use App\Filament\Resources\LoginLogs\Pages\ListLoginLogs;
use App\Models\LoginLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class LoginLogResource extends Resource
{
    protected static ?string $model = LoginLog::class;

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
                TextColumn::make('email_attempted')->placeholder('—'), TextColumn::make('success')->badge()->formatStateUsing(fn ($state) => $state ? 'success' : 'failed')->color(fn ($state) => $state ? 'success' : 'danger'), TextColumn::make('failure_reason')->placeholder('—'), TextColumn::make('ip_address'),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListLoginLogs::route('/')];
    }
}
