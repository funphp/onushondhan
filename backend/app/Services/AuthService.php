<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\UserStatus;
use App\Exceptions\ApiException;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthService
{
    public function __construct(
        private readonly AgreementService $agreements,
        private readonly AuditLogger $audit,
        private readonly SecurityLogger $security,
    ) {}

    /** @param array{name: string, username?: string|null, email: string, password: string} $data */
    public function register(array $data): User
    {
        $user = DB::transaction(function () use ($data): User {
            $user = new User;
            $user->fill([
                'name' => $data['name'],
                'username' => $data['username'] ?? null,
                'email' => Str::lower($data['email']),
                'password' => $data['password'],
            ]);
            $user->save();
            $user->assignRole('user');
            $this->agreements->acceptCurrent($user, config('onushondhan.agreements.user'), 'registration');
            $this->audit->log('user.registered', $user, null, ['username' => $user->username], $user);

            return $user;
        });

        event(new Registered($user));

        return $user;
    }

    public function attemptLogin(string $email, string $password, bool $remember): User
    {
        $email = Str::lower(trim($email));
        /** @var User|null $user */
        $user = User::query()->where('email', $email)->first();

        if ($user === null || ! Hash::check($password, $user->password)) {
            $this->security->login($user, $email, false, 'invalid_credentials');
            if ($user !== null) {
                $this->security->event('login.failed', $user, 'info');
            }
            throw new ApiException('These credentials do not match our records.', 'invalid_credentials', 422);
        }

        if ($user->status === UserStatus::Suspended && $user->suspended_until !== null && $user->suspended_until->isPast()) {
            $user->forceFill(['status' => UserStatus::Active, 'suspended_until' => null, 'status_reason' => null])->save();
        }

        if (! $user->isActive()) {
            $this->security->login($user, $email, false, 'account_'.$user->status->value);
            throw new ApiException('This account is not active. Contact support if you believe this is a mistake.', 'account_inactive', 403);
        }

        Auth::guard('web')->login($user, $remember);
        $user->forceFill(['last_login_at' => now()])->save();
        if (Hash::needsRehash($user->password)) {
            $user->forceFill(['password' => $password])->save();
        }
        $this->security->login($user, $email, true);

        return $user;
    }

    /** Invalidate every other session for this user (after credential changes). */
    public function logoutOtherSessions(User $user, ?string $keepSessionId): void
    {
        DB::table('sessions')->where('user_id', $user->id)
            ->when($keepSessionId, fn ($q) => $q->where('id', '!=', $keepSessionId))
            ->delete();
        $user->forceFill(['remember_token' => Str::random(60)])->save();
    }

    public static function verificationSignature(int $userId, string $hash, int $expires): string
    {
        return hash_hmac('sha256', "verify-email|{$userId}|{$hash}|{$expires}", (string) config('app.key'));
    }

    public static function verificationUrl(User $user): string
    {
        $expires = now()->addMinutes((int) config('auth.verification.expire', 60))->getTimestamp();
        $hash = sha1($user->getEmailForVerification());
        $query = http_build_query([
            'id' => $user->public_id,
            'hash' => $hash,
            'expires' => $expires,
            'signature' => self::verificationSignature($user->id, $hash, $expires),
        ]);

        return config('onushondhan.frontend_url').'/verify-email?'.$query;
    }

    public function verifyEmail(string $publicId, string $hash, int $expires, string $signature): User
    {
        $user = User::query()->where('public_id', $publicId)->first();
        $invalid = new ApiException('This verification link is invalid or has expired.', 'verification_invalid', 422);
        if ($user === null || $expires < now()->getTimestamp()) {
            throw $invalid;
        }
        if (! hash_equals(self::verificationSignature($user->id, $hash, $expires), $signature)
            || ! hash_equals(sha1($user->getEmailForVerification()), $hash)) {
            throw $invalid;
        }
        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            $this->audit->log('user.email_verified', $user, null, null, $user);
        }

        return $user;
    }
}
