<?php

declare(strict_types=1);

namespace App\Filament\Resources\Evidence;

use App\Enums\EvidenceVisibility;
use App\Enums\ModerationActionType as A;
use App\Enums\ScanStatus;
use App\Filament\Resources\Evidence\Pages\ListEvidence;
use App\Filament\Support\ModerationActions;
use App\Models\Evidence;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class EvidenceResource extends Resource
{
    protected static ?string $model = Evidence::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperClip;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 2;

    protected static ?string $pluralModelLabel = 'Evidence';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('investigation:id,public_id'))
            ->defaultSort('uploaded_at', 'desc')
            ->columns([
                TextColumn::make('public_id')->label('ID')->limit(12)->copyable(),
                TextColumn::make('investigation.public_id')->label('Investigation')->searchable(),
                TextColumn::make('kind')->badge(),
                TextColumn::make('mime_type')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('size_bytes')->label('Size')->formatStateUsing(fn (int $state) => number_format($state / 1024, 1).' KB'),
                TextColumn::make('visibility')->badge(),
                TextColumn::make('scan_status')->badge()->color(fn (ScanStatus $state) => match ($state) {
                    ScanStatus::Clean => 'success', ScanStatus::Infected => 'danger', ScanStatus::Failed => 'warning', default => 'gray',
                }),
                TextColumn::make('moderation_status')->badge(),
                TextColumn::make('sha256')->label('SHA-256')->limit(16)->copyable()->toggleable(),
                TextColumn::make('uploaded_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('scan_status')->options(ScanStatus::options()),
                SelectFilter::make('visibility')->options(EvidenceVisibility::options()),
                SelectFilter::make('moderation_status')->options(['visible' => 'Visible', 'hidden' => 'Hidden', 'removed' => 'Removed', 'quarantined' => 'Quarantined']),
            ])
            ->recordActions([
                Action::make('download')->label('Download (logged)')->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn (Evidence $r) => url('/api/v1/evidence/'.$r->public_id.'/download'), shouldOpenInNewTab: true)
                    ->visible(fn (Evidence $r) => $r->visibility !== EvidenceVisibility::Private && Auth::user()?->hasPermission('evidence.view_restricted') && $r->scan_status !== ScanStatus::Infected),
                ActionGroup::make([
                    ModerationActions::make('evidence', A::Hide, 'Hide', 'warning', fn (Evidence $r) => $r->moderation_status === 'visible'),
                    ModerationActions::make('evidence', A::Remove, 'Remove', 'danger', fn (Evidence $r) => $r->moderation_status !== 'removed'),
                    ModerationActions::make('evidence', A::Restore, 'Restore', 'success', fn (Evidence $r) => in_array($r->moderation_status, ['hidden', 'removed'], true)),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListEvidence::route('/')];
    }
}
