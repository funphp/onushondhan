<?php

declare(strict_types=1);

namespace App\Services\Files;

use App\Contracts\FileVault;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class FilesystemFileVault implements FileVault
{
    public function __construct(private readonly string $disk) {}

    public function diskName(): string
    {
        return $this->disk;
    }

    private function fs(): Filesystem
    {
        return Storage::disk($this->disk);
    }

    public function putFile(string $localPath, string $prefix): string
    {
        $prefix = trim(preg_replace('/[^a-z0-9_\-]/', '', strtolower($prefix)) ?: 'files', '/');
        // Random key, no user-controlled segment, no executable extension.
        $key = sprintf('%s/%s/%s.bin', $prefix, now()->format('Y/m'), Str::lower(Str::random(40)));
        $stream = fopen($localPath, 'rb');
        if ($stream === false) {
            throw new RuntimeException('Unable to open upload for storage.');
        }
        try {
            $this->fs()->writeStream($key, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $key;
    }

    public function readStream(string $key)
    {
        $stream = $this->fs()->readStream($key);
        if (! is_resource($stream)) {
            throw new RuntimeException('Stored file is unavailable.');
        }

        return $stream;
    }

    public function exists(string $key): bool
    {
        return $this->fs()->exists($key);
    }

    public function delete(string $key): void
    {
        $this->fs()->delete($key);
    }

    public function size(string $key): int
    {
        return (int) $this->fs()->size($key);
    }

    public function withLocalPath(string $key, callable $callback): mixed
    {
        $config = config("filesystems.disks.{$this->disk}");
        if (($config['driver'] ?? null) === 'local') {
            return $callback($this->fs()->path($key));
        }

        $tmp = tempnam(sys_get_temp_dir(), 'vault');
        if ($tmp === false) {
            throw new RuntimeException('Unable to allocate a temporary file.');
        }
        try {
            $in = $this->readStream($key);
            $out = fopen($tmp, 'wb');
            stream_copy_to_stream($in, $out);
            fclose($in);
            fclose($out);

            return $callback($tmp);
        } finally {
            @unlink($tmp);
        }
    }
}
