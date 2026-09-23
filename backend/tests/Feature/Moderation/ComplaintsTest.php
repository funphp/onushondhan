<?php

use App\Models\AuditLog;
use App\Models\Complaint;

beforeEach(fn () => $this->pub = $this->publishedInvestigation());

it('accepts guest complaints only with an email, and explains factual disputes without claiming verification', function () {
    $body = ['complaint_type' => 'factual_dispute', 'description' => 'The figures in paragraph two are wrong in my view.', 'investigation' => $this->pub->public_id, 'good_faith' => true];
    $this->postJson('/api/v1/complaints', $body)->assertUnprocessable()->assertJsonValidationErrors('reporter_email');
    $res = $this->postJson('/api/v1/complaints', $body + ['reporter_email' => 'Subject@Example.org'])->assertCreated();
    expect($res->json('data.message'))->toContain('does not independently verify');
    expect(Complaint::first()->reporter_email)->toBe('subject@example.org');
});

it('requires a target', function () {
    $this->actingAs($this->member())->postJson('/api/v1/complaints', ['complaint_type' => 'spam', 'description' => 'Spam spam spam spam spam spam', 'good_faith' => true])
        ->assertUnprocessable()->assertJsonPath('code', 'complaint_target_required');
});

it('lets moderators work the complaint queue and notifies without revealing the reporter', function () {
    $reporter = $this->member();
    $id = $this->actingAs($reporter)->postJson('/api/v1/complaints', ['complaint_type' => 'privacy', 'description' => 'My home address appears in the evidence section.', 'investigation' => $this->pub->public_id, 'good_faith' => true])->json('data.id');
    $mod = $this->staff('moderator');

    $this->actingAs($reporter)->getJson('/api/v1/admin/complaints')->assertForbidden();
    $this->actingAs($mod)->getJson('/api/v1/admin/complaints')->assertOk()->assertJsonPath('data.0.id', $id)->assertJsonMissingPath('data.0.reporter_email');
    $this->actingAs($mod)->patchJson("/api/v1/admin/complaints/{$id}", ['status' => 'action_requested', 'internal_notes' => 'Asked investigator to redact'])
        ->assertOk()->assertJsonPath('data.status', 'action_requested');

    $note = $this->pub->investigator->user->notifications()->where('data->kind', 'complaint_action_requested')->first();
    expect($note)->not->toBeNull()->and(json_encode($note->data))->not->toContain($reporter->username)->not->toContain($reporter->email);
    expect($reporter->notifications()->where('data->kind', 'complaint_status')->exists())->toBeTrue();
    $this->actingAs($reporter)->getJson('/api/v1/me/complaints')->assertJsonPath('data.0.status', 'action_requested')->assertJsonMissingPath('data.0.internal_notes');
    expect(AuditLog::query()->where('action', 'complaint.status_changed')->exists())->toBeTrue();
});
