<?php

use App\Contracts\FileVault;
use App\Models\Investigation;
use App\Services\InvestigationService;
use Illuminate\Http\UploadedFile;

function coverDraft(array $o = []): array
{
    return array_merge([
        'title' => 'Procurement irregularities in district roads',
        'body' => "## Background\n\nThe records show several contracts were awarded **without competition**. ".str_repeat('Each tender had a single bidder. ', 4),
        'category' => 'governance-public-administration',
    ], $o);
}

it('generates the summary from the report body and ignores a client-supplied one', function () {
    $inv = $this->investigator();
    $res = $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', coverDraft(['summary' => 'Ignored']))->assertCreated();

    expect($res->json('data.summary'))->toStartWith('The records show several contracts were awarded without competition.')
        ->and(mb_strlen($res->json('data.summary')))->toBeLessThanOrEqual(301);
});

it('summarises Markdown as plain text, cutting at a Bangla sentence end', function () {
    $body = "# শিরোনাম\n\n> উদ্ধৃতি [লিংক](https://example.org) ও *জোর*।\n\n".str_repeat('পোশাকশ্রমিকদের মজুরি নিয়ে আলোচনা হয়েছে। ', 12);
    $summary = InvestigationService::summarize($body);

    expect($summary)->toStartWith('উদ্ধৃতি লিংক ও জোর।')
        ->and($summary)->toEndWith('।')
        ->and(mb_strlen($summary))->toBeLessThanOrEqual(300)
        ->and($summary)->not->toContain('https://')->not->toContain('*')->not->toContain('#');
    expect(InvestigationService::summarize("  \n**  **"))->toBeNull();
});

it('keeps a new header image private until the report is published', function () {
    $inv = $this->investigator();
    $id = $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', coverDraft())->json('data.id');

    $res = $this->actingAs($inv->user)->post("/api/v1/me/investigations/{$id}/cover", ['cover' => UploadedFile::fake()->image('cover.jpg', 1200, 600)])
        ->assertOk()->assertJsonPath('data.revision', 1); // does not disturb text autosaves
    $draftUrl = $res->json('data.cover_url');
    expect($draftUrl)->toStartWith("/api/v1/me/investigations/{$id}/cover?v=");
    $this->actingAs($inv->user)->get($draftUrl)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    $this->actingAs($inv->user)->getJson("/api/v1/me/investigations/{$id}/preview")->assertJsonPath('data.cover_url', $draftUrl);

    $other = $this->investigator();
    $this->actingAs($other->user)->get($draftUrl)->assertNotFound();

    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/publish", ['confirmations' => $this->confirmations()])->assertOk();
    auth()->forgetGuards();
    $publicUrl = $this->getJson("/api/v1/investigations/{$id}")->json('data.cover_url');
    expect($publicUrl)->toStartWith("/api/v1/investigations/{$id}/cover?v=");
    $this->get($publicUrl)->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    expect(collect($this->getJson('/api/v1/investigations')->json('data'))->firstWhere('id', $id)['cover_url'])->toBe($publicUrl);
});

it('replaces and removes covers without touching the published image until republish', function () {
    $inv = $this->investigator();
    $id = $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', coverDraft())->json('data.id');
    $this->actingAs($inv->user)->post("/api/v1/me/investigations/{$id}/cover", ['cover' => UploadedFile::fake()->image('a.jpg', 1200, 600)])->assertOk();
    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/publish", ['confirmations' => $this->confirmations()])->assertOk();
    $model = Investigation::query()->where('public_id', $id)->first();
    $first = $model->published_cover_path;
    $vault = app(FileVault::class);

    $this->actingAs($inv->user)->post("/api/v1/me/investigations/{$id}/cover", ['cover' => UploadedFile::fake()->image('b.png', 1000, 500)])
        ->assertOk()->assertJsonPath('data.has_unpublished_changes', true);
    $model->refresh();
    expect($model->published_cover_path)->toBe($first)->and($model->cover_path)->not->toBe($first)->and($vault->exists($first))->toBeTrue();

    $second = $model->cover_path;
    $this->actingAs($inv->user)->deleteJson("/api/v1/me/investigations/{$id}/cover")->assertOk()->assertJsonPath('data.cover_url', null);
    expect($vault->exists($second))->toBeFalse()->and($vault->exists($first))->toBeTrue();

    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/publish", ['confirmations' => $this->confirmations(), 'change_reason' => 'Removed the header image'])->assertOk();
    expect($model->refresh()->published_cover_path)->toBeNull()->and($vault->exists($first))->toBeFalse();
    auth()->forgetGuards();
    $this->get("/api/v1/investigations/{$id}/cover")->assertNotFound();
});

it('rejects non-images and tiny images as covers', function () {
    $inv = $this->investigator();
    $id = $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', coverDraft())->json('data.id');

    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/cover", ['cover' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])
        ->assertUnprocessable()->assertJsonValidationErrors('cover');
    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/cover", ['cover' => UploadedFile::fake()->image('s.jpg', 200, 100)])
        ->assertUnprocessable()->assertJsonPath('code', 'image_too_small');
});

it('hides the published cover when a report is withdrawn', function () {
    $inv = $this->investigator();
    $id = $this->actingAs($inv->user)->postJson('/api/v1/me/investigations', coverDraft())->json('data.id');
    $this->actingAs($inv->user)->post("/api/v1/me/investigations/{$id}/cover", ['cover' => UploadedFile::fake()->image('a.jpg', 1200, 600)])->assertOk();
    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/publish", ['confirmations' => $this->confirmations()])->assertOk();
    $this->actingAs($inv->user)->postJson("/api/v1/me/investigations/{$id}/withdraw", ['reason' => 'Retracting for corrections'])->assertOk();
    auth()->forgetGuards();

    $this->getJson("/api/v1/investigations/{$id}")->assertOk()->assertJsonPath('data.cover_url', null);
    $this->get("/api/v1/investigations/{$id}/cover")->assertNotFound();
});
