<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\LoginLog;
use App\Models\SecurityEvent;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Request;

class SecurityLogger
{
    public function login(?User $user, ?string $email, bool $success, ?string $failureReason = null): void
    {
        LoginLog::query()->create([
            'user_id' => $user?->id,
            'email_attempted' => $success ? null : ($email !== null ? mb_substr(mb_strtolower($email), 0, 255) : null),
            'success' => $success,
            'ip_address' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 500),
            'failure_reason' => $failureReason,
            'created_at' => now(),
        ]);
    }

    /** @param array<string, mixed> $metadata */
    public function event(string $type, ?User $user = null, string $severity = 'info', array $metadata = []): void
    {
        SecurityEvent::query()->create([
            'user_id' => $user?->id,
            'event_type' => $type,
            'severity' => $severity,
            'ip_address' => Request::ip(),
            'user_agent' => mb_substr((string) Request::userAgent(), 0, 500),
            'metadata_json' => $metadata,
            'created_at' => now(),
        ]);

        if (in_array($severity, ['warning', 'critical'], true)) {
            Log::channel(config('logging.default'))->warning('security_event', ['type' => $type, 'user_id' => $user?->id] + $metadata);
        }
    }
}
