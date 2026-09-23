<?php

declare(strict_types=1);

namespace App\Filament\Resources\Investigations;

use App\Enums\InvestigationStatus;
use App\Enums\ModerationActionType as A;
use App\Filament\Resources\Investigations\Pages\ListInvestigations;
use App\Filament\Resources\Investigations\Pages\ViewInvestigation;
use App\Filament\Support\ModerationActions;
use App\Models\Investigation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class InvestigationResource extends Resource
{
    protected static ?string $model = Investigation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'title';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('investigator'))
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('public_id')->label('ID')->searchable()->copyable(),
                TextColumn::make('title')->label('Working title')->searchable()->limit(60)->wrap(),
                TextColumn::make('investigator.public_name')->label('Investigator (public)')->searchable(),
                TextColumn::make('status')->badge()->color(fn (InvestigationStatus $state) => match ($state) {
                    InvestigationStatus::Published => 'success',
                    InvestigationStatus::Restricted, InvestigationStatus::Removed => 'danger',
                    InvestigationStatus::Withdrawn => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('visibility')->badge(),
                TextColumn::make('current_version')->label('Ver.'),
                TextColumn::make('views_count')->label('Views')->numeric()->sortable(),
                TextColumn::make('comments_count')->label('Comments')->numeric()->sortable()->toggleable(),
                TextColumn::make('published_at')->dateTime()->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->options(InvestigationStatus::options()),
                SelectFilter::make('visibility')->options(['public' => 'Public', 'unlisted' => 'Unlisted', 'private' => 'Private']),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make(self::moderationActions()),
            ]);
    }

    /** @return list<Action> */
    public static function moderationActions(): array
    {
        $published = fn (Investigation $r) => in_array($r->status, [InvestigationStatus::Published, InvestigationStatus::Archived], true);
        $moderated = fn (Investigation $r) => in_array($r->status, [InvestigationStatus::Restricted, InvestigationStatus::Removed], true);

        return [
            ModerationActions::make('investigation', A::RequestCorrection, 'Request correction', 'info'),
            ModerationActions::make('investigation', A::Warn, 'Warn investigator', 'warning'),
            ModerationActions::make('investigation', A::Restrict, 'Restrict (pending review)', 'warning', $published, withPublicNote: true),
            ModerationActions::make('investigation', A::Remove, 'Remove', 'danger', fn (Investigation $r) => $r->status !== InvestigationStatus::Removed, withPublicNote: true),
            ModerationActions::make('investigation', A::Restore, 'Restore', 'success', $moderated),
            ModerationActions::make('investigation', A::LockComments, 'Lock comments', 'gray', fn (Investigation $r) => ! $r->comments_locked),
            ModerationActions::make('investigation', A::UnlockComments, 'Unlock comments', 'gray', fn (Investigation $r) => $r->comments_locked),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Publication')->columns(3)->schema([
                TextEntry::make('public_id')->label('Public ID')->copyable(),
                TextEntry::make('status')->badge(),
                TextEntry::make('visibility')->badge(),
                TextEntry::make('investigator.public_name')->label('Investigator (public identity)'),
                TextEntry::make('investigator.identity_mode')->label('Identity mode')->badge(),
                TextEntry::make('current_version')->label('Published version'),
                TextEntry::make('published_at')->dateTime(),
                TextEntry::make('last_published_at')->dateTime(),
                IconEntry::make('comments_locked')->boolean(),
                TextEntry::make('public_status_note')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Published content')->schema([
                TextEntry::make('published_title')->label('Title')->placeholder('Not published'),
                TextEntry::make('published_summary')->label('Summary')->placeholder('—'),
                TextEntry::make('published_body')->label('Body (Markdown)')->markdown()->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Engagement (not a measure of accuracy)')->columns(6)->schema([
                TextEntry::make('views_count')->label('Views'),
                TextEntry::make('shares_count')->label('Shares'),
                TextEntry::make('comments_count')->label('Comments'),
                TextEntry::make('bookmarks_count')->label('Bookmarks'),
                TextEntry::make('reactions_count')->label('Reactions'),
                TextEntry::make('tips_count')->label('Tips'),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvestigations::route('/'),
            'view' => ViewInvestigation::route('/{record}'),
        ];
    }
}
