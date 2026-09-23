<?php

use App\Contracts\MalwareScanner;
use App\Enums\ScanStatus;
use App\Models\Evidence;
use App\Services\Scanning\ScanResult;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function pdfFile(string $name = 'report.pdf'): UploadedFile
{
    $pdf = "%PDF-1.4\n1 0 obj << /Type /Catalog /Pages 2 0 R >> endobj\n2 0 obj << /Type /Pages /Kids [3 0 R] /Count 1 >> endobj\n3 0 obj << /Type /Page /Parent 2 0 R >> endobj\ntrailer << /Root 1 0 R >>\n%%EOF";

    return UploadedFile::fake()->createWithContent($name, $pdf);
}

beforeEach(function () {
    $this->inv = $this->investigator();
    $this->investigation = $this->inv->investigations()->create(['title' => 'Evidence host']);
    $this->url = "/api/v1/me/investigations/{$this->investigation->public_id}/evidence";
});

it('accepts an allowlisted PDF, hashes it and stores it privately under a random name', function () {
    $file = pdfFile('../../etc/passwd<script>.pdf');
    $res = $this->actingAs($this->inv->user)->post($this->url, ['file' => $file, 'caption' => 'Tender', 'visibility' => 'public'])->assertCreated();

    $e = Evidence::query()->where('public_id', $res->json('data.id'))->firstOrFail();
    expect($e->sha256)->toBe(hash_file('sha256', $file->getRealPath()))
        ->and($e->mime_type)->toBe('application/pdf')
        ->and($e->storage_path)->toMatch('#^evidence/\d{4}/\d{2}/[a-z0-9]{40}\.bin$#')
        ->and($e->original_name)->not->toContain('/')->not->toContain('<')
        ->and($e->scan_status->value)->toBe('not_scanned')
        ->and($e->versions()->count())->toBe(1);
    Storage::disk('vault')->assertExists($e->storage_path);
    expect(json_encode($res->json()))->not->toContain($e->storage_path);
});

it('rejects disguised, active or executable content', function (string $name, string $content) {
    $this->actingAs($this->inv->user)->post($this->url, ['file' => UploadedFile::fake()->createWithContent($name, $content)])
        ->assertUnprocessable()->assertJsonValidationErrors('file');
    expect(Evidence::query()->count())->toBe(0);
})->with([
    'php as jpg' => ['photo.jpg', "<?php system(\$_GET['c']); ?>"],
    'svg' => ['logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'],
    'html' => ['page.html', '<html><script>alert(1)</script></html>'],
    'windows exe' => ['tool.pdf', "MZ\x90\x00".str_repeat("\x00", 64)],
    'elf binary' => ['a.png', "\x7fELF".str_repeat("\x00", 64)],
    'shell script' => ['run.txt', "#!/bin/sh\nrm -rf /"],
    'double extension' => ['x.php.pdf', '%PDF-1.4 test'],
    'text with script' => ['notes.txt', 'hello <script>alert(1)</script>'],
]);

it('rejects a real PNG uploaded with a PDF extension (content/extension mismatch)', function () {
    $png = UploadedFile::fake()->image('real.png', 10, 10);
    $renamed = UploadedFile::fake()->createWithContent('real.pdf', file_get_contents($png->getRealPath()));
    $this->actingAs($this->inv->user)->post($this->url, ['file' => $renamed])->assertUnprocessable()->assertJsonValidationErrors('file');
});

it('enforces the size limit', function () {
    config(['onushondhan.evidence.max_upload_mb' => 1]);
    $big = UploadedFile::fake()->createWithContent('big.txt', str_repeat('a', 1024 * 1024 + 10));
    $this->actingAs($this->inv->user)->post($this->url, ['file' => $big])->assertUnprocessable();
});

it('rejects duplicate files on the same investigation', function () {
    $this->actingAs($this->inv->user)->post($this->url, ['file' => pdfFile()])->assertCreated();
    $this->actingAs($this->inv->user)->post($this->url, ['file' => pdfFile('copy.pdf')])->assertStatus(409);
});

it('strips EXIF by producing a sanitized image rendition', function () {
    $res = $this->actingAs($this->inv->user)->post($this->url, ['file' => UploadedFile::fake()->image('p.jpg', 400, 300)])->assertCreated();
    $e = Evidence::query()->where('public_id', $res->json('data.id'))->first();
    expect($e->preview_path)->toStartWith('previews/')->and($e->metadata_json['width'])->toBe(400);
});

it('versions evidence on replacement', function () {
    $id = $this->actingAs($this->inv->user)->post($this->url, ['file' => pdfFile()])->json('data.id');
    $newPdf = UploadedFile::fake()->createWithContent('v2.pdf', "%PDF-1.4\n% version two\n%%EOF");
    $this->actingAs($this->inv->user)->post("/api/v1/me/evidence/{$id}/versions", ['file' => $newPdf])->assertOk()->assertJsonPath('data.version', 2);
    $this->actingAs($this->inv->user)->getJson("/api/v1/me/evidence/{$id}/versions")->assertJsonCount(2, 'data');
});

it('prevents uploading to another investigator\'s investigation', function () {
    $other = $this->investigator();
    $this->actingAs($other->user)->post($this->url, ['file' => pdfFile()])->assertNotFound();
});

it('marks infected files quarantined and never serves them', function () {
    app()->instance(MalwareScanner::class, new class implements MalwareScanner
    {
        public function scan(string $p): ScanResult
        {
            return new ScanResult(ScanStatus::Infected, 'Eicar-Test-Signature');
        }

        public function isEnabled(): bool
        {
            return true;
        }
    });
    $id = $this->actingAs($this->inv->user)->post($this->url, ['file' => pdfFile(), 'visibility' => 'public'])->json('data.id');
    $e = Evidence::query()->where('public_id', $id)->first();
    expect($e->scan_status->value)->toBe('infected')->and($e->visibility->value)->toBe('private')->and($e->moderation_status)->toBe('quarantined');
    $this->actingAs($this->inv->user)->get("/api/v1/evidence/{$id}/download")->assertNotFound();
});
