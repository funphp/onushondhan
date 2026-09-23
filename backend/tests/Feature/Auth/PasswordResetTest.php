<?php

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

it('responds identically whether or not the account exists', function () {
    Notification::fake();
    $user = $this->member(['email' => 'r@example.org']);

    $a = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'r@example.org'])->assertOk();
    $b = $this->postJson('/api/v1/auth/forgot-password', ['email' => 'nobody@example.org'])->assertOk();

    expect($a->json('message'))->toBe($b->json('message'));
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use ($user) {
        return str_starts_with(($n->toMail($user))->actionUrl, 'http://localhost:3000/reset-password?');
    });
});

it('resets the password with a valid token, once, and invalidates sessions', function () {
    Notification::fake();
    $user = $this->member(['email' => 'r@example.org']);
    DB::table('sessions')->insert(['id' => 's1', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $this->postJson('/api/v1/auth/forgot-password', ['email' => 'r@example.org']);

    $token = null;
    Notification::assertSentTo($user, ResetPassword::class, function (ResetPassword $n) use (&$token) {
        $token = $n->token;

        return true;
    });

    $body = ['token' => $token, 'email' => 'r@example.org', 'password' => 'Brand-New-Pass9', 'password_confirmation' => 'Brand-New-Pass9'];
    $this->postJson('/api/v1/auth/reset-password', $body)->assertOk();
    expect(Hash::check('Brand-New-Pass9', $user->fresh()->password))->toBeTrue();
    expect(DB::table('sessions')->where('user_id', $user->id)->exists())->toBeFalse();

    // Single use.
    $this->postJson('/api/v1/auth/reset-password', $body)->assertUnprocessable()->assertJsonPath('code', 'reset_invalid');
});

it('rejects an invalid token', function () {
    $this->member(['email' => 'r@example.org']);
    $this->postJson('/api/v1/auth/reset-password', ['token' => 'bogus', 'email' => 'r@example.org', 'password' => 'Brand-New-Pass9', 'password_confirmation' => 'Brand-New-Pass9'])
        ->assertUnprocessable();
});
