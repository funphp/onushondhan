<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Private file storage used by evidence and tip attachments. Domain services depend
 * only on this contract, so a future object-storage adapter can replace the local
 * implementation without touching them.
 */
interface FileVault
{
    public function diskName(): string;

    /** Store the file at $localPath under a new randomised key; returns the key. */
    public function putFile(string $localPath, string $prefix): string;

    /** @return resource */
    public function readStream(string $key);

    public function exists(string $key): bool;

    public function delete(string $key): void;

    public function size(string $key): int;

    /**
     * Run $callback with a local filesystem path for $key (a temp copy for remote
     * adapters). The path is only valid inside the callback.
     *
     * @template T
     *
     * @param  callable(string): T  $callback
     * @return T
     */
    public function withLocalPath(string $key, callable $callback): mixed;
}
