<?php

declare(strict_types=1);

namespace App\Filament\Resources\Settings;

use App\Filament\Resources\Settings\Pages\ManageSettings;
use App\Models\SystemSetting;
use App\Services\Settings;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

/** Non-secret runtime settings. Secrets live in the environment, never here. */
class SettingResource extends Resource
{
    protected static ?string $model = SystemSetting::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;

    protected static string|UnitEnum|null $navigationGroup = 'Configuration';

    protected static ?string $modelLabel = 'setting';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('key')->disabled(),
            Toggle::make('value_json')->label('Enabled')->visible(fn (?SystemSetting $record) => $record?->value_type === 'boolean'),
            TextInput::make('value_json')->label('Value')->numeric()->minValue(0)->maxValue(100000)->visible(fn (?SystemSetting $record) => $record?->value_type === 'integer'),
            TextInput::make('description')->maxLength(500),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('key')->searchable(),
                TextColumn::make('value_json')->label('Value')->formatStateUsing(fn ($state) => is_bool($state) ? ($state ? 'true' : 'false') : (string) json_encode($state)),
                TextColumn::make('description')->placeholder('—'),
                TextColumn::make('updated_at')->since(),
            ])
            ->recordActions([EditAction::make()->mutateDataUsing(function (array $data, SystemSetting $record) {
                if ($record->value_type === 'integer') {
                    $data['value_json'] = (int) $data['value_json'];
                } elseif ($record->value_type === 'boolean') {
                    $data['value_json'] = (bool) $data['value_json'];
                }

                return $data;
            })->after(fn () => app(Settings::class)->flush())]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageSettings::route('/')];
    }
}
