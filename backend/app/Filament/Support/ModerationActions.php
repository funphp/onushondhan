<?php

declare(strict_types=1);

namespace App\Filament\Support;

use App\Enums\ModerationActionType;
use App\Exceptions\ApiException;
use App\Models\User;
use App\Services\ModerationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

/**
 * Builds admin-panel actions that route through ModerationService, so every panel
 * moderation decision is permission-checked, recorded and audited exactly like the API.
 */
final class ModerationActions
{
    /**
     * @param  \Closure|null  $visible  receives the record, returns whether the action is shown
     */
    public static function make(string $targetType, ModerationActionType $type, string $label, string $color = 'gray', ?\Closure $visible = null, bool $withPublicNote = false, bool $withDays = false): Action
    {
        $fields = [Textarea::make('reason')->label('Reason (recorded in the audit log)')->required()->minLength(5)->maxLength(1000)];
        if ($withPublicNote) {
            $fields[] = TextInput::make('public_note')->label('Public note (optional, shown on the tombstone)')->maxLength(500)
                ->helperText('Keep it factual. Do not disclose sensitive moderation details.');
        }
        if ($withDays) {
            $fields[] = TextInput::make('days')->label('Suspend for days (blank = indefinite)')->numeric()->minValue(1)->maxValue(3650);
        }

        return Action::make($targetType.'_'.$type->value)
            ->label($label)
            ->color($color)
            ->requiresConfirmation()
            ->schema($fields)
            ->visible(fn (Model $record): bool => $visible === null || $visible($record))
            ->action(function (Model $record, array $data) use ($targetType, $type): void {
                /** @var User $actor */
                $actor = Auth::user();
                $details = array_filter([
                    'public_note' => $data['public_note'] ?? null,
                    'days' => isset($data['days']) && $data['days'] !== '' ? (int) $data['days'] : null,
                ], fn ($v) => $v !== null && $v !== '');

                try {
                    app(ModerationService::class)->apply($actor, $targetType, $record, $type, (string) $data['reason'], $details);
                    Notification::make()->title('Action recorded')->success()->send();
                } catch (ApiException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }
}
