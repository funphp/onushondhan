<?php

use App\Models\TermsVersion;

function publishNewTermsVersion(): TermsVersion
{
    $v = new TermsVersion(['agreement_type' => 'terms_of_use', 'version' => '2.0', 'title' => 'Terms v2', 'content' => 'Updated terms', 'effective_at' => now()->subMinute()]);
    $v->status = 'published';
    $v->published_at = now();
    $v->save();

    return $v;
}

it('lists current agreements publicly with the legal review notice', function () {
    $res = $this->getJson('/api/v1/agreements')->assertOk();
    expect(collect($res->json('data'))->pluck('agreement_type')->all())
        ->toContain('terms_of_use', 'privacy_policy', 'content_policy', 'investigator_responsibility');
    expect($res->json('data.0.content'))->toContain('REQUIRES LEGAL REVIEW');
});

it('requires re-acceptance when a new version is published and blocks community writes until then', function () {
    $user = $this->member();
    $inv = $this->publishedInvestigation();
    $v2 = publishNewTermsVersion();

    $this->actingAs($user)->getJson('/api/v1/auth/me')->assertJsonPath('data.pending_agreements.0.id', $v2->id);
    $this->actingAs($user)->postJson("/api/v1/investigations/{$inv->public_id}/comments", ['body' => 'A comment'])
        ->assertForbidden()->assertJsonPath('code', 'agreements_required');

    $this->actingAs($user)->postJson('/api/v1/agreements/accept', ['terms_version_ids' => [$v2->id]])
        ->assertOk()->assertJsonPath('data.pending_agreements', []);
    $this->actingAs($user)->postJson("/api/v1/investigations/{$inv->public_id}/comments", ['body' => 'A comment'])->assertCreated();
});

it('refuses acceptance of a superseded version', function () {
    $user = $this->member();
    $old = TermsVersion::currentFor('terms_of_use');
    publishNewTermsVersion();

    $this->actingAs($user)->postJson('/api/v1/agreements/accept', ['terms_version_ids' => [$old->id]])
        ->assertStatus(409)->assertJsonPath('code', 'agreement_outdated');
});
