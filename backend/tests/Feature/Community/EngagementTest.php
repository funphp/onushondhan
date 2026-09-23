<?php

use App\Models\AnalyticsEvent;
use App\Models\AuditLog;
use App\Models\InvestigatorSupport;

it('supports an investigator once; toggling never inflates the count', function () {
    $inv = $this->investigator();
    $u = $this->member();
    $url = "/api/v1/investigators/{$inv->slug}/support";

    $this->actingAs($u)->putJson($url)->assertOk()->assertJsonPath('data.counts.supporters', 1);
    $this->actingAs($u)->putJson($url)->assertOk()->assertJsonPath('data.counts.supporters', 1);
    $this->actingAs($u)->deleteJson($url)->assertOk()->assertJsonPath('data.counts.supporters', 0);
    $this->actingAs($u)->putJson($url)->assertStatus(429)->assertJsonPath('code', 'support_cooldown');
    $this->travel(61)->minutes();
    $this->actingAs($u)->putJson($url)->assertOk()->assertJsonPath('data.counts.supporters', 1);

    expect(InvestigatorSupport::query()->where('user_id', $u->id)->count())->toBe(1);
    expect(AuditLog::query()->whereIn('action', ['support.created', 'support.revoked'])->count())->toBe(3);
});

it('prevents self-support and self-follow', function () {
    $inv = $this->investigator();
    $this->actingAs($inv->user)->putJson("/api/v1/investigators/{$inv->slug}/support")->assertUnprocessable()->assertJsonPath('code', 'self_support');
    $this->actingAs($inv->user)->putJson("/api/v1/investigators/{$inv->slug}/follow")->assertUnprocessable();
});

it('follows, bookmarks and reacts idempotently and lists them for the user', function () {
    $pub = $this->publishedInvestigation();
    $u = $this->member();
    $this->actingAs($u)->putJson("/api/v1/investigators/{$pub->investigator->slug}/follow")->assertJsonPath('data.counts.followers', 1);
    $this->actingAs($u)->putJson("/api/v1/investigators/{$pub->investigator->slug}/follow")->assertJsonPath('data.counts.followers', 1);
    $this->actingAs($u)->putJson("/api/v1/investigations/{$pub->public_id}/bookmark")->assertJsonPath('data.counts.bookmarks', 1);
    $this->actingAs($u)->putJson("/api/v1/investigations/{$pub->public_id}/bookmark")->assertJsonPath('data.counts.bookmarks', 1);
    $this->actingAs($u)->putJson("/api/v1/investigations/{$pub->public_id}/reaction", ['type' => 'useful'])->assertJsonPath('data.counts.reactions', 1);
    $this->actingAs($u)->putJson("/api/v1/investigations/{$pub->public_id}/reaction", ['type' => 'important'])->assertJsonPath('data.counts.reactions', 1);

    $this->actingAs($u)->getJson('/api/v1/me/bookmarks')->assertJsonPath('data.0.id', $pub->public_id);
    $this->actingAs($u)->getJson('/api/v1/me/following')->assertJsonPath('data.0.slug', $pub->investigator->slug);
    $this->actingAs($u)->getJson("/api/v1/investigations/{$pub->public_id}")
        ->assertJsonPath('data.viewer.bookmarked', true)->assertJsonPath('data.viewer.reaction', 'important')->assertJsonPath('data.viewer.following', true);
});

it('publishes neutral leaderboards that never claim accuracy', function () {
    $inv = $this->investigator();
    $this->actingAs($this->member())->putJson("/api/v1/investigators/{$inv->slug}/support");
    auth()->forgetGuards();

    $res = $this->getJson('/api/v1/leaderboards/support')->assertOk()
        ->assertJsonPath('label', 'Top Supported Investigators')->assertJsonPath('data.0.investigator.slug', $inv->slug);
    expect(strtolower(json_encode($res->json('boards'))))->not->toContain('accurate')->not->toContain('best');
    expect($res->json('note'))->toContain('not a measure of accuracy');
    $this->getJson('/api/v1/leaderboards/rising')->assertOk();
    $this->getJson('/api/v1/leaderboards/most_accurate')->assertNotFound();
});

it('counts views once per visitor per window', function () {
    $pub = $this->publishedInvestigation();
    $this->postJson("/api/v1/investigations/{$pub->public_id}/view")->assertJsonPath('counted', true);
    $this->postJson("/api/v1/investigations/{$pub->public_id}/view")->assertJsonPath('counted', false);
    expect($pub->fresh()->views_count)->toBe(1);
    expect(AnalyticsEvent::query()->where('event_name', 'investigation_view')->first()->anonymous_fingerprint)->toHaveLength(64);
});

it('records shares by platform', function () {
    $pub = $this->publishedInvestigation();
    $this->postJson("/api/v1/investigations/{$pub->public_id}/share", ['platform' => 'whatsapp'])->assertOk();
    $this->postJson("/api/v1/investigations/{$pub->public_id}/share", ['platform' => 'myspace'])->assertUnprocessable();
    expect($pub->fresh()->shares_count)->toBe(1);
});
