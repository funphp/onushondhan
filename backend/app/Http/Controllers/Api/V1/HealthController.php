<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Contracts\FileVault;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Readiness probe: app, database, storage and queue backlog. Reports only coarse
 * ok/fail states — no versions, hosts, paths or error messages.
 */
class HealthController extends Controller
{
    public function __invoke(FileVault $vault): JsonResponse
    {
        $checks = [];

        try {
            DB::select('SELECT 1');
            $checks['database'] = 'ok';
        } catch (Throwable) {
            $checks['database'] = 'fail';
        }

        try {
            $vault->exists('.healthcheck');
            $checks['storage'] = 'ok';
        } catch (Throwable) {
            $checks['storage'] = 'fail';
        }

        try {
            $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subHour())->count();
            $backlog = DB::table('jobs')->where('available_at', '<=', now()->subMinutes(10)->getTimestamp())->count();
            $checks['queue'] = $backlog > 500 ? 'degraded' : 'ok';
            $checks['recent_failed_jobs'] = $failed;
        } catch (Throwable) {
            $checks['queue'] = 'unknown';
        }

        $healthy = $checks['database'] === 'ok' && $checks['storage'] === 'ok';

        return response()->json(['status' => $healthy ? 'ok' : 'fail', 'checks' => $checks, 'time' => now()->toIso8601String()], $healthy ? 200 : 503);
    }
}
