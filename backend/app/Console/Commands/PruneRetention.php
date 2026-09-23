<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Data-retention policy (see docs/SECURITY.md#retention). Removes aged operational
 * telemetry only; audit logs, moderation actions, complaints and evidence access
 * logs are retained as the forensic record.
 */
class PruneRetention extends Command
{
    protected $signature = 'retention:prune {--dry-run}';

    protected $description = 'Prune analytics events, login logs, security events and stale verification codes past retention';

    public function handle(): int
    {
        $plan = [
            'analytics_events' => now()->subDays((int) config('onushondhan.analytics.retention_days')),
            'login_logs' => now()->subDays((int) config('onushondhan.retention.login_logs_days')),
            'security_events' => now()->subDays((int) config('onushondhan.retention.security_events_days')),
            'phone_verifications' => now()->subDays(7),
        ];

        foreach ($plan as $table => $before) {
            $query = DB::table($table)->where('created_at', '<', $before);
            $count = (clone $query)->count();
            if (! $this->option('dry-run')) {
                do {
                    $deleted = (clone $query)->limit(5000)->delete();
                } while ($deleted > 0);
            }
            $this->line(sprintf('%-22s %s %d rows older than %s', $table, $this->option('dry-run') ? 'would prune' : 'pruned', $count, $before->toDateString()));
        }

        // Analytics: drop fingerprints older than 30 days even inside the retention window.
        if (! $this->option('dry-run')) {
            DB::table('analytics_events')->where('created_at', '<', now()->subDays(30))->whereNotNull('anonymous_fingerprint')->update(['anonymous_fingerprint' => null]);
        }

        return self::SUCCESS;
    }
}
