<?php

use App\Models\Investigation;
use App\Notifications\PlatformNotification;

beforeEach(fn () => $this->withHeaders(['X-Locale' => '']));

it('answers in Bangla by default, including validation and domain errors', function () {
    $res = $this->postJson('/api/v1/auth/register', [])->assertUnprocessable()->assertHeader('Content-Language', 'bn');
    expect($res->json('message'))->toBe('জমা দেওয়া তথ্য সঠিক নয়।')
        ->and($res->json('errors.email.0'))->toBe('ইমেইল আবশ্যক।');

    $this->member(['email' => 'a@example.org']);
    $this->postJson('/api/v1/auth/login', ['email' => 'a@example.org', 'password' => 'Wrong-pass-1'])
        ->assertJsonPath('message', 'ইমেইল বা পাসওয়ার্ড আমাদের রেকর্ডের সঙ্গে মিলছে না।');
    $this->getJson('/api/v1/nope')->assertNotFound()->assertJsonPath('message', 'অনুরোধ করা বিষয়টি পাওয়া যায়নি।');
});

it('honours an explicit X-Locale: en', function () {
    $this->withHeaders(['X-Locale' => 'en'])->postJson('/api/v1/auth/register', [])
        ->assertHeader('Content-Language', 'en')->assertJsonPath('message', 'The submitted data is invalid.');
});

it('returns Bangla public texts and leaderboard labels', function () {
    $pub = $this->publishedInvestigation();
    expect($this->getJson("/api/v1/investigations/{$pub->public_id}")->json('data.disclaimer'))->toStartWith('এই প্রতিবেদনটি একজন স্বাধীন অনুসন্ধানকারী');
    $this->getJson('/api/v1/leaderboards/support')->assertJsonPath('label', 'সর্বাধিক সমর্থিত অনুসন্ধানকারী');
});

it('publishes Bangla investigations with Unicode slugs and finds them by Bangla search', function () {
    $inv = $this->investigator();
    $id = $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', [
        'title' => 'ধলেশ্বরী নদীতে ট্যানারির বর্জ্য',
        'summary' => 'ছয় মাস ধরে সংগৃহীত পানির নমুনার বিশ্লেষণ ও স্থানীয়দের সাক্ষ্য।',
        'body' => str_repeat('সাভারের হেমায়েতপুরে ট্যানারি শিল্পনগরীর বর্জ্য সরাসরি নদীতে পড়ছে। ', 4),
        'category' => 'environment-climate',
        'tags' => ['নদী দূষণ', 'ট্যানারি'],
    ])->assertCreated()->json('data.id');

    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/publish", ['confirmations' => $this->confirmations()])
        ->assertOk()->assertJsonPath('data.slug', 'ধলেশ্বরী-নদীতে-ট্যানারির-বর্জ্য');
    auth()->forgetGuards();
    $this->getJson("/api/v1/investigations/{$id}")->assertOk()->assertJsonPath('data.tags.0.slug', 'নদী-দূষণ');
    $this->getJson('/api/v1/tags/'.rawurlencode('নদী-দূষণ'))->assertOk();
    expect(Investigation::query()->where('public_id', $id)->value('slug'))->toBe('ধলেশ্বরী-নদীতে-ট্যানারির-বর্জ্য');
});

it('renders notifications in the user\'s language', function () {
    $user = $this->member();
    $user->notify(new PlatformNotification('investigation_milestone', 'Your investigation reached :views reads', [], null, titleParams: ['views' => 100]));
    expect($user->notifications()->first()->data['title'])->toBe('আপনার অনুসন্ধান 100 বার পড়া হয়েছে');

    // The API re-renders the stored title in the reader's current interface language.
    $this->actingAs($user)->withHeaders(['X-Locale' => 'en'])->getJson('/api/v1/me/notifications')
        ->assertJsonPath('data.0.data.title', 'Your investigation reached 100 reads')
        ->assertJsonMissingPath('data.0.data.title_key');
    $this->actingAs($user)->withHeaders(['X-Locale' => 'bn'])->getJson('/api/v1/me/notifications')
        ->assertJsonPath('data.0.data.title', 'আপনার অনুসন্ধান 100 বার পড়া হয়েছে');
});

it('localises category labels and stores the user\'s interface language', function () {
    $this->getJson('/api/v1/categories')->assertJsonPath('data.0.name', 'শাসন ও জনপ্রশাসন');
    $this->withHeaders(['X-Locale' => 'en'])->getJson('/api/v1/categories')->assertJsonPath('data.0.name', 'Governance & Public Administration');

    $user = $this->member();
    expect($user->preferredLocale())->toBe('bn');
    $this->actingAs($user)->patchJson('/api/v1/me/profile', ['locale' => 'en'])->assertOk()->assertJsonPath('data.locale', 'en');
    $this->actingAs($user)->patchJson('/api/v1/me/profile', ['locale' => 'fr'])->assertUnprocessable();
    expect($user->fresh()->preferredLocale())->toBe('en');
});

it('remembers the interface language chosen at registration', function () {
    $payload = ['name' => 'Rahim Uddin', 'email' => 'rahim@example.org', 'password' => 'Valid-pass-123', 'password_confirmation' => 'Valid-pass-123', 'accept_agreements' => true];
    $this->postJson('/api/v1/auth/register', $payload + ['locale' => 'en'])->assertCreated()->assertJsonPath('data.locale', 'en');

    auth()->forgetGuards();
    $this->postJson('/api/v1/auth/register', ['email' => 'karim@example.org'] + $payload)->assertCreated()->assertJsonPath('data.locale', 'bn');
});
