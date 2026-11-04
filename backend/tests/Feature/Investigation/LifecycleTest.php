<?php

use App\Models\AuditLog;
use App\Models\Investigation;
use App\Models\PublishConfirmation;

function draftPayload(array $o = []): array
{
    return array_merge([
        'title' => 'Procurement irregularities in district roads',
        'summary' => 'A detailed look at how road contracts were awarded in 2025.',
        'body' => str_repeat('The records show several contracts awarded without competition. ', 5),
        'category' => 'governance-public-administration',
        'tags' => ['procurement', 'roads'],
        'findings' => [['title' => 'Single bidder', 'body' => 'Nine of ten contracts had one bidder.']],
        'timeline' => [['event_date' => '2025-01-10', 'title' => 'Tender published']],
        'sources' => [
            ['title' => 'Tender notice', 'url' => 'https://example.org/tender', 'is_public' => true],
            ['title' => 'Whistleblower notes', 'is_public' => false],
        ],
        'locations' => [['country' => 'BD', 'district' => 'Khulna']],
        'recommendations' => ['Publish all bids.'],
    ], $o);
}

it('creates a draft and autosaves with optimistic revision checks', function () {
    $inv = $this->investigator();
    $res = $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', draftPayload())->assertCreated();
    $id = $res->json('data.id');
    expect($id)->toMatch('/^INV-\d{4}-[0-9A-Z]{8}$/');
    expect($res->json('data.status'))->toBe('draft')->and($res->json('data.revision'))->toBe(1);

    $this->actingAs($inv->user)->patchJson("/api/v1/me/investigations/{$id}", ['revision' => 1, 'body' => 'Autosaved **report** text.'])
        ->assertOk()->assertJsonPath('data.revision', 2)->assertJsonPath('data.summary', 'Autosaved report text.');
    $this->actingAs($inv->user)->patchJson("/api/v1/me/investigations/{$id}", ['revision' => 1, 'body' => 'Stale write'])
        ->assertStatus(409)->assertJsonPath('code', 'revision_conflict');
});

it('rejects javascript: and other non-http source urls', function () {
    $inv = $this->investigator();
    $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', draftPayload(['sources' => [['title' => 'x', 'url' => 'javascript:alert(1)']]]))
        ->assertUnprocessable()->assertJsonValidationErrors('sources.0.url');
});

it('drafts are never publicly visible', function () {
    $inv = $this->investigator();
    $id = $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', draftPayload())->json('data.id');
    auth()->forgetGuards();

    $this->getJson("/api/v1/investigations/{$id}")->assertNotFound();
    expect(collect($this->getJson('/api/v1/investigations')->json('data'))->pluck('id'))->not->toContain($id);
});

it('previews the working copy exactly like the public page, for the owner only', function () {
    $inv = $this->investigator();
    $id = $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', draftPayload())->json('data.id');

    $this->actingAs($inv->user)->getJson("/api/v1/me/investigations/{$id}/preview")
        ->assertOk()->assertJsonPath('data.is_preview', true)->assertJsonPath('data.robots.index', false)
        ->assertJsonCount(1, 'data.sources'); // private source excluded

    $other = $this->investigator();
    $this->actingAs($other->user)->getJson("/api/v1/me/investigations/{$id}/preview")->assertNotFound();
});

it('requires every responsibility confirmation to publish and records it', function () {
    $inv = $this->investigator();
    $id = $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', draftPayload())->json('data.id');
    $partial = $this->confirmations();
    $partial['no_platform_verification'] = false;

    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/publish", ['confirmations' => $partial])
        ->assertUnprocessable()->assertJsonPath('code', 'confirmation_required');

    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/publish", ['confirmations' => $this->confirmations()])
        ->assertOk()->assertJsonPath('data.status', 'published')->assertJsonPath('data.current_version', 1);

    $model = Investigation::query()->where('public_id', $id)->first();
    $confirmation = PublishConfirmation::query()->where('investigation_id', $model->id)->first();
    expect($confirmation->version_no)->toBe(1)->and($confirmation->terms_version_id)->not->toBeNull()
        ->and($confirmation->statements_json)->toHaveCount(4);
    expect(AuditLog::query()->where('action', 'investigation.published')->where('entity_id', $model->id)->exists())->toBeTrue();
});

it('refuses to publish incomplete drafts', function () {
    $inv = $this->investigator();
    $id = $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', ['title' => 'Too short'])->json('data.id');
    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/publish", ['confirmations' => $this->confirmations()])
        ->assertUnprocessable()->assertJsonPath('code', 'not_ready')->assertJsonValidationErrors(['body', 'category']);
});

it('keeps working-copy edits private until an update is published with a reason', function () {
    $published = $this->publishedInvestigation();
    $owner = $published->investigator->user;
    $originalTitle = $published->published_title;

    $this->actingAs($owner)->patchJson("/api/v1/me/investigations/{$published->public_id}", ['title' => 'Corrected headline for this report'])
        ->assertOk()->assertJsonPath('data.has_unpublished_changes', true);
    auth()->forgetGuards();
    $this->getJson("/api/v1/investigations/{$published->public_id}")->assertJsonPath('data.title', $originalTitle);

    $this->actingAs($owner)->postJson("/api/v1/me/investigations/{$published->public_id}/publish", ['confirmations' => $this->confirmations()])
        ->assertUnprocessable()->assertJsonPath('code', 'change_reason_required');
    $this->actingAs($owner)->postJson("/api/v1/me/investigations/{$published->public_id}/publish", ['confirmations' => $this->confirmations(), 'change_reason' => 'Corrected the headline'])
        ->assertOk()->assertJsonPath('data.current_version', 2);
    auth()->forgetGuards();

    $this->getJson("/api/v1/investigations/{$published->public_id}")
        ->assertJsonPath('data.title', 'Corrected headline for this report')->assertJsonPath('data.version', 2);
    $this->getJson("/api/v1/investigations/{$published->public_id}/versions")->assertJsonCount(2, 'data');

    $diff = $this->actingAs($owner)->getJson("/api/v1/me/investigations/{$published->public_id}/diff?from=1&to=2")->assertOk();
    expect($diff->json('data.fields.title.changed'))->toBeTrue()->and($diff->json('data.fields.body.changed'))->toBeFalse();
});

it('withdraws to a public tombstone without content', function () {
    $published = $this->publishedInvestigation();
    $this->actingAs($published->investigator->user)->postJson("/api/v1/me/investigations/{$published->public_id}/withdraw", ['reason' => 'Source retracted statement'])
        ->assertOk()->assertJsonPath('data.status', 'withdrawn');
    auth()->forgetGuards();

    $this->getJson("/api/v1/investigations/{$published->public_id}")->assertOk()
        ->assertJsonPath('data.notice.type', 'withdrawn')->assertJsonPath('data.body', null)
        ->assertJsonPath('data.evidence', [])->assertJsonPath('data.robots.index', false);
    expect(collect($this->getJson('/api/v1/investigations')->json('data'))->pluck('id'))->not->toContain($published->public_id);
});

it('archives with comments locked and keeps it readable', function () {
    $published = $this->publishedInvestigation();
    $this->actingAs($published->investigator->user)->postJson("/api/v1/me/investigations/{$published->public_id}/archive")->assertOk();
    auth()->forgetGuards();
    $this->getJson("/api/v1/investigations/{$published->public_id}")->assertOk()
        ->assertJsonPath('data.notice.type', 'archived')->assertJsonPath('data.comments_locked', true);
    expect($this->getJson("/api/v1/investigations/{$published->public_id}")->json('data.body'))->not->toBeNull();
});

it('only deletes never-published drafts', function () {
    $inv = $this->investigator();
    $draftId = $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', draftPayload())->json('data.id');
    $this->actingAs($inv->user)->deleteJson("/api/v1/me/investigations/{$draftId}")->assertOk();

    $published = $this->publishedInvestigation($inv);
    $this->actingAs($inv->user)->deleteJson("/api/v1/me/investigations/{$published->public_id}")->assertUnprocessable();
});

it('unlisted investigations are readable by URL but not listed or indexable', function () {
    $published = $this->publishedInvestigation(null, ['visibility' => 'unlisted']);
    $this->getJson("/api/v1/investigations/{$published->public_id}")->assertOk()->assertJsonPath('data.robots.index', false);
    expect(collect($this->getJson('/api/v1/investigations')->json('data'))->pluck('id'))->not->toContain($published->public_id);
});

it('private investigations are not reachable publicly even when published', function () {
    $published = $this->publishedInvestigation(null, ['visibility' => 'private']);
    $this->getJson("/api/v1/investigations/{$published->public_id}")->assertNotFound();
});
