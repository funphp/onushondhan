<?php

declare(strict_types=1);

namespace App\Services\Scanning;

use App\Contracts\MalwareScanner;
use App\Enums\ScanStatus;
use Illuminate\Support\Facades\Process;

/**
 * ClamAV integration via clamdscan (daemon) — exit code 0 clean, 1 infected, 2 error.
 */
class ClamAvScanner implements MalwareScanner
{
    public function __construct(private readonly string $binary, private readonly int $timeout) {}

    public function isEnabled(): bool
    {
        return true;
    }

    public function scan(string $localPath): ScanResult
    {
        if (! is_executable($this->binary)) {
            return new ScanResult(ScanStatus::Failed, 'Scanner binary unavailable.');
        }

        $result = Process::timeout($this->timeout)->run([$this->binary, '--no-summary', '--fdpass', '--stdout', $localPath]);

        return match ($result->exitCode()) {
            0 => new ScanResult(ScanStatus::Clean),
            1 => new ScanResult(ScanStatus::Infected, mb_substr(trim(str_replace($localPath, '[file]', $result->output())), 0, 480)),
            default => new ScanResult(ScanStatus::Failed, 'Scanner error (exit '.$result->exitCode().').'),
        };
    }
}
