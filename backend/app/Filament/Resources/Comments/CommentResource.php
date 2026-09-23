<?php

declare(strict_types=1);

namespace App\Filament\Resources\Comments;

use App\Enums\CommentStatus;
use App\Enums\ModerationActionType as A;
use App\Filament\Resources\Comments\Pages\ListComments;
use App\Filament\Support\ModerationActions;
use App\Models\Comment;
use App\Services\AuditLogger;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use UnitEnum;

class CommentResource extends Resource
{
    protected static ?string $model = Comment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Community';

    protected static ?int $navigationSort = 1;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->withTrashed()->with(['user:id,username', 'investigation:id,public_id'])->withCount(['reports as open_reports_count' => fn ($r) => $r->where('status', 'open')]))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('body')->limit(80)->wrap()->searchable(),
                TextColumn::make('user.username')->label('Author'),
                TextColumn::make('investigation.public_id')->label('Investigation'),
                TextColumn::make('status')->badge()->color(fn (CommentStatus $state) => match ($state) {
                    CommentStatus::Visible => 'success', CommentStatus::Pending => 'warning', CommentStatus::Hidden, CommentStatus::Removed => 'danger', default => 'gray',
                }),
                TextColumn::make('open_reports_count')->label('Open reports')->badge()->color(fn (int $state) => $state > 0 ? 'danger' : 'gray'),
                TextColumn::make('score')->sortable(),
                IconColumn::make('is_pinned')->boolean()->label('Pinned'),
                TextColumn::make('created_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(CommentStatus::options()),
                Filter::make('reported')->label('Has open reports')->query(fn ($query) => $query->whereHas('reports', fn ($r) => $r->where('status', 'open'))),
            ])
            ->recordActions([
                ActionGroup::make([
                    ModerationActions::make('comment', A::NoAction, 'Dismiss reports', 'gray', fn (Comment $r) => $r->open_reports_count > 0),
                    ModerationActions::make('comment', A::Hide, 'Hide', 'warning', fn (Comment $r) => in_array($r->status, [CommentStatus::Visible, CommentStatus::Pending], true)),
                    ModerationActions::make('comment', A::Remove, 'Remove', 'danger', fn (Comment $r) => $r->status !== CommentStatus::Removed),
                    ModerationActions::make('comment', A::Restore, 'Restore', 'success', fn (Comment $r) => $r->status !== CommentStatus::Visible && ! $r->trashed()),
                    ModerationActions::make('comment', A::LockComments, 'Lock thread', 'gray', fn (Comment $r) => $r->status === CommentStatus::Visible),
                    Action::make('pin')->label(fn (Comment $r) => $r->is_pinned ? 'Unpin' : 'Pin')
                        ->visible(fn () => Auth::user()?->hasPermission('comments.moderate'))
                        ->action(function (Comment $record): void {
                            $record->forceFill(['is_pinned' => ! $record->is_pinned])->save();
                            app(AuditLogger::class)->log($record->is_pinned ? 'comment.pinned' : 'comment.unpinned', $record);
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListComments::route('/')];
    }
}
