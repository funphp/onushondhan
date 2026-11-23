<?php

use App\Enums\InvestigationStatus;
use App\Enums\ModerationActionType;
use App\Models\AuditLog;
use App\Models\Investigation;
use App\Services\ModerationService;

function reviewDraft(array $o = []): array
{
    return array_merge([
        'title' => 'River sand extraction beyond permitted limits',
        'body' => str_repeat('Satellite images and lease records show extraction far beyond the permitted area. ', 3),
        'category' => 'environment-climate',
    ], $o);
}

it('keeps a submitted report private until a moderator approves it', function () {
    $inv = $this->investigator();
    $id = $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', reviewDraft())->json('data.id');
    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/publish", ['confirmations' => $this->confirmations()])
        ->assertOk()->assertJsonPath('data.review.status', 'pending')->assertJsonPath('data.status', 'draft');
    auth()->forgetGuards();
    $this->getJson("/api/v1/investigations/{$id}")->assertNotFound();
    expect(collect($this->getJson('/api/v1/investigations')->json('data'))->pluck('id'))->not->toContain($id);

    // Moderator approves through the moderation service (as the admin panel does).
    $moderator = $this->staff('moderator');
    app(ModerationService::class)->apply($moderator, 'investigation', Investigation::query()->where('public_id', $id)->first(), ModerationActionType::ApprovePublication, 'Looks fine');

    $model = Investigation::query()->where('public_id', $id)->first();
    expect($model->status)->toBe(InvestigationStatus::Published)->and($model->review_status)->toBeNull()
        ->and($model->currentVersion->changed_by)->toBe($inv->user_id); // the investigator remains the author
    $this->getJson("/api/v1/investigations/{$id}")->assertOk()->assertJsonPath('data.title', 'River sand extraction beyond permitted limits');
    expect(AuditLog::query()->where('action', 'investigation.submitted_for_review')->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'moderation.approve_publication')->exists())->toBeTrue();
    expect($inv->user->notifications()->latest()->first()->data['title'] ?? '')->toBe('আপনার অনুসন্ধানটি অনুমোদিত হয়ে প্রকাশিত হয়েছে');
});

it('locks editing while under review and lets the investigator cancel', function () {
    $inv = $this->investigator();
    $id = $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', reviewDraft())->json('data.id');
    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/publish", ['confirmations' => $this->confirmations()])->assertOk();

    $this->actingAs($inv->user)->patchJson("/api/v1/me/investigations/{$id}", ['title' => 'Changed after submitting'])
        ->assertUnprocessable()->assertJsonPath('code', 'under_review');
    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/publish", ['confirmations' => $this->confirmations()])
        ->assertJsonPath('code', 'under_review');

    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/cancel-review")->assertOk()->assertJsonPath('data.review', null);
    $this->actingAs($inv->user)->patchJson("/api/v1/me/investigations/{$id}", ['title' => 'Changed after cancelling'])->assertOk();
});

it('sends a rejected report back with the moderator\'s reason', function () {
    $inv = $this->investigator();
    $id = $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', reviewDraft())->json('data.id');
    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/publish", ['confirmations' => $this->confirmations()])->assertOk();

    $moderator = $this->staff('moderator');
    $this->actingAs($moderator)->postJson('/api/v1/admin/moderation/actions', [
        'target_type' => 'investigation', 'target_id' => $id, 'action' => 'reject_publication', 'reason' => 'Please add the source for the lease records.',
    ])->assertSuccessful();

    $this->actingAs($inv->user)->getJson("/api/v1/me/investigations/{$id}")
        ->assertJsonPath('data.review.status', 'rejected')->assertJsonPath('data.review.note', 'Please add the source for the lease records.');
    // Editable again, and can be resubmitted.
    $this->actingAs($inv->user)->patchJson("/api/v1/me/investigations/{$id}", ['title' => 'River sand extraction: lease records'])->assertOk();
    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/publish", ['confirmations' => $this->confirmations()])
        ->assertOk()->assertJsonPath('data.review.status', 'pending')->assertJsonPath('data.review.note', null);
    auth()->forgetGuards();
    $this->getJson("/api/v1/investigations/{$id}")->assertNotFound();
});

it('keeps the live version public while an update waits for review', function () {
    $published = $this->publishedInvestigation();
    $owner = $published->investigator->user;
    $this->actingAs($owner)->patchJson("/api/v1/me/investigations/{$published->public_id}", ['title' => 'Updated headline pending review'])->assertOk();
    $this->actingAs($owner)->postJson("/api/v1/me/investigations/{$published->public_id}/publish", ['confirmations' => $this->confirmations(), 'change_reason' => 'New headline'])
        ->assertOk()->assertJsonPath('data.status', 'published')->assertJsonPath('data.review.status', 'pending');
    auth()->forgetGuards();
    $this->getJson("/api/v1/investigations/{$published->public_id}")->assertOk()->assertJsonPath('data.title', $published->published_title);

    $this->approvePublication($published->public_id);
    $this->getJson("/api/v1/investigations/{$published->public_id}")->assertJsonPath('data.title', 'Updated headline pending review');
});

it('only staff with moderation permission can approve', function () {
    $inv = $this->investigator();
    $id = $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', reviewDraft())->json('data.id');
    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/publish", ['confirmations' => $this->confirmations()])->assertOk();

    $this->actingAs($inv->user)->postJson('/api/v1/admin/moderation/actions', [
        'target_type' => 'investigation', 'target_id' => $id, 'action' => 'approve_publication', 'reason' => 'Approving my own report',
    ])->assertForbidden();
    expect(Investigation::query()->where('public_id', $id)->value('review_status'))->toBe('pending');
});

it('publishes immediately when review is switched off', function () {
    config(['onushondhan.publication_review' => false]);
    $inv = $this->investigator();
    $id = $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', reviewDraft())->json('data.id');
    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/publish", ['confirmations' => $this->confirmations()])
        ->assertOk()->assertJsonPath('data.status', 'published')->assertJsonPath('data.review', null);
});
