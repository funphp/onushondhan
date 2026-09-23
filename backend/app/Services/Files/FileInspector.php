<?php

declare(strict_types=1);

namespace App\Services\Files;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use ZipArchive;

/**
 * Server-side upload validation. Nothing the client says (filename, extension,
 * Content-Type) is trusted: the type is detected from content, then cross-checked
 * against an allowlist and the claimed extension.
 */
class FileInspector
{
    private const OOXML_MARKERS = [
        'docx' => 'word/document.xml',
        'xlsx' => 'xl/workbook.xml',
    ];

    private const ODF_TYPES = [
        'odt' => 'application/vnd.oasis.opendocument.text',
        'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
    ];

    /** Signatures of executables/scripts that must never be accepted in any container. */
    private const FORBIDDEN_PREFIXES = ["MZ", "\x7fELF", "#!", "\xCA\xFE\xBA\xBE", "\xCF\xFA\xED\xFE", "\xFE\xED\xFA\xCE"];

    /** @param array<string, list<string>> $allowed detected mime => extensions */
    public function inspect(UploadedFile $file, array $allowed, int $maxBytes, string $field = 'file'): InspectedFile
    {
        if (! $file->isValid()) {
            $this->fail($field, 'The upload failed. Please try again.');
        }

        $path = $file->getRealPath();
        if ($path === false || ! is_file($path)) {
            $this->fail($field, 'The upload could not be read.');
        }

        $size = (int) filesize($path);
        if ($size <= 0) {
            $this->fail($field, 'The file is empty.');
        }
        if ($size > $maxBytes) {
            $this->fail($field, 'The file exceeds the maximum allowed size of '.intdiv($maxBytes, 1024 * 1024).' MB.');
        }

        $safeName = $this->safeName($file->getClientOriginalName());
        $claimedExt = strtolower(pathinfo($safeName, PATHINFO_EXTENSION));
        if ($claimedExt === '' || count(explode('.', $safeName)) > 2 && $this->hasDangerousInnerExtension($safeName)) {
            $this->fail($field, 'This file name is not allowed.');
        }

        $head = (string) file_get_contents($path, false, null, 0, 4096);
        foreach (self::FORBIDDEN_PREFIXES as $sig) {
            if (str_starts_with($head, $sig)) {
                $this->fail($field, 'Executable files are not allowed.');
            }
        }

        $mime = $this->detectMime($path, $claimedExt);

        if (! array_key_exists($mime, $allowed)) {
            $this->fail($field, 'This file type is not allowed.');
        }
        if (! in_array($claimedExt, $allowed[$mime], true)) {
            $this->fail($field, 'The file extension does not match its content.');
        }

        $metadata = [];
        if (str_starts_with($mime, 'text/')) {
            $this->assertSafeText($path, $field);
        }
        if ($mime === 'application/pdf') {
            $metadata = $this->pdfMetadata($path);
        }
        if (in_array($claimedExt, ['docx', 'xlsx'], true) && $this->ooxmlHasMacros($path)) {
            $this->fail($field, 'Documents containing macros are not allowed.');
        }

        return new InspectedFile(
            path: $path,
            mime: $mime,
            extension: $claimedExt,
            kind: self::kindFor($mime),
            size: $size,
            sha256: (string) hash_file('sha256', $path),
            safeName: $safeName,
            metadata: $metadata,
        );
    }

    public static function kindFor(string $mime): string
    {
        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            $mime === 'application/pdf' => 'pdf',
            default => 'document',
        };
    }

    public function safeName(string $name): string
    {
        $name = str_replace(['\\', '/'], '_', $name);
        $name = basename($name);
        $name = preg_replace('/[\x00-\x1F\x7F]+/u', '', $name) ?? '';
        $name = preg_replace('/[<>:"|?*]+/', '_', $name) ?? '';
        $name = trim($name, " .\t");
        if ($name === '') {
            $name = 'file';
        }
        if (mb_strlen($name) > 200) {
            $ext = pathinfo($name, PATHINFO_EXTENSION);
            $name = mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 190).($ext !== '' ? '.'.mb_substr($ext, 0, 8) : '');
        }

        return $name;
    }

    private function hasDangerousInnerExtension(string $name): bool
    {
        $parts = array_map('strtolower', array_slice(explode('.', $name), 1, -1));
        $dangerous = ['php', 'phtml', 'phar', 'exe', 'js', 'sh', 'bat', 'cmd', 'html', 'htm', 'svg', 'jsp', 'asp', 'aspx', 'cgi', 'pl', 'py'];

        return array_intersect($parts, $dangerous) !== [];
    }

    private function detectMime(string $path, string $claimedExt): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = (string) $finfo->file($path);

        // Older libmagic versions report OOXML/ODF as generic zip; confirm by structure.
        if (in_array($mime, ['application/zip', 'application/octet-stream', 'application/x-zip-compressed'], true)) {
            $resolved = $this->resolveZipOffice($path, $claimedExt);
            if ($resolved !== null) {
                return $resolved;
            }
        }
        if ($mime === 'text/plain' && $claimedExt === 'csv') {
            return 'text/csv';
        }
        if ($mime === 'audio/x-hx-aac-adts' || $mime === 'video/x-m4v') {
            return $mime === 'video/x-m4v' ? 'video/mp4' : 'audio/mp4';
        }

        return $mime;
    }

    private function resolveZipOffice(string $path, string $ext): ?string
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return null;
        }
        try {
            if (isset(self::OOXML_MARKERS[$ext]) && $zip->locateName('[Content_Types].xml') !== false && $zip->locateName(self::OOXML_MARKERS[$ext]) !== false) {
                return $ext === 'docx'
                    ? 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
                    : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            }
            if (isset(self::ODF_TYPES[$ext]) && trim((string) $zip->getFromName('mimetype')) === self::ODF_TYPES[$ext]) {
                return self::ODF_TYPES[$ext];
            }
        } finally {
            $zip->close();
        }

        return null;
    }

    private function ooxmlHasMacros(string $path): bool
    {
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::RDONLY) !== true) {
            return false;
        }
        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $name = strtolower((string) $zip->getNameIndex($i));
                if (str_ends_with($name, 'vbaproject.bin') || str_contains($name, 'activex')) {
                    return true;
                }
            }
        } finally {
            $zip->close();
        }

        return false;
    }

    private function assertSafeText(string $path, string $field): void
    {
        $sample = strtolower((string) file_get_contents($path, false, null, 0, 65536));
        if (str_contains($sample, '<?php') || str_contains($sample, '<script') || str_contains($sample, '<html')) {
            $this->fail($field, 'This text file contains disallowed markup.');
        }
        if (! mb_check_encoding($sample, 'UTF-8') && preg_match('/[\x00-\x08\x0E-\x1F]/', $sample) === 1) {
            $this->fail($field, 'This text file contains binary content.');
        }
    }

    /** @return array<string, mixed> */
    private function pdfMetadata(string $path): array
    {
        $content = (string) file_get_contents($path, false, null, 0, 5 * 1024 * 1024);
        $pages = preg_match_all('/\/Type\s*\/Page[^s]/', $content);

        return [
            'pages' => $pages ?: null,
            'has_active_content' => preg_match('/\/(JavaScript|JS|Launch|EmbeddedFile|OpenAction|AA)\b/', $content) === 1,
        ];
    }

    private function fail(string $field, string $message): never
    {
        throw ValidationException::withMessages([$field => [$message]]);
    }
}
