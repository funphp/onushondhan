<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserStatus;
use App\Models\Concerns\HasRoles;
use Database\Factories\UserFactory;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasName;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use SensitiveParameter;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $username
 * @property string $email
 * @property UserStatus $status
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property \Illuminate\Support\Carbon|null $phone_verified_at
 * @property \Illuminate\Support\Carbon|null $suspended_until
 * @property \Illuminate\Support\Carbon|null $last_login_at
 * @property \Illuminate\Support\Carbon $created_at
 * @property-read Investigator|null $investigator
 */
class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery, HasName, MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasRoles;
    use HasUlids;
    use Notifiable;
    use SoftDeletes;

    /** Status / verification / MFA columns are deliberately NOT mass assignable. */
    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'phone',
        'notification_preferences',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'app_authentication_secret',
        'app_authentication_recovery_codes',
    ];

    protected $attributes = [
        'status' => 'active',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'suspended_until' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
            'notification_preferences' => 'array',
            'app_authentication_secret' => 'encrypted',
            'app_authentication_recovery_codes' => 'encrypted:array',
        ];
    }

    /** @return list<string> */
    public function uniqueIds(): array
    {
        return ['public_id'];
    }

    protected static function booted(): void
    {
        static::creating(function (User $user): void {
            if (blank($user->username)) {
                $user->username = self::generateUsername();
            }
        });
    }

    public static function generateUsername(): string
    {
        do {
            $candidate = 'reader-'.Str::lower(Str::random(8));
        } while (self::withTrashed()->where('username', $candidate)->exists());

        return $candidate;
    }

    /** @return HasOne<Investigator, $this> */
    public function investigator(): HasOne
    {
        return $this->hasOne(Investigator::class);
    }

    /** @return HasMany<TermsAcceptance, $this> */
    public function termsAcceptances(): HasMany
    {
        return $this->hasMany(TermsAcceptance::class);
    }

    /** @return BelongsToMany<Investigator, $this> */
    public function followedInvestigators(): BelongsToMany
    {
        return $this->belongsToMany(Investigator::class, 'investigator_followers')->withPivot('created_at');
    }

    /** @return HasMany<Bookmark, $this> */
    public function bookmarks(): HasMany
    {
        return $this->hasMany(Bookmark::class);
    }

    public function isActive(): bool
    {
        if ($this->status === UserStatus::Suspended && $this->suspended_until !== null && $this->suspended_until->isPast()) {
            return true;
        }

        return $this->status === UserStatus::Active;
    }

    public function isInvestigator(): bool
    {
        return $this->investigator !== null && $this->hasRole('investigator');
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(['moderator', 'admin', 'super_admin']);
    }

    public function wantsEmailFor(string $category): bool
    {
        $prefs = $this->notification_preferences ?? [];

        return (bool) ($prefs['email'][$category] ?? true);
    }

    // --- Filament ---------------------------------------------------------

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isActive() && $this->hasVerifiedEmail() && $this->isStaff();
    }

    public function getFilamentName(): string
    {
        return $this->username;
    }

    public function getAppAuthenticationSecret(): ?string
    {
        return $this->app_authentication_secret;
    }

    public function saveAppAuthenticationSecret(#[SensitiveParameter] ?string $secret): void
    {
        $this->app_authentication_secret = $secret;
        $this->save();
    }

    public function getAppAuthenticationHolderName(): string
    {
        return $this->email;
    }

    /** @return ?array<string> */
    public function getAppAuthenticationRecoveryCodes(): ?array
    {
        return $this->app_authentication_recovery_codes;
    }

    /** @param ?array<string> $codes */
    public function saveAppAuthenticationRecoveryCodes(#[SensitiveParameter] ?array $codes): void
    {
        $this->app_authentication_recovery_codes = $codes;
        $this->save();
    }
}
