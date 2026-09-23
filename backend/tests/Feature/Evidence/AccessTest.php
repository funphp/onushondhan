<?php

use App\Models\AuditLog;
use App\Models\Evidence;
use App\Models\EvidenceAccessLog;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->owner = $this->investigator();
    $this->published = $this->publishedInvestigation($this->owner);
    $upload = fn (string $vis, string $name) => Evidence::query()->where('public_id',
        $this->actingAs($this->owner->user)->post("/api/v1/me/investigations/{$this->published->public_id}/evidence", [
            'file' => UploadedFile::fake()->createWithContent($name, "%PDF-1.4\n% {$name}\n%%EOF"), 'visibility' => $vis,
        ])->json('data.id'))->first();
    $this->public = $upload('public', 'public.pdf');
    $this->private = $upload('private', 'private.pdf');
    $this->restricted = $upload('restricted', 'restricted.pdf');
    auth()->forgetGuards();
});

it('serves public evidence of a published investigation to guests with safe headers and logs access', function () {
    $res = $this->get("/api/v1/evidence/{$this->public->public_id}/download")->assertOk();
    expect($res->headers->get('Content-Disposition'))->toStartWith('attachment')->toContain('evidence-')
        ->and($res->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($res->headers->get('Content-Security-Policy'))->toContain('sandbox');
    expect(EvidenceAccessLog::query()->where('evidence_id', $this->public->id)->where('success', true)->where('action', 'download')->exists())->toBeTrue();
});

it('hides private evidence from guests, members and other investigators (404) and logs the denial', function () {
    $this->get("/api/v1/evidence/{$this->private->public_id}/download")->assertNotFound();
    $this->actingAs($this->member())->get("/api/v1/evidence/{$this->private->public_id}/download")->assertNotFound();
    $this->actingAs($this->investigator()->user)->get("/api/v1/evidence/{$this->private->public_id}/download")->assertNotFound();
    $this->actingAs($this->staff('moderator'))->get("/api/v1/evidence/{$this->private->public_id}/download")->assertNotFound();
    expect(EvidenceAccessLog::query()->where('evidence_id', $this->private->id)->where('success', false)->count())->toBe(4);
});

it('lets the owner access private evidence and moderators access restricted evidence (audited)', function () {
    $this->actingAs($this->owner->user)->get("/api/v1/evidence/{$this->private->public_id}/download")->assertOk();
    auth()->forgetGuards();
    $this->get("/api/v1/evidence/{$this->restricted->public_id}/download")->assertNotFound();
    $mod = $this->staff('moderator');
    $this->actingAs($mod)->get("/api/v1/evidence/{$this->restricted->public_id}/download")->assertOk();
    expect(AuditLog::query()->where('action', 'evidence.accessed')->where('actor_user_id', $mod->id)->exists())->toBeTrue();
});

it('excludes non-public evidence from the public investigation payload', function () {
    $ids = collect($this->getJson("/api/v1/investigations/{$this->published->public_id}")->json('data.evidence'))->pluck('id');
    expect($ids->all())->toBe([$this->public->public_id]);
});

it('hides public evidence once the investigation is withdrawn', function () {
    $this->actingAs($this->owner->user)->postJson("/api/v1/me/investigations/{$this->published->public_id}/withdraw", ['reason' => 'Withdrawn for review']);
    auth()->forgetGuards();
    $this->get("/api/v1/evidence/{$this->public->public_id}/download")->assertNotFound();
});

it('issues short-lived signed links bound to the user and rejects tampering or expiry', function () {
    $link = $this->actingAs($this->owner->user)->postJson("/api/v1/evidence/{$this->private->public_id}/download-link")->assertOk()->json('data.download_url');
    auth()->forgetGuards();
    $this->get($link)->assertOk();
    $this->get(str_replace('uid=', 'uid=x', $link))->assertForbidden();
    $this->travel(11)->minutes();
    $this->get($link)->assertForbidden();
});

it('never exposes storage paths or original filenames publicly', function () {
    $json = json_encode($this->getJson("/api/v1/evidence/{$this->public->public_id}")->assertOk()->json());
    expect($json)->not->toContain($this->public->storage_path)->not->toContain('public.pdf')->not->toContain('storage_path');
});
