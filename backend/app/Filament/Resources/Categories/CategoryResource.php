<?php

declare(strict_types=1);

namespace App\Filament\Resources\Categories;

use App\Filament\Resources\Categories\Pages\ManageCategories;
use App\Models\InvestigationCategory;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class CategoryResource extends Resource
{
    protected static ?string $model = InvestigationCategory::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 5;

    protected static ?string $modelLabel = 'category';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('name')->label('Name (English)')->required()->maxLength(120),
            TextInput::make('name_bn')->label('Name (Bangla)')->maxLength(120),
            TextInput::make('slug')->required()->maxLength(140)->alphaDash()->unique(ignoreRecord: true),
            Select::make('parent_id')->label('Parent')->relationship('parent', 'name', fn ($query) => $query->whereNull('parent_id'))->nullable(),
            Select::make('status')->options(['active' => 'Active', 'hidden' => 'Hidden'])->required()->default('active'),
            TextInput::make('sort_order')->numeric()->default(0),
            Textarea::make('description')->maxLength(1000)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with('parent'))
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('name_bn')->label('Bangla')->searchable(),
                TextColumn::make('slug'),
                TextColumn::make('parent.name')->label('Parent')->placeholder('—'),
                TextColumn::make('status')->badge(),
                TextColumn::make('sort_order')->sortable(),
            ])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCategories::route('/')];
    }
}
