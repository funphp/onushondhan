<?php

declare(strict_types=1);

namespace App\Services\Scanning;

use App\Enums\ScanStatus;

final class ScanResult
{
    public function __construct(public readonly ScanStatus $status, public readonly ?string $message = null) {}
}
