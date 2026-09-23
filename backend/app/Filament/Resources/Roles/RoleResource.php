<?php

declare(strict_types=1);

namespace App\Filament\Resources\Roles;

use App\Filament\Resources\Roles\Pages\ManageRoles;
use App\Models\Role;
use App\Services\AuditLogger;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Role → permission mapping (roles.manage). Changes are audited by AdminChangeObserver and the pivot hook below. */
class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'People';

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(120),
            TextInput::make('slug')->required()->alphaDash()->maxLength(64)->unique(ignoreRecord: true)
                ->disabled(fn (?Role $record) => $record !== null && in_array($record->slug, Role::PROTECTED_SLUGS, true)),
            TextInput::make('description')->maxLength(500)->columnSpanFull(),
            CheckboxList::make('permissions')->relationship('permissions', 'name')->columns(2)->columnSpanFull()
                ->helperText('identity.* and tips.view_any are highly sensitive — grant only where operationally justified.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withCount(['permissions', 'users']))
            ->columns([
                TextColumn::make('name'),
                TextColumn::make('slug')->badge(),
                TextColumn::make('permissions_count')->label('Permissions'),
                TextColumn::make('users_count')->label('Users'),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([
                EditAction::make()->after(fn (Role $record) => app(AuditLogger::class)->log('role.permissions_saved', $record, null, ['permissions' => $record->permissions()->pluck('slug')->all()])),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageRoles::route('/')];
    }
}
