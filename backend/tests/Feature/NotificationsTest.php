<?php

use App\Models\TermsVersion;
use App\Notifications\PlatformNotification;

it('notifies the investigator when a read milestone is reached', function () {
    $pub = $this->publishedInvestigation();
    $pub->forceFill(['views_count' => 99])->save();
    $this->postJson("/api/v1/investigations/{$pub->public_id}/view")->assertJsonPath('counted', true);
    $n = $pub->investigator->user->notifications()->where('data->kind', 'investigation_milestone')->first();
    expect($n)->not->toBeNull()->and($n->data['views'])->toBe(100);
});

it('notifies active users when an agreement version is published', function () {
    $member = $this->member();
    $inv = $this->investigator();
    $draft = new TermsVersion(['agreement_type' => 'investigator_responsibility', 'version' => '9.9', 'title' => 'Responsibility v9.9', 'content' => 'x']);
    $draft->save();
    expect($inv->user->notifications()->count())->toBe(0);

    $draft->status = 'published';
    $draft->published_at = now();
    $draft->save();

    expect($inv->user->notifications()->where('data->kind', 'agreement_updated')->exists())->toBeTrue();
    // Investigator-only agreement: ordinary members are not bothered.
    expect($member->notifications()->where('data->kind', 'agreement_updated')->exists())->toBeFalse();
});

it('lists only the caller\'s notifications and marks all read', function () {
    $user = $this->member();
    $user->notify(new PlatformNotification('x', 'Hello'));
    $this->actingAs($user)->getJson('/api/v1/me/notifications')->assertJsonCount(1, 'data')->assertJsonPath('meta.unread', 1);
    $this->actingAs($user)->postJson('/api/v1/me/notifications/read-all')->assertOk();
    $this->actingAs($user)->getJson('/api/v1/me/notifications?unread=1')->assertJsonCount(0, 'data');
});
