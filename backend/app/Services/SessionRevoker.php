<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Revokes sessions so they cannot come back. Deleting a session row is not enough:
 * a request that was already in flight when the session was destroyed writes it
 * back when it finishes. Revoked ids are remembered and rejected on sight
 * (see RejectRevokedSession) for the full session lifetime.
 */
class SessionRevoker
{
    public function revoke(string $sessionId): void
    {
        Cache::put(self::key($sessionId), true, now()->addMinutes((int) config('session.lifetime', 120) + 5));
    }

    /** Revoke (and delete) every session of a user, optionally keeping one. */
    public function revokeUser(User $user, ?string $except = null): void
    {
        $ids = DB::table('sessions')->where('user_id', $user->id)
            ->when($except, fn ($q) => $q->where('id', '!=', $except))
            ->pluck('id');
        foreach ($ids as $id) {
            $this->revoke((string) $id);
        }
        DB::table('sessions')->whereIn('id', $ids)->delete();
    }

    public function isRevoked(string $sessionId): bool
    {
        return Cache::has(self::key($sessionId));
    }

    private static function key(string $id): string
    {
        return 'revoked-session:'.hash('sha256', $id);
    }
}
