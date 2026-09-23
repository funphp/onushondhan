<?php

declare(strict_types=1);

namespace App\Filament\Resources\TermsVersions;

use App\Enums\AgreementType;
use App\Enums\TermsStatus;
use App\Filament\Resources\TermsVersions\Pages\ManageTermsVersions;
use App\Models\TermsVersion;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\MarkdownEditor;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use UnitEnum;

/**
 * Agreement versions. Drafts are editable; publishing makes a version current and
 * immutable, and every user must re-accept it before further protected actions.
 */
class TermsVersionResource extends Resource
{
    protected static ?string $model = TermsVersion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static string|UnitEnum|null $navigationGroup = 'Content';

    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'agreement version';

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(2)->components([
            Select::make('agreement_type')->options(AgreementType::options())->required(),
            TextInput::make('version')->required()->maxLength(32)->helperText('e.g. 1.1 — must be unique per agreement type.'),
            TextInput::make('title')->required()->maxLength(250)->columnSpanFull(),
            MarkdownEditor::make('content')->required()->columnSpanFull()
                ->helperText('Legal text is a product-policy template until reviewed by qualified counsel.'),
            DateTimePicker::make('effective_at')->helperText('Leave empty to take effect on publication.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('agreement_type')->badge(),
                TextColumn::make('version'),
                TextColumn::make('title')->limit(50),
                TextColumn::make('status')->badge()->color(fn (TermsStatus $state) => $state === TermsStatus::Published ? 'success' : 'gray'),
                TextColumn::make('effective_at')->dateTime(),
                TextColumn::make('published_at')->dateTime()->sortable(),
                TextColumn::make('acceptances_count')->counts('acceptances')->label('Acceptances'),
            ])
            ->filters([SelectFilter::make('agreement_type')->options(AgreementType::options())])
            ->headerActions([CreateAction::make()->mutateDataUsing(fn (array $data) => $data + ['created_by' => Auth::id()])])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('publish')->label('Publish')->color('success')->requiresConfirmation()
                    ->modalDescription('Publishing makes this version current and immutable. All affected users will be asked to accept it again.')
                    ->visible(fn (TermsVersion $r) => $r->status === TermsStatus::Draft && Auth::user()?->hasPermission('terms.manage'))
                    ->action(function (TermsVersion $record): void {
                        DB::transaction(function () use ($record): void {
                            $record->status = TermsStatus::Published;
                            $record->published_at = now();
                            $record->effective_at ??= now();
                            $record->save();
                        });
                        Notification::make()->title('Agreement version published')->success()->send();
                    }),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ManageTermsVersions::route('/')];
    }
}
