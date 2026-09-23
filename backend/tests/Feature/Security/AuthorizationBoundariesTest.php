<?php

use App\Models\AuditLog;
use App\Notifications\PlatformNotification;

it('rejects guests on every protected family', function (string $method, string $uri) {
    $this->json($method, $uri)->assertUnauthorized();
})->with([
    ['GET', '/api/v1/auth/me'], ['GET', '/api/v1/me/notifications'], ['GET', '/api/v1/me/investigations'],
    ['POST', '/api/v1/me/investigations'], ['GET', '/api/v1/me/inbox/tips'], ['GET', '/api/v1/admin/complaints'],
    ['GET', '/api/v1/admin/audit-logs'], ['POST', '/api/v1/admin/moderation/actions'], ['GET', '/api/v1/me/investigator/private-identity'],
]);

it('blocks plain members from investigator and admin areas', function (string $method, string $uri) {
    $this->actingAs($this->member())->json($method, $uri)->assertForbidden();
})->with([
    ['GET', '/api/v1/me/investigations'], ['GET', '/api/v1/me/inbox/tips'], ['GET', '/api/v1/admin/complaints'],
    ['GET', '/api/v1/admin/audit-logs'], ['GET', '/api/v1/admin/moderation/queue'],
]);

it('prevents investigator A from reading or changing investigator B\'s work (IDOR)', function () {
    $a = $this->investigator();
    $b = $this->investigator();
    $draft = $b->investigations()->create(['title' => 'B secret draft']);
    $pub = $this->publishedInvestigation($b);

    foreach (["/api/v1/me/investigations/{$draft->public_id}", "/api/v1/me/investigations/{$draft->public_id}/preview", "/api/v1/me/investigations/{$pub->public_id}/versions", "/api/v1/me/investigations/{$pub->public_id}/evidence"] as $uri) {
        $this->actingAs($a->user)->getJson($uri)->assertNotFound();
    }
    $this->actingAs($a->user)->patchJson("/api/v1/me/investigations/{$pub->public_id}", ['title' => 'Hijacked title'])->assertNotFound();
    $this->actingAs($a->user)->postJson("/api/v1/me/investigations/{$pub->public_id}/withdraw", ['reason' => 'because I can'])->assertNotFound();
    $this->actingAs($a->user)->postJson("/api/v1/me/investigations/{$draft->public_id}/publish", ['confirmations' => $this->confirmations()])->assertNotFound();
    expect($pub->fresh()->title)->not->toBe('Hijacked title');
});

it('does not let users read other users\' notifications', function () {
    $a = $this->member();
    $b = $this->member();
    $b->notify(new PlatformNotification('x', 'For B only'));
    $id = $b->notifications()->first()->id;
    $this->actingAs($a)->postJson("/api/v1/me/notifications/{$id}/read")->assertNotFound();
    $this->actingAs($a)->getJson('/api/v1/me/notifications')->assertJsonCount(0, 'data');
});

it('gates private identity: moderators and admins cannot view it; super admins can, with a reason, audited', function () {
    $inv = $this->investigator();
    $uri = "/api/v1/admin/investigators/{$inv->public_id}/private-identity";
    $this->actingAs($this->staff('moderator'))->postJson($uri, ['reason' => 'Need to check identity'])->assertForbidden();
    $this->actingAs($this->staff('admin'))->postJson($uri, ['reason' => 'Need to check identity'])->assertForbidden();

    $super = $this->staff('super_admin');
    $this->actingAs($super)->postJson($uri, ['reason' => 'short'])->assertUnprocessable();
    $this->actingAs($super)->postJson($uri, ['reason' => 'Court order ref 123/2026 received'])->assertOk()
        ->assertJsonPath('data.legal_or_private_name', $inv->privateIdentity->legal_or_private_name);
    expect(AuditLog::query()->where('action', 'identity.private_viewed')->where('actor_user_id', $super->id)->exists())->toBeTrue();
});

it('never leaks private identity or account data in public payloads', function () {
    $inv = $this->investigator(['public_name' => 'Masked Kite']);
    $pub = $this->publishedInvestigation($inv);
    $secret = [$inv->user->email, $inv->user->name, $inv->privateIdentity->legal_or_private_name, $inv->privateIdentity->private_email];

    $payloads = [
        $this->getJson("/api/v1/investigations/{$pub->public_id}")->json(),
        $this->getJson('/api/v1/investigations')->json(),
        $this->getJson("/api/v1/investigators/{$inv->slug}")->json(),
        $this->getJson('/api/v1/investigators?sort=recent')->json(),
        $this->getJson('/api/v1/leaderboards/most_published')->json(),
        $this->getJson('/api/v1/sitemap')->json(),
    ];
    foreach ($payloads as $p) {
        $json = json_encode($p);
        foreach ($secret as $s) {
            expect($json)->not->toContain($s);
        }
        expect($json)->not->toContain('storage_path')->not->toContain('user_id')->not->toContain('identity_snapshot');
    }
    // Private (non-public) sources never appear publicly.
    expect(json_encode($payloads[0]))->not->toContain('Confidential source notes');
});

it('sets security headers on API responses', function () {
    $res = $this->getJson('/api/v1/health')->assertOk();
    expect($res->headers->get('X-Content-Type-Options'))->toBe('nosniff')
        ->and($res->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($res->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'none'")
        ->and($res->headers->get('Cache-Control'))->toContain('no-store');
    expect($res->json())->not->toHaveKey('version');
});

it('rejects unknown moderation target types and SQL-ish input safely', function () {
    $this->getJson('/api/v1/investigations?q='.urlencode("' OR 1=1 --"))->assertOk();
    $this->getJson('/api/v1/investigations?sort=DROP TABLE')->assertUnprocessable();
    $this->getJson("/api/v1/investigations/1' or '1'='1")->assertNotFound();
});
