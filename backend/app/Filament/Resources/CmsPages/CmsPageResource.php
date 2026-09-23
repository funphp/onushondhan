<?php

declare(strict_types=1);

namespace App\Filament\Resources\CmsPages;

use App\Enums\CmsStatus;
use App\Filament\Resources\CmsPages\Pages\ManageCmsPages;
use App\Models\CmsPage;
use BackedEnum;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class CmsPageResource extends Resource
{
    protected static ?string $model = CmsPage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 3;

    protected static ?string $modelLabel = 'CMS page';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            TextInput::make('title')->required()->maxLength(250),
            TextInput::make('slug')->required()->maxLength(120)->alphaDash()->unique(ignoreRecord: true),
            Select::make('page_type')->options(['page' => 'Page', 'legal' => 'Legal', 'announcement' => 'Announcement'])->required()->default('page'),
            Select::make('status')->options(CmsStatus::options())->required()->default('draft'),
            TextInput::make('excerpt')->maxLength(1000)->columnSpanFull(),
            MarkdownEditor::make('body')->required()->columnSpanFull()
                ->helperText('Markdown. Raw HTML is not rendered on the public site.'),
            Toggle::make('requires_legal_review')->helperText('Shows a "template — requires legal review" notice publicly.'),
            DateTimePicker::make('published_at'),
            TextInput::make('seo_title')->maxLength(250),
            TextInput::make('seo_description')->maxLength(500),
            TextInput::make('canonical_url')->url()->maxLength(500)->columnSpanFull(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable(),
                TextColumn::make('slug')->searchable(),
                TextColumn::make('page_type')->badge(),
                TextColumn::make('status')->badge(),
                IconColumn::make('requires_legal_review')->label('Legal review')->boolean(),
                TextColumn::make('published_at')->dateTime()->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable(),
            ])
            ->filters([SelectFilter::make('page_type')->options(['page' => 'Page', 'legal' => 'Legal', 'announcement' => 'Announcement'])])
            ->headerActions([CreateAction::make()])
            ->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageCmsPages::route('/')];
    }
}
