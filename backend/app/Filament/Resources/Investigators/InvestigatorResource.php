<?php

declare(strict_types=1);

namespace App\Filament\Resources\Investigators;

use App\Enums\InvestigatorStatus;
use App\Enums\ModerationActionType as A;
use App\Exceptions\ApiException;
use App\Filament\Resources\Investigators\Pages\ListInvestigators;
use App\Filament\Support\ModerationActions;
use App\Models\Investigator;
use App\Models\User;
use App\Services\IdentityAccessService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class InvestigatorResource extends Resource
{
    protected static ?string $model = Investigator::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 2;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('public_name')->searchable(),
                TextColumn::make('slug'),
                TextColumn::make('identity_mode')->badge(),
                TextColumn::make('status')->badge()->color(fn (InvestigatorStatus $state) => $state === InvestigatorStatus::Active ? 'success' : 'danger'),
                TextColumn::make('published_investigations_count')->label('Published')->sortable(),
                TextColumn::make('supporters_count')->label('Supporters')->sortable(),
                TextColumn::make('followers_count')->label('Followers')->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(InvestigatorStatus::options()),
                SelectFilter::make('identity_mode')->options(['real_name' => 'Real name', 'protected_public_identity' => 'Protected identity']),
            ])
            ->recordActions([ActionGroup::make([
                ModerationActions::make('investigator', A::Warn, 'Warn', 'warning'),
                ModerationActions::make('investigator', A::Restrict, 'Suspend profile', 'danger', fn (Investigator $r) => $r->status === InvestigatorStatus::Active),
                ModerationActions::make('investigator', A::Restore, 'Restore profile', 'success', fn (Investigator $r) => $r->status !== InvestigatorStatus::Active),
                Action::make('revealIdentity')->label('Reveal private identity (audited)')->icon(Heroicon::OutlinedLockOpen)->color('danger')
                    ->visible(fn () => Auth::user()?->hasPermission('identity.view_private') ?? false)
                    ->modalDescription('Access to private identity is logged with your justification and raises a security event. Only proceed where operationally or legally required.')
                    ->schema([Textarea::make('reason')->label('Justification')->required()->minLength(10)->maxLength(1000)])
                    ->action(function (Investigator $record, array $data): void {
                        /** @var User $actor */
                        $actor = Auth::user();
                        try {
                            $identity = app(IdentityAccessService::class)->reveal($actor, $record, $data['reason']);
                        } catch (ApiException $e) {
                            Notification::make()->title($e->getMessage())->danger()->send();

                            return;
                        }
                        $lines = collect($identity)->map(fn ($v, $k) => str_replace('_', ' ', ucfirst($k)).': '.($v ?? '—'))->implode("\n");
                        Notification::make()->title('Private identity — '.$record->public_name)->body(nl2br(e($lines)))->persistent()->warning()->send();
                    }),
            ])]);
    }

    public static function getPages(): array
    {
        return ['index' => ListInvestigators::route('/')];
    }
}
