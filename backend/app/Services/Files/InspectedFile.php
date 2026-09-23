<?php

declare(strict_types=1);

namespace App\Services\Files;

final class InspectedFile
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string $path,
        public readonly string $mime,
        public readonly string $extension,
        public readonly string $kind,
        public readonly int $size,
        public readonly string $sha256,
        public readonly string $safeName,
        public readonly array $metadata = [],
    ) {}
}
