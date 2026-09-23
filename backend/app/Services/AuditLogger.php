<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\RequestId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Request;

/**
 * Append-only, tamper-evident audit trail. Each row's hash covers its content and
 * the previous row's hash; the chain head row is locked while appending so
 * concurrent transactions serialise instead of forking the chain.
 */
class AuditLogger
{
    /** Keys that must never be written into audit payloads. */
    private const REDACT = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes', 'token', 'code', 'guest_access_code', 'contact_value'];

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>|null  $after
     */
    public function log(string $action, Model|string|null $entity = null, ?array $before = null, ?array $after = null, ?User $actor = null, ?int $entityId = null): AuditLog
    {
        $actor ??= Auth::user() instanceof User ? Auth::user() : null;
        [$entityType, $id] = $this->entityRef($entity, $entityId);

        return DB::transaction(function () use ($action, $entityType, $id, $before, $after, $actor): AuditLog {
            $head = DB::table('audit_chain')->where('id', 1)->lockForUpdate()->first();
            $prevHash = $head?->last_hash;

            $row = [
                'actor_user_id' => $actor?->id,
                'action' => $action,
                'entity_type' => $entityType,
                'entity_id' => $id,
                'before_json' => $before === null ? null : $this->redact($before),
                'after_json' => $after === null ? null : $this->redact($after),
                'ip_address' => Request::ip(),
                'user_agent' => mb_substr((string) Request::userAgent(), 0, 500) ?: null,
                'request_id' => app()->runningInConsole() && ! app()->runningUnitTests() ? null : RequestId::get(),
                'prev_hash' => $prevHash,
                'created_at' => Carbon::now()->format('Y-m-d H:i:s.u'),
            ];
            $row['hash'] = self::computeHash($row);

            $log = new AuditLog;
            $log->forceFill($row);
            $log->save();

            DB::table('audit_chain')->where('id', 1)->update(['last_hash' => $row['hash'], 'last_id' => $log->id]);

            return $log;
        });
    }

    /** @param array<string, mixed> $row */
    public static function computeHash(array $row): string
    {
        $payload = [
            'prev' => $row['prev_hash'],
            'actor' => $row['actor_user_id'] === null ? null : (int) $row['actor_user_id'],
            'action' => $row['action'],
            'entity_type' => $row['entity_type'],
            'entity_id' => $row['entity_id'] === null ? null : (int) $row['entity_id'],
            'before' => self::canonical($row['before_json']),
            'after' => self::canonical($row['after_json']),
            'ip' => $row['ip_address'],
            'ua' => $row['user_agent'],
            'request_id' => $row['request_id'],
            'at' => $row['created_at'],
        ];

        return hash('sha256', (string) json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /**
     * Walk the chain in id order. Returns the id of the first broken row, or null if intact.
     */
    public function verifyChain(): ?int
    {
        $prev = null;
        $broken = null;
        DB::table('audit_logs')->orderBy('id')->chunk(1000, function ($rows) use (&$prev, &$broken) {
            foreach ($rows as $r) {
                $row = [
                    'prev_hash' => $r->prev_hash,
                    'actor_user_id' => $r->actor_user_id,
                    'action' => $r->action,
                    'entity_type' => $r->entity_type,
                    'entity_id' => $r->entity_id,
                    'before_json' => $r->before_json === null ? null : json_decode($r->before_json, true),
                    'after_json' => $r->after_json === null ? null : json_decode($r->after_json, true),
                    'ip_address' => $r->ip_address,
                    'user_agent' => $r->user_agent,
                    'request_id' => $r->request_id,
                    'created_at' => Carbon::parse($r->created_at)->format('Y-m-d H:i:s.u'),
                ];
                if ($r->prev_hash !== $prev || self::computeHash($row) !== $r->hash) {
                    $broken = (int) $r->id;

                    return false;
                }
                $prev = $r->hash;
            }

            return true;
        });

        return $broken;
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }
        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::canonical(...), $value);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function redact(array $data): array
    {
        foreach ($data as $key => $value) {
            if (in_array((string) $key, self::REDACT, true)) {
                $data[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $data[$key] = $this->redact($value);
            } elseif ($value instanceof \BackedEnum) {
                $data[$key] = $value->value;
            } elseif ($value instanceof \DateTimeInterface) {
                $data[$key] = $value->format(DATE_ATOM);
            } elseif (is_float($value)) {
                $data[$key] = (string) $value;
            }
        }

        return $data;
    }

    /** @return array{0: string|null, 1: int|null} */
    private function entityRef(Model|string|null $entity, ?int $entityId): array
    {
        if ($entity instanceof Model) {
            return [class_basename($entity), (int) $entity->getKey()];
        }

        return [$entity, $entityId];
    }
}
