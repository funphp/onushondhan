<?php

use App\Models\Tip;
use App\Models\TipAttachment;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->pub = $this->publishedInvestigation();
    $this->owner = $this->pub->investigator->user;
});

$tip = fn (array $o = []) => array_merge(['subject' => 'A lead', 'message' => 'Check the second tender carefully.', 'agree_to_content_policy' => true], $o);

it('accepts guest tips anonymous to the platform and returns a one-time access code', function () use ($tip) {
    $res = $this->postJson('/api/v1/tips', $tip(['investigation' => $this->pub->public_id]))->assertCreated();
    $code = $res->json('data.access_code');
    expect($code)->toMatch('/^TIP-[A-Z0-9]{5}(-[A-Z0-9]{5}){3}$/');

    $model = Tip::query()->where('public_id', $res->json('data.id'))->first();
    expect($model->sender_user_id)->toBeNull()->and($model->anonymous_to_investigator)->toBeTrue()
        ->and($model->guest_access_hash)->toBe(hash('sha256', $code))->and($this->pub->fresh()->tips_count)->toBe(1);

    $this->postJson('/api/v1/tips/access', ['access_code' => $code])->assertOk()->assertJsonPath('data.subject', 'A lead');
    $this->postJson('/api/v1/tips/access', ['access_code' => 'TIP-AAAAA-BBBBB-CCCCC-DDDDD'])->assertNotFound();
});

it('hides member identity from the investigator unless the member opts in', function () use ($tip) {
    $member = $this->member();
    $this->actingAs($member)->postJson('/api/v1/tips', $tip(['investigator' => $this->pub->investigator->slug]))->assertCreated();
    $this->actingAs($member)->postJson('/api/v1/tips', $tip(['investigator' => $this->pub->investigator->slug, 'subject' => 'Named', 'anonymous_to_investigator' => false]))->assertCreated();

    $inbox = $this->actingAs($this->owner)->getJson('/api/v1/me/inbox/tips')->assertOk();
    $bySubject = collect($inbox->json('data'))->keyBy('subject');
    expect($bySubject['A lead']['sender']['anonymous'])->toBeTrue()->and($bySubject['A lead']['sender']['username'])->toBeNull()
        ->and($bySubject['Named']['sender']['username'])->toBe($member->username);
    expect(json_encode($inbox->json()))->not->toContain($member->email)->not->toContain($member->name);
});

it('scopes the inbox strictly to the recipient investigator', function () use ($tip) {
    $id = $this->postJson('/api/v1/tips', $tip(['investigation' => $this->pub->public_id]))->json('data.id');
    $other = $this->investigator();
    $this->actingAs($other->user)->getJson("/api/v1/me/inbox/tips/{$id}")->assertNotFound();
    $this->actingAs($other->user)->patchJson("/api/v1/me/inbox/tips/{$id}", ['status' => 'archived'])->assertNotFound();
    $this->actingAs($other->user)->postJson("/api/v1/me/inbox/tips/{$id}/messages", ['body' => 'hi'])->assertNotFound();
    expect(collect($this->actingAs($other->user)->getJson('/api/v1/me/inbox/tips')->json('data')))->toBeEmpty();
    $this->actingAs($this->staff('admin'))->getJson("/api/v1/me/tips/{$id}")->assertNotFound();
});

it('supports read/important/archive statuses and two-way messaging', function () use ($tip) {
    $code = $this->postJson('/api/v1/tips', $tip(['investigation' => $this->pub->public_id]))->json('data.access_code');
    $id = Tip::query()->first()->public_id;

    $this->actingAs($this->owner)->getJson("/api/v1/me/inbox/tips/{$id}")->assertJsonPath('data.status', 'read');
    $this->actingAs($this->owner)->patchJson("/api/v1/me/inbox/tips/{$id}", ['status' => 'important'])->assertJsonPath('data.status', 'important');
    $this->actingAs($this->owner)->postJson("/api/v1/me/inbox/tips/{$id}/messages", ['body' => 'Can you share more?'])->assertJsonPath('data.messages.0.from', 'investigator');
    auth()->forgetGuards();

    $this->postJson('/api/v1/tips/access/messages', ['access_code' => $code, 'body' => 'Yes, the invoice is dated March.'])
        ->assertOk()->assertJsonCount(2, 'data.messages');
    $this->actingAs($this->owner)->patchJson("/api/v1/me/inbox/tips/{$id}", ['status' => 'closed']);
    auth()->forgetGuards();
    $this->postJson('/api/v1/tips/access/messages', ['access_code' => $code, 'body' => 'more'])->assertUnprocessable()->assertJsonPath('code', 'tip_closed');
});

it('protects tip attachments to the recipient and sender', function () use ($tip) {
    $member = $this->member();
    $file = UploadedFile::fake()->createWithContent('invoice.pdf', "%PDF-1.4\n% invoice\n%%EOF");
    $this->actingAs($member)->post('/api/v1/tips', $tip(['investigation' => $this->pub->public_id, 'attachments' => [$file]]))->assertCreated();
    $attachment = TipAttachment::query()->first();

    $this->actingAs($this->owner)->get("/api/v1/tip-attachments/{$attachment->public_id}/download")->assertOk();
    $this->actingAs($member)->get("/api/v1/tip-attachments/{$attachment->public_id}/download")->assertOk();
    $this->actingAs($this->investigator()->user)->get("/api/v1/tip-attachments/{$attachment->public_id}/download")->assertNotFound();
    $this->actingAs($this->staff('admin'))->get("/api/v1/tip-attachments/{$attachment->public_id}/download")->assertNotFound();
});

it('rejects malicious tip attachments', function () use ($tip) {
    $bad = UploadedFile::fake()->createWithContent('x.jpg', '<?php echo 1;');
    $this->post('/api/v1/tips', $tip(['investigation' => $this->pub->public_id, 'attachments' => [$bad]]))->assertUnprocessable();
    expect(Tip::query()->count())->toBe(0);
});

it('does not allow tipping yourself', function () use ($tip) {
    $this->actingAs($this->owner)->postJson('/api/v1/tips', $tip(['investigation' => $this->pub->public_id]))->assertUnprocessable();
});
