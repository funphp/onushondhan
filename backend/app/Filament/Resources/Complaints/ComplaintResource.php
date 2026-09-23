<?php

declare(strict_types=1);

namespace App\Filament\Resources\Complaints;

use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Filament\Resources\Complaints\Pages\ListComplaints;
use App\Filament\Resources\Complaints\Pages\ViewComplaint;
use App\Models\Complaint;
use App\Models\User;
use App\Services\ComplaintService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class ComplaintResource extends Resource
{
    protected static ?string $model = Complaint::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Moderation';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function getNavigationBadge(): ?string
    {
        $open = Complaint::query()->whereIn('status', [ComplaintStatus::Submitted, ComplaintStatus::Escalated])->count();

        return $open > 0 ? (string) $open : null;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['investigation:id,public_id', 'comment:id,public_id', 'evidence:id,public_id']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('public_id')->label('ID')->limit(10),
                TextColumn::make('complaint_type')->badge(),
                TextColumn::make('status')->badge()->color(fn (ComplaintStatus $state) => match ($state) {
                    ComplaintStatus::Submitted, ComplaintStatus::Escalated => 'warning', ComplaintStatus::Resolved => 'success', ComplaintStatus::Dismissed => 'gray', default => 'info',
                }),
                TextColumn::make('investigation.public_id')->label('Investigation')->placeholder('—'),
                TextColumn::make('description')->limit(60)->wrap(),
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('reviewed_at')->dateTime()->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options(ComplaintStatus::options()),
                SelectFilter::make('complaint_type')->options(ComplaintType::options()),
            ])
            ->recordActions([ViewAction::make(), self::updateStatusAction()]);
    }

    public static function updateStatusAction(): Action
    {
        return Action::make('updateStatus')->label('Update status')->icon(Heroicon::OutlinedPencilSquare)
            ->visible(fn () => Auth::user()?->hasPermission('complaints.manage'))
            ->fillForm(fn (Complaint $record) => ['status' => $record->status->value, 'resolution' => $record->resolution])
            ->schema([
                Select::make('status')->options(ComplaintStatus::options())->required(),
                Textarea::make('resolution')->label('Resolution (visible to the complainant)')->maxLength(5000),
                Textarea::make('internal_notes')->label('Internal note (staff only, appended)')->maxLength(5000),
            ])
            ->action(function (Complaint $record, array $data): void {
                /** @var User $actor */
                $actor = Auth::user();
                app(ComplaintService::class)->updateStatus($record, ComplaintStatus::from($data['status']), $actor, $data['resolution'] ?? null, $data['internal_notes'] ?? null);
                Notification::make()->title('Complaint updated')->success()->send();
            });
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Complaint')->columns(3)->schema([
                TextEntry::make('public_id')->label('ID'),
                TextEntry::make('complaint_type')->badge(),
                TextEntry::make('status')->badge(),
                TextEntry::make('investigation.public_id')->label('Investigation')->placeholder('—'),
                TextEntry::make('comment.public_id')->label('Comment')->placeholder('—'),
                TextEntry::make('evidence.public_id')->label('Evidence')->placeholder('—'),
                TextEntry::make('reporter_email')->label('Guest reporter email')->placeholder('Filed by a signed-in member'),
                TextEntry::make('created_at')->dateTime(),
                TextEntry::make('reviewed_at')->dateTime()->placeholder('—'),
                TextEntry::make('description')->columnSpanFull(),
                TextEntry::make('comment.body')->label('Reported comment text')->placeholder('—')->columnSpanFull(),
            ]),
            Section::make('Review')->schema([
                TextEntry::make('resolution')->placeholder('—'),
                TextEntry::make('internal_notes')->placeholder('—'),
            ]),
            Section::make('Note')->schema([
                TextEntry::make('note')->hiddenLabel()->state('For factual disputes, record the dispute and any evidence supplied. Onushondhan does not declare either side factually correct; act only on policy breaches (privacy, copyright, prohibited content, etc.).'),
            ])->visible(fn (Complaint $record) => $record->complaint_type === ComplaintType::FactualDispute),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListComplaints::route('/'), 'view' => ViewComplaint::route('/{record}')];
    }
}
