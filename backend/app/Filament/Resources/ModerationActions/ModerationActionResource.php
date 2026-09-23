<?php

declare(strict_types=1);

namespace App\Filament\Resources\ModerationActions;

use App\Enums\ModerationActionType;
use App\Filament\Resources\ModerationActions\Pages\ListModerationActions;
use App\Models\ModerationAction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class ModerationActionResource extends Resource
{
    protected static ?string $model = ModerationAction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'moderation action';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('moderator:id,username'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('moderator.username')->label('Moderator'),
                TextColumn::make('action')->badge(),
                TextColumn::make('target_type')->badge(),
                TextColumn::make('target_id'),
                TextColumn::make('reason')->limit(80)->wrap(),
            ])
            ->filters([
                SelectFilter::make('action')->options(ModerationActionType::options()),
                SelectFilter::make('target_type')->options(['investigation' => 'Investigation', 'comment' => 'Comment', 'evidence' => 'Evidence', 'user' => 'User', 'investigator' => 'Investigator']),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListModerationActions::route('/')];
    }
}
