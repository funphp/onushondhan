<?php

use App\Models\User;
use App\Services\AuthService;

function verificationParams(User $user): array
{
    parse_str((string) parse_url(AuthService::verificationUrl($user), PHP_URL_QUERY), $q);

    return $q;
}

it('builds verification links that point at the frontend', function () {
    $user = User::factory()->unverified()->create();
    expect(AuthService::verificationUrl($user))->toStartWith('http://localhost:3000/verify-email?');
});

it('verifies email with a valid signed link', function () {
    $user = User::factory()->unverified()->create();
    $this->postJson('/api/v1/auth/verify-email', verificationParams($user))->assertOk()->assertJsonPath('verified', true);
    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

it('rejects tampered, foreign or expired links', function () {
    $user = User::factory()->unverified()->create();
    $other = User::factory()->unverified()->create();
    $params = verificationParams($user);

    $this->postJson('/api/v1/auth/verify-email', ['signature' => str_repeat('a', 64)] + $params)->assertUnprocessable();
    $this->postJson('/api/v1/auth/verify-email', ['id' => $other->public_id] + $params)->assertUnprocessable();
    $this->travel(2)->hours();
    $this->postJson('/api/v1/auth/verify-email', $params)->assertUnprocessable()->assertJsonPath('code', 'verification_invalid');
    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
    expect($other->fresh()->hasVerifiedEmail())->toBeFalse();
});

it('blocks community actions until the email is verified', function () {
    $inv = $this->publishedInvestigation();
    $user = User::factory()->unverified()->acceptedAgreements()->create();

    $this->actingAs($user)->postJson("/api/v1/investigations/{$inv->public_id}/comments", ['body' => 'Hello there'])
        ->assertForbidden()->assertJsonPath('code', 'email_unverified');
});
