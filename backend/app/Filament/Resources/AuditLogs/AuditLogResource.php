<?php

declare(strict_types=1);

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Models\AuditLog;
use App\Services\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\CodeEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static string|UnitEnum|null $navigationGroup = 'Audit';

    protected static ?int $navigationSort = 1;

    protected static ?string $modelLabel = 'audit log entry';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('actor:id,username'))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->sortable(),
                TextColumn::make('created_at')->dateTime('Y-m-d H:i:s'),
                TextColumn::make('actor.username')->label('Actor')->placeholder('system'),
                TextColumn::make('action')->badge()->searchable(),
                TextColumn::make('entity_type')->placeholder('—'),
                TextColumn::make('entity_id')->placeholder('—'),
                TextColumn::make('ip_address')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('request_id')->limit(12)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('hash')->limit(12)->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('entity_type')->options(fn () => AuditLog::query()->distinct()->whereNotNull('entity_type')->pluck('entity_type', 'entity_type')->all()),
                Filter::make('action')->schema([TextInput::make('prefix')->label('Action starts with')])
                    ->query(fn ($query, array $data) => filled($data['prefix'] ?? null) ? $query->where('action', 'like', str_replace(['%', '_'], ['\%', '\_'], $data['prefix']).'%') : $query),
            ])
            ->headerActions([
                Action::make('verify')->label('Verify hash chain')->icon(Heroicon::OutlinedShieldCheck)
                    ->action(function (): void {
                        $broken = app(AuditLogger::class)->verifyChain();
                        $broken === null
                            ? Notification::make()->title('Audit chain intact')->success()->send()
                            : Notification::make()->title("Chain broken at entry #{$broken}")->danger()->persistent()->send();
                    }),
            ])
            ->recordActions([ViewAction::make()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextEntry::make('action'),
            TextEntry::make('created_at')->dateTime('Y-m-d H:i:s.u'),
            TextEntry::make('actor.username')->label('Actor')->placeholder('system'),
            TextEntry::make('entity_type')->placeholder('—'),
            TextEntry::make('entity_id')->placeholder('—'),
            TextEntry::make('ip_address')->placeholder('—'),
            TextEntry::make('request_id')->placeholder('—'),
            TextEntry::make('user_agent')->placeholder('—'),
            CodeEntry::make('before_json')->label('Before')->columnSpanFull(),
            CodeEntry::make('after_json')->label('After')->columnSpanFull(),
            TextEntry::make('prev_hash')->placeholder('— (chain start)'),
            TextEntry::make('hash'),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAuditLogs::route('/')];
    }
}
