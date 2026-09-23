<?php

use App\Models\Comment;
use App\Models\Investigation;

beforeEach(function () {
    $this->inv = $this->publishedInvestigation();
    $this->url = "/api/v1/investigations/{$this->inv->public_id}/comments";
});

it('creates, lists and threads comments and notifies the investigator', function () {
    $a = $this->member();
    $b = $this->member();
    $root = $this->actingAs($a)->postJson($this->url, ['body' => 'Great work on this.'])->assertCreated()->json('data.id');
    $this->actingAs($b)->postJson($this->url, ['body' => 'Agreed, thanks.', 'parent_id' => $root])->assertCreated();
    auth()->forgetGuards();

    $list = $this->getJson($this->url)->assertOk();
    expect($list->json('data.0.id'))->toBe($root)->and($list->json('data.0.replies.0.body'))->toBe('Agreed, thanks.')
        ->and($list->json('data.0.author.name'))->toBe($a->username);
    expect(json_encode($list->json()))->not->toContain($a->email)->not->toContain($a->name);
    expect(Investigation::find($this->inv->id)->comments_count)->toBe(2);
    expect($this->inv->investigator->user->notifications()->where('data->kind', 'comment_on_investigation')->count())->toBe(2);
    expect($a->notifications()->where('data->kind', 'comment_reply')->count())->toBe(1);
});

it('strips HTML from comment bodies (stored XSS)', function () {
    $id = $this->actingAs($this->member())->postJson($this->url, ['body' => '<img src=x onerror=alert(1)>Hello <b>there</b>'])->json('data.id');
    expect(Comment::query()->where('public_id', $id)->value('body'))->toBe('Hello there');
});

it('lets authors edit/delete their own comments only', function () {
    $author = $this->member();
    $id = $this->actingAs($author)->postJson($this->url, ['body' => 'Original text'])->json('data.id');
    $this->actingAs($this->member())->patchJson("/api/v1/comments/{$id}", ['body' => 'Hijacked'])->assertForbidden();
    $this->actingAs($this->member())->deleteJson("/api/v1/comments/{$id}")->assertNotFound();
    $this->actingAs($author)->patchJson("/api/v1/comments/{$id}", ['body' => 'Edited text'])->assertOk()->assertJsonPath('data.body', 'Edited text');
    $this->actingAs($author)->deleteJson("/api/v1/comments/{$id}")->assertOk();
    expect(Comment::withTrashed()->where('public_id', $id)->first()->trashed())->toBeTrue();
});

it('allows one vote per user, not on own comments', function () {
    $author = $this->member();
    $id = $this->actingAs($author)->postJson($this->url, ['body' => 'Vote on me'])->json('data.id');
    $this->actingAs($author)->putJson("/api/v1/comments/{$id}/vote", ['value' => 1])->assertForbidden();
    $voter = $this->member();
    $this->actingAs($voter)->putJson("/api/v1/comments/{$id}/vote", ['value' => 1])->assertJsonPath('data.score', 1);
    $this->actingAs($voter)->putJson("/api/v1/comments/{$id}/vote", ['value' => 1])->assertJsonPath('data.score', 1);
    $this->actingAs($voter)->putJson("/api/v1/comments/{$id}/vote", ['value' => -1])->assertJsonPath('data.score', -1);
    $this->actingAs($voter)->deleteJson("/api/v1/comments/{$id}/vote")->assertJsonPath('data.score', 0);
});

it('auto-hides a comment pending review after enough distinct reports', function () {
    $id = $this->actingAs($this->member())->postJson($this->url, ['body' => 'Spammy spam'])->json('data.id');
    foreach (range(1, 5) as $i) {
        $this->actingAs($this->member())->postJson("/api/v1/comments/{$id}/report", ['reason' => 'spam'])->assertCreated();
    }
    expect(Comment::query()->where('public_id', $id)->first()->status->value)->toBe('pending');
});

it('refuses comments when locked or not published', function () {
    $this->inv->forceFill(['comments_locked' => true])->save();
    $this->actingAs($this->member())->postJson($this->url, ['body' => 'Hello'])->assertUnprocessable()->assertJsonPath('code', 'comments_locked');
});

it('shows the investigator\'s own comments under their public identity, not their account', function () {
    $owner = $this->inv->investigator->user;
    $this->actingAs($owner)->postJson($this->url, ['body' => 'Author reply here'])->assertCreated()
        ->assertJsonPath('data.author.role', 'investigator')->assertJsonPath('data.author.name', $this->inv->investigator->public_name);
});

it('rate-limits comment flooding', function () {
    $u = $this->member();
    foreach (range(1, 6) as $i) {
        $this->actingAs($u)->postJson($this->url, ['body' => "Comment {$i}"])->assertCreated();
    }
    $this->actingAs($u)->postJson($this->url, ['body' => 'One too many'])->assertStatus(429);
});
