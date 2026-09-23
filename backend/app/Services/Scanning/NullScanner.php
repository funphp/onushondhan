<?php

declare(strict_types=1);

namespace App\Services\Scanning;

use App\Contracts\MalwareScanner;
use App\Enums\ScanStatus;

/** Used when no scanner is configured: files are explicitly marked not_scanned. */
class NullScanner implements MalwareScanner
{
    public function scan(string $localPath): ScanResult
    {
        return new ScanResult(ScanStatus::NotScanned, 'No malware scanner configured.');
    }

    public function isEnabled(): bool
    {
        return false;
    }
}
