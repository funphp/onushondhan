<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContactPreference;
use App\Enums\TipStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $public_id
 * @property int|null $investigation_id
 * @property int $investigator_id
 * @property int|null $sender_user_id
 * @property bool $anonymous_to_investigator
 * @property string|null $guest_access_hash
 * @property string $subject
 * @property string $message
 * @property ContactPreference $contact_preference
 * @property string|null $contact_value
 * @property TipStatus $status
 * @property Carbon|null $last_message_at
 * @property Carbon $created_at
 * @property-read Investigator $investigator
 * @property-read Investigation|null $investigation
 * @property-read User|null $sender
 */
class Tip extends Model
{
    use HasUlids;

    protected $fillable = ['subject', 'message', 'contact_preference', 'contact_value', 'anonymous_to_investigator'];

    protected $hidden = ['guest_access_hash', 'contact_value'];

    protected $attributes = ['status' => 'new'];

    protected function casts(): array
    {
        return [
            'status' => TipStatus::class,
            'contact_preference' => ContactPreference::class,
            'contact_value' => 'encrypted',
            'anonymous_to_investigator' => 'boolean',
            'last_message_at' => 'datetime',
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function isGuest(): bool
    {
        return $this->sender_user_id === null;
    }

    /** @return BelongsTo<Investigator, $this> */
    public function investigator(): BelongsTo
    {
        return $this->belongsTo(Investigator::class);
    }

    /** @return BelongsTo<Investigation, $this> */
    public function investigation(): BelongsTo
    {
        return $this->belongsTo(Investigation::class);
    }

    /** @return BelongsTo<User, $this> */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_user_id');
    }

    /** @return HasMany<TipMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(TipMessage::class)->orderBy('created_at')->orderBy('id');
    }

    /** @return HasMany<TipAttachment, $this> */
    public function attachments(): HasMany
    {
        return $this->hasMany(TipAttachment::class);
    }
}
