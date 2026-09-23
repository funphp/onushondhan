<?php

declare(strict_types=1);

namespace App\Filament\Resources\Users;

use App\Enums\ModerationActionType as A;
use App\Enums\UserStatus;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Support\ModerationActions;
use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\SecurityLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\CheckboxList;
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
use Illuminate\Support\Facades\DB;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'username';

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('roles'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('username')->searchable(),
                TextColumn::make('email')->searchable()->toggleable(),
                TextColumn::make('roles.slug')->label('Roles')->badge(),
                TextColumn::make('status')->badge()->color(fn (UserStatus $state) => $state === UserStatus::Active ? 'success' : 'danger'),
                TextColumn::make('email_verified_at')->label('Verified')->since()->placeholder('No'),
                TextColumn::make('last_login_at')->since()->sortable(),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(UserStatus::options()),
                SelectFilter::make('role')->relationship('roles', 'name'),
            ])
            ->recordActions([ViewAction::make(), ActionGroup::make(self::accountActions())]);
    }

    /** @return list<Action> */
    public static function accountActions(): array
    {
        return [
            ModerationActions::make('user', A::Warn, 'Warn', 'warning'),
            ModerationActions::make('user', A::SuspendUser, 'Suspend', 'warning', fn (User $r) => $r->status === UserStatus::Active, withDays: true),
            ModerationActions::make('user', A::BanUser, 'Block', 'danger', fn (User $r) => $r->status !== UserStatus::Blocked),
            ModerationActions::make('user', A::ReinstateUser, 'Reinstate', 'success', fn (User $r) => $r->status !== UserStatus::Active),
            Action::make('roles')->label('Manage roles')->icon(Heroicon::OutlinedKey)
                ->visible(fn (User $r) => Auth::user()?->can('manageRoles', $r) ?? false)
                ->fillForm(fn (User $r) => ['roles' => $r->roles->pluck('id')->all()])
                ->schema([
                    CheckboxList::make('roles')->options(fn () => Role::query()
                        ->when(! Auth::user()?->isSuperAdmin(), fn ($query) => $query->where('slug', '!=', 'super_admin'))
                        ->pluck('name', 'id'))->required(),
                ])
                ->action(function (User $record, array $data): void {
                    /** @var User $actor */
                    $actor = Auth::user();
                    $allowed = Role::query()->when(! $actor->isSuperAdmin(), fn ($query) => $query->where('slug', '!=', 'super_admin'))->pluck('id')->all();
                    $ids = array_values(array_intersect(array_map('intval', $data['roles']), $allowed));
                    // A non-super-admin can never strip or grant super_admin.
                    if (! $actor->isSuperAdmin() && $record->isSuperAdmin()) {
                        Notification::make()->title('Not allowed')->danger()->send();

                        return;
                    }
                    $before = $record->roleSlugs();
                    DB::transaction(function () use ($record, $ids, $before, $actor): void {
                        $record->roles()->sync(array_fill_keys($ids, ['created_at' => now()]));
                        $record->flushRoleCache();
                        app(AuditLogger::class)->log('user.roles_changed', $record, ['roles' => $before], ['roles' => $record->roleSlugs()], $actor);
                        app(SecurityLogger::class)->event('roles.changed', $record, 'warning', ['by' => $actor->public_id]);
                    });
                    Notification::make()->title('Roles updated')->success()->send();
                }),
        ];
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Account')->columns(3)->schema([
                TextEntry::make('username'),
                TextEntry::make('email'),
                TextEntry::make('status')->badge(),
                TextEntry::make('status_reason')->placeholder('—'),
                TextEntry::make('suspended_until')->dateTime()->placeholder('—'),
                TextEntry::make('roles.name')->badge(),
                TextEntry::make('email_verified_at')->dateTime()->placeholder('Not verified'),
                TextEntry::make('phone_verified_at')->dateTime()->placeholder('Not verified'),
                TextEntry::make('last_login_at')->dateTime()->placeholder('—'),
                TextEntry::make('investigator.public_name')->label('Investigator profile')->placeholder('—'),
                TextEntry::make('created_at')->dateTime(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListUsers::route('/'), 'view' => ViewUser::route('/{record}')];
    }
}
