<?php

use App\Models\TermsAcceptance;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;

$payload = fn (array $o = []) => array_merge([
    'name' => 'Private Person',
    'email' => 'new@example.org',
    'password' => 'Sufficiently-Long1',
    'password_confirmation' => 'Sufficiently-Long1',
    'accept_agreements' => true,
], $o);

it('registers a user, assigns the user role, records agreement acceptance and sends verification', function () use ($payload) {
    Notification::fake();

    $res = $this->postJson('/api/v1/auth/register', $payload());

    $res->assertCreated()->assertJsonPath('data.email', 'new@example.org')->assertJsonPath('data.email_verified', false)
        ->assertJsonPath('data.roles', ['user'])->assertJsonPath('data.pending_agreements', []);
    $user = User::query()->where('email', 'new@example.org')->firstOrFail();
    expect(TermsAcceptance::query()->where('user_id', $user->id)->pluck('agreement_type')->sort()->values()->all())
        ->toBe(['content_policy', 'privacy_policy', 'terms_of_use']);
    expect(TermsAcceptance::query()->where('user_id', $user->id)->first()->ip_address)->not->toBeNull();
    Notification::assertSentTo($user, VerifyEmail::class);
    $this->assertAuthenticatedAs($user);
});

it('requires explicit acceptance of agreements', function () use ($payload) {
    $this->postJson('/api/v1/auth/register', $payload(['accept_agreements' => false]))
        ->assertUnprocessable()->assertJsonPath('code', 'validation_failed')->assertJsonValidationErrors('accept_agreements');
});

it('enforces the password policy', function (string $password) use ($payload) {
    $this->postJson('/api/v1/auth/register', $payload(['password' => $password, 'password_confirmation' => $password]))
        ->assertUnprocessable()->assertJsonValidationErrors('password');
})->with(['short1A', 'alllowercase123', 'NoNumbersHereAtAll']);

it('rejects duplicate emails', function () use ($payload) {
    User::factory()->create(['email' => 'new@example.org']);
    $this->postJson('/api/v1/auth/register', $payload())->assertUnprocessable()->assertJsonValidationErrors('email');
});

it('ignores privileged attributes supplied at registration (mass assignment)', function () use ($payload) {
    $this->postJson('/api/v1/auth/register', $payload([
        'status' => 'active', 'email_verified_at' => now()->toDateTimeString(), 'roles' => ['super_admin'], 'is_admin' => true,
    ]))->assertCreated();

    $user = User::query()->where('email', 'new@example.org')->firstOrFail();
    expect($user->hasVerifiedEmail())->toBeFalse()->and($user->roleSlugs())->toBe(['user']);
});

it('returns the standard error envelope with a request id', function () {
    $res = $this->postJson('/api/v1/auth/register', []);
    $res->assertUnprocessable()->assertJsonStructure(['message', 'code', 'errors', 'request_id']);
    expect($res->headers->get('X-Request-Id'))->toBe($res->json('request_id'));
});
