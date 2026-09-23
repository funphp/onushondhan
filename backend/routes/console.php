<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

// Cron: * * * * * cd /var/www/onushondhan/backend && php artisan schedule:run >> /dev/null 2>&1
Schedule::command('investigations:trending')->hourly()->withoutOverlapping();
Schedule::command('retention:prune')->dailyAt('03:15')->withoutOverlapping();
Schedule::command('audit:verify')->dailyAt('03:45');
Schedule::command('queue:prune-failed --hours=720')->daily();
Schedule::command('queue:prune-batches --hours=168')->daily();
Schedule::command('auth:clear-resets')->everyFifteenMinutes();
