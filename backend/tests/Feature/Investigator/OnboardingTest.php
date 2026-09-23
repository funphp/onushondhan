<?php

use App\Models\AuditLog;
use App\Models\TermsAcceptance;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

$onboard = fn (array $o = []) => array_merge([
    'public_name' => 'Delta Watch',
    'identity_mode' => 'protected_public_identity',
    'accept_investigator_agreement' => true,
    'private_identity' => ['legal_or_private_name' => 'Secret Legal Name', 'private_email' => 'secret@example.org'],
], $o);

it('onboards a verified user as investigator with protected identity', function () use ($onboard) {
    $user = $this->member();

    $this->actingAs($user)->postJson('/api/v1/me/investigator', $onboard())
        ->assertCreated()->assertJsonPath('data.public_name', 'Delta Watch')->assertJsonPath('data.slug', 'delta-watch');

    $user->refresh();
    expect($user->hasRole('investigator'))->toBeTrue();
    expect(TermsAcceptance::query()->where('user_id', $user->id)->where('agreement_type', 'investigator_responsibility')->exists())->toBeTrue();
    expect($user->investigator->privateIdentity->legal_or_private_name)->toBe('Secret Legal Name');
    expect(AuditLog::query()->where('action', 'investigator.onboarded')->exists())->toBeTrue();
});

it('encrypts private identity at rest', function () use ($onboard) {
    $user = $this->member();
    $this->actingAs($user)->postJson('/api/v1/me/investigator', $onboard())->assertCreated();

    $raw = DB::table('investigator_private_identity')->first();
    expect($raw->legal_or_private_name)->not->toContain('Secret Legal Name');
});

it('requires the investigator agreement and a verified email', function () use ($onboard) {
    $user = $this->member();
    $this->actingAs($user)->postJson('/api/v1/me/investigator', $onboard(['accept_investigator_agreement' => false]))->assertUnprocessable();

    $unverified = User::factory()->unverified()->acceptedAgreements()->create();
    $this->actingAs($unverified)->postJson('/api/v1/me/investigator', $onboard())->assertForbidden();
});

it('cannot onboard twice', function () use ($onboard) {
    $inv = $this->investigator();
    $this->actingAs($inv->user)->postJson('/api/v1/me/investigator', $onboard())->assertStatus(409);
});

it('regenerates the slug without redirect when the public name changes', function () {
    $inv = $this->investigator(['public_name' => 'Real Person Name', 'identity_mode' => 'real_name']);
    $oldSlug = $inv->slug;

    $this->actingAs($inv->user)->patchJson('/api/v1/me/investigator', ['public_name' => 'Anonymous Owl', 'identity_mode' => 'protected_public_identity'])
        ->assertOk()->assertJsonPath('data.slug', 'anonymous-owl');

    $this->getJson("/api/v1/investigators/{$oldSlug}")->assertNotFound();
    expect(AuditLog::query()->where('action', 'investigator.identity_changed')->exists())->toBeTrue();
});

it('lets the owner read and update their own private identity but not audit its values', function () {
    $inv = $this->investigator();
    $this->actingAs($inv->user)->putJson('/api/v1/me/investigator/private-identity', ['private_phone' => '+8801711111111'])
        ->assertOk()->assertJsonPath('data.private_phone', '+8801711111111');

    $log = AuditLog::query()->where('action', 'identity.private_updated')->latest('id')->first();
    expect(json_encode($log->after_json))->not->toContain('8801711111111');
});

it('uploads an avatar as a metadata-free rendition served publicly', function () {
    $inv = $this->investigator();

    $file = UploadedFile::fake()->image('me.jpg', 300, 300);
    $this->actingAs($inv->user)->post('/api/v1/me/investigator/avatar', ['avatar' => $file])->assertOk();

    $inv->refresh();
    expect($inv->avatar_path)->toStartWith('avatars/');
    $this->get("/api/v1/investigators/{$inv->slug}/avatar")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
});
