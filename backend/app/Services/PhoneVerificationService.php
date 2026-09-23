<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\SmsSender;
use App\Exceptions\ApiException;
use App\Models\PhoneVerification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Optional phone verification (anti-abuse signal). Codes are hashed, expire, and are attempt-limited. */
class PhoneVerificationService
{
    private const MAX_ATTEMPTS = 5;

    public function __construct(private readonly SmsSender $sms, private readonly AuditLogger $audit) {}

    public function start(User $user, string $phone): void
    {
        $phone = preg_replace('/[^\d+]/', '', $phone) ?? '';
        if (preg_match('/^\+?[1-9]\d{7,14}$/', $phone) !== 1) {
            throw new ApiException('Enter the phone number in international format, e.g. +8801XXXXXXXXX.', 'invalid_phone', 422, ['phone' => ['Invalid phone number.']]);
        }
        $code = (string) random_int(100000, 999999);

        DB::transaction(function () use ($user, $phone, $code): void {
            PhoneVerification::query()->where('user_id', $user->id)->whereNull('verified_at')->delete();
            PhoneVerification::query()->create([
                'user_id' => $user->id,
                'phone' => $phone,
                'code_hash' => Hash::make($code),
                'expires_at' => now()->addMinutes(10),
            ]);
        });

        $this->sms->send($phone, "Your Onushondhan verification code is {$code}. It expires in 10 minutes.");
    }

    public function verify(User $user, string $code): void
    {
        $pending = PhoneVerification::query()->where('user_id', $user->id)->whereNull('verified_at')->latest('id')->first();
        $fail = new ApiException('The code is invalid or has expired.', 'invalid_code', 422, ['code' => ['Invalid or expired code.']]);
        if ($pending === null || $pending->expires_at->isPast() || $pending->attempts >= self::MAX_ATTEMPTS) {
            throw $fail;
        }
        $pending->increment('attempts');
        if (! Hash::check($code, $pending->code_hash)) {
            throw $fail;
        }

        DB::transaction(function () use ($user, $pending): void {
            $pending->forceFill(['verified_at' => now()])->save();
            $user->forceFill(['phone' => $pending->phone, 'phone_verified_at' => now()])->save();
            $this->audit->log('user.phone_verified', $user, null, null, $user);
        });
    }
}
