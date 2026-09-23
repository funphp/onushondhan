<?php

use App\Models\AuditLog;
use App\Models\CommentReport;
use App\Models\ModerationAction;
use Illuminate\Support\Facades\DB;

beforeEach(fn () => $this->pub = $this->publishedInvestigation());

$act = fn (string $type, string $id, string $action, array $extra = []) => ['target_type' => $type, 'target_id' => $id, 'action' => $action, 'reason' => 'Policy review reason'] + $extra;

it('restricts an investigation to a neutral tombstone and restores it', function () use ($act) {
    $mod = $this->staff('moderator');
    $this->actingAs($mod)->postJson('/api/v1/admin/moderation/actions', $act('investigation', $this->pub->public_id, 'restrict', ['details' => ['public_note' => 'Under privacy review']]))->assertCreated();
    auth()->forgetGuards();
    $res = $this->getJson("/api/v1/investigations/{$this->pub->public_id}")->assertOk()
        ->assertJsonPath('data.notice.type', 'restricted')->assertJsonPath('data.body', null);
    expect($res->json('data.notice.message'))->toContain('not a finding about the accuracy');

    $owner = $this->pub->investigator->user;
    $this->actingAs($owner)->patchJson("/api/v1/me/investigations/{$this->pub->public_id}", ['title' => 'Try editing'])->assertForbidden();
    expect($owner->notifications()->where('data->kind', 'moderation_action')->exists())->toBeTrue();

    $this->actingAs($mod)->postJson('/api/v1/admin/moderation/actions', $act('investigation', $this->pub->public_id, 'restore'))->assertCreated();
    expect($this->pub->fresh()->status->value)->toBe('published');
    expect(AuditLog::query()->where('action', 'moderation.restrict')->exists())->toBeTrue();
});

it('enforces per-target permissions and the role hierarchy', function () use ($act) {
    $mod = $this->staff('moderator');
    $admin = $this->staff('admin');
    $member = $this->member();

    $this->actingAs($member)->postJson('/api/v1/admin/moderation/actions', $act('user', $member->public_id, 'ban_user'))->assertForbidden();
    // Moderators lack users.manage.
    $this->actingAs($mod)->postJson('/api/v1/admin/moderation/actions', $act('user', $member->public_id, 'suspend_user'))->assertForbidden();
    // Admins cannot moderate other admins or super admins.
    $otherAdmin = $this->staff('admin');
    $this->actingAs($admin)->postJson('/api/v1/admin/moderation/actions', $act('user', $otherAdmin->public_id, 'ban_user'))->assertForbidden();
    // Actions must be valid for the target.
    $this->actingAs($admin)->postJson('/api/v1/admin/moderation/actions', $act('user', $member->public_id, 'lock_comments'))->assertUnprocessable();
});

it('suspends a user and kills their sessions', function () use ($act) {
    $member = $this->member();
    DB::table('sessions')->insert(['id' => 'sess-m', 'user_id' => $member->id, 'payload' => '', 'last_activity' => time()]);
    $this->actingAs($this->staff('admin'))->postJson('/api/v1/admin/moderation/actions', $act('user', $member->public_id, 'suspend_user', ['details' => ['days' => 7]]))->assertCreated();
    expect($member->fresh()->status->value)->toBe('suspended')->and($member->fresh()->suspended_until)->not->toBeNull();
    expect(DB::table('sessions')->where('user_id', $member->id)->exists())->toBeFalse();
});

it('hides comments and resolves their reports', function () use ($act) {
    $author = $this->member();
    $cid = $this->actingAs($author)->postJson("/api/v1/investigations/{$this->pub->public_id}/comments", ['body' => 'Rude remark'])->json('data.id');
    $this->actingAs($this->member())->postJson("/api/v1/comments/{$cid}/report", ['reason' => 'harassment']);
    $this->actingAs($this->staff('moderator'))->postJson('/api/v1/admin/moderation/actions', $act('comment', $cid, 'hide'))->assertCreated();
    auth()->forgetGuards();
    $this->getJson("/api/v1/investigations/{$this->pub->public_id}/comments")->assertJsonCount(0, 'data');
    expect(CommentReport::query()->where('status', 'actioned')->count())->toBe(1);
});

it('suspending an investigator hides all their content', function () use ($act) {
    $this->actingAs($this->staff('admin'))->postJson('/api/v1/admin/moderation/actions', $act('investigator', $this->pub->investigator->public_id, 'restrict'))->assertCreated();
    auth()->forgetGuards();
    $this->getJson("/api/v1/investigations/{$this->pub->public_id}")->assertNotFound();
    $this->getJson("/api/v1/investigators/{$this->pub->investigator->slug}")->assertNotFound();
});

it('keeps moderation actions append-only', function () use ($act) {
    $this->actingAs($this->staff('moderator'))->postJson('/api/v1/admin/moderation/actions', $act('investigation', $this->pub->public_id, 'warn'));
    $record = ModerationAction::first();
    expect(fn () => $record->update(['reason' => 'edited']))->toThrow(LogicException::class);
    expect(fn () => $record->delete())->toThrow(LogicException::class);
});
