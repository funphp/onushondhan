<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tips;

use App\Enums\TipStatus;
use App\Filament\Resources\Tips\Pages\ListTips;
use App\Models\Tip;
use App\Services\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use UnitEnum;

/**
 * Need-to-know oversight of tips (tips.view_any, super admin only by default).
 * The list shows metadata only; opening a tip's content requires a justification
 * and is audited. Tipster identity is never shown here.
 */
class TipResource extends Resource
{
    protected static ?string $model = Tip::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'tip';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('investigator:id,public_name')->withCount('attachments'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('public_id')->label('ID')->limit(10),
                TextColumn::make('investigator.public_name')->label('Recipient'),
                TextColumn::make('status')->badge(),
                IconColumn::make('anonymous_to_investigator')->label('Anon. to investigator')->boolean(),
                TextColumn::make('sender_user_id')->label('Sender type')->formatStateUsing(fn ($state) => $state === null ? 'guest' : 'member')->placeholder('guest'),
                TextColumn::make('attachments_count')->label('Files'),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('last_message_at')->dateTime()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(TipStatus::options())])
            ->recordActions([
                Action::make('open')->label('Open content (audited)')->icon(Heroicon::OutlinedEye)->color('warning')
                    ->schema([Textarea::make('reason')->label('Justification')->required()->minLength(10)->maxLength(1000)])
                    ->action(function (Tip $record, array $data): void {
                        app(AuditLogger::class)->log('tip.content_viewed_by_staff', $record, null, ['reason' => $data['reason']]);
                        Notification::make()->title('Tip: '.Str::limit($record->subject, 80))
                            ->body(Str::limit($record->message, 2000))->persistent()->send();
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListTips::route('/')];
    }
}
