<?php

use App\Models\LoginLog;
use App\Services\SessionRevoker;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

it('logs in with valid credentials and records a login log', function () {
    $user = $this->member(['email' => 'a@example.org']);

    $this->postJson('/api/v1/auth/login', ['email' => 'A@example.org', 'password' => 'Password-123'])
        ->assertOk()->assertJsonPath('data.email', 'a@example.org');

    $this->assertAuthenticatedAs($user);
    expect(LoginLog::query()->where('user_id', $user->id)->where('success', true)->exists())->toBeTrue();
    expect($user->fresh()->last_login_at)->not->toBeNull();
});

it('rejects invalid credentials without revealing whether the account exists', function () {
    $this->member(['email' => 'a@example.org']);

    $wrongPassword = $this->postJson('/api/v1/auth/login', ['email' => 'a@example.org', 'password' => 'nope-nope-1A']);
    $noAccount = $this->postJson('/api/v1/auth/login', ['email' => 'ghost@example.org', 'password' => 'nope-nope-1A']);

    $wrongPassword->assertUnprocessable()->assertJsonPath('code', 'invalid_credentials');
    $noAccount->assertUnprocessable()->assertJsonPath('code', 'invalid_credentials');
    expect($wrongPassword->json('message'))->toBe($noAccount->json('message'));
    $this->assertGuest();
});

it('blocks suspended, blocked and deactivated accounts', function (string $status) {
    $user = $this->member(['email' => 'b@example.org']);
    $user->forceFill(['status' => $status])->save();

    $this->postJson('/api/v1/auth/login', ['email' => 'b@example.org', 'password' => 'Password-123'])
        ->assertForbidden()->assertJsonPath('code', 'account_inactive');
    $this->assertGuest();
})->with(['suspended', 'blocked', 'deactivated']);

it('lets an expired suspension sign in again', function () {
    $user = $this->member(['email' => 'c@example.org']);
    $user->forceFill(['status' => 'suspended', 'suspended_until' => now()->subDay()])->save();

    $this->postJson('/api/v1/auth/login', ['email' => 'c@example.org', 'password' => 'Password-123'])->assertOk();
    expect($user->fresh()->status->value)->toBe('active');
});

it('rate-limits repeated login attempts (brute force)', function () {
    $this->member(['email' => 'd@example.org']);
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/login', ['email' => 'd@example.org', 'password' => 'wrong-Pass1']);
    }
    $this->postJson('/api/v1/auth/login', ['email' => 'd@example.org', 'password' => 'Password-123'])
        ->assertStatus(429)->assertJsonPath('code', 'rate_limited');
});

it('rejects sessions of accounts suspended after login', function () {
    $user = $this->member();
    $this->actingAs($user)->getJson('/api/v1/auth/me')->assertOk();
    $user->forceFill(['status' => 'blocked'])->save();
    $this->actingAs($user->fresh())->getJson('/api/v1/auth/me')->assertForbidden()->assertJsonPath('code', 'account_inactive');
});

it('logs out', function () {
    $user = $this->member();
    $this->actingAs($user)->postJson('/api/v1/auth/logout')->assertOk();
    $this->assertGuest('web');
});

it('requires authentication for /me', function () {
    $this->getJson('/api/v1/auth/me')->assertUnauthorized()->assertJsonPath('code', 'unauthenticated');
});

it('changes password with the current password and signs out other sessions', function () {
    $user = $this->member();
    DB::table('sessions')->insert(['id' => 'other-session', 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);

    $this->actingAs($user)->putJson('/api/v1/auth/password', ['current_password' => 'wrong', 'password' => 'New-Password-99', 'password_confirmation' => 'New-Password-99'])
        ->assertUnprocessable();
    $this->actingAs($user)->putJson('/api/v1/auth/password', ['current_password' => 'Password-123', 'password' => 'New-Password-99', 'password_confirmation' => 'New-Password-99'])
        ->assertOk();

    expect(Hash::check('New-Password-99', $user->fresh()->password))->toBeTrue();
    expect(DB::table('sessions')->where('id', 'other-session')->exists())->toBeFalse();
});

it('invalidates the session on logout so later requests are unauthenticated', function () {
    $this->member(['email' => 'e@example.org']);
    $this->postJson('/api/v1/auth/login', ['email' => 'e@example.org', 'password' => 'Password-123'])->assertOk();
    $this->getJson('/api/v1/auth/me')->assertOk();
    $this->postJson('/api/v1/auth/logout')->assertOk();
    $this->getJson('/api/v1/auth/me')->assertUnauthorized();
});

it('rejects a revoked session id even if an in-flight request wrote it back', function () {
    $user = $this->member();
    $revoker = app(SessionRevoker::class);
    $this->startSession();
    $sid = session()->getId();
    DB::table('sessions')->insert(['id' => $sid, 'user_id' => $user->id, 'payload' => '', 'last_activity' => time()]);
    $revoker->revokeUser($user);
    expect($revoker->isRevoked($sid))->toBeTrue();
    expect(DB::table('sessions')->where('id', $sid)->exists())->toBeFalse();
});
