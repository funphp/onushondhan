<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\AuditLogger;
use App\Services\SecurityLogger;
use Illuminate\Console\Command;

class VerifyAuditChain extends Command
{
    protected $signature = 'audit:verify';

    protected $description = 'Verify the tamper-evident hash chain of the audit log';

    public function handle(AuditLogger $audit, SecurityLogger $security): int
    {
        $broken = $audit->verifyChain();
        if ($broken === null) {
            $this->info('Audit chain intact.');

            return self::SUCCESS;
        }
        $security->event('audit.chain_broken', null, 'critical', ['first_broken_id' => $broken]);
        $this->error("Audit chain broken at audit_logs.id = {$broken}.");

        return self::FAILURE;
    }
}
