# Operations, Backup & Recovery

## Recovery objectives (initial assumptions — confirm with the operator)

| | Target | How |
|---|---|---|
| RPO (data loss) | ≤ 24 h | daily encrypted backup (`onushondhan-backup.timer`, 02:30 UTC). Increase frequency or add MySQL binlog shipping when write volume grows. |
| RTO (restore time) | ≤ 4 h | documented restore below onto a prepared host; rehearse quarterly. |

## Backups

`scripts/backup.sh` (run by `onushondhan-backup.service`):

1. `mysqldump --single-transaction` → gzip → **age-encrypted** to a public key (the private key is kept
   offline, never on the server).
2. The private file vault (evidence, previews, tip attachments, avatars) → tar.gz → age-encrypted.
3. SHA-256 checksums; optional `rsync` to a **separate** location (`BACKUP_REMOTE`).
4. Local retention `BACKUP_RETENTION_DAYS` (default 30). Keep off-site copies for longer (e.g. 12
   monthly) according to your retention policy.

Also back up, separately and securely: `APP_KEY` and `ANALYTICS_FINGERPRINT_KEY` (encrypted columns
are unreadable without `APP_KEY`), and the age private key.

## Restore (test it — a backup is not valid until a restore has succeeded)

```bash
# On a staging host or a scratch database — never straight onto production without authorisation.
BACKUP_DIR=/var/backups/onushondhan AGE_IDENTITY=/secure/age-key.txt \
DB_DATABASE=onushondhan_restore_test DB_USERNAME=... DB_PASSWORD=... \
VAULT_PATH=/tmp/vault-restore \
scripts/restore.sh <timestamp> --i-understand-this-overwrites-data

# Then, with the restored DB configured:
php artisan migrate:status && php artisan audit:verify
```

Record each rehearsal (date, backup timestamp, duration, result) in the release checklist.

## Scheduled jobs (`routes/console.php`)

| Job | Schedule | Purpose |
|---|---|---|
| `investigations:trending` | hourly | trending scores |
| `retention:prune` | daily 03:15 | analytics (180 d, fingerprints nulled after 30 d), login logs (365 d), security events (730 d), stale phone codes |
| `audit:verify` | daily 03:45 | hash-chain integrity; raises a `critical` security event if broken |
| `queue:prune-failed --hours=720`, `queue:prune-batches` | daily | queue hygiene |
| `auth:clear-resets` | every 15 min | expired password-reset tokens |

Retained indefinitely as the forensic record: audit logs, moderation actions, complaints, agreement
acceptances, investigation versions, evidence access logs.

## Monitoring

| Signal | Source |
|---|---|
| Liveness/readiness | `GET /api/v1/health` (DB, storage, queue backlog, failed jobs in the last hour) — `scripts/healthcheck.sh` from cron or an external monitor |
| 4xx/5xx rates, latency | Nginx `onushondhan.access.log` |
| Application errors | `backend/storage/logs/laravel-*.log` (JSON-able; includes `request_id`) |
| PHP-FPM | `pm.status_path=/fpm-status` (expose only to localhost) |
| Next.js | `systemctl status onushondhan-next`, journal |
| Queue | `php artisan queue:failed`, health `queue` field |
| Security events | `security_events` table / admin panel "Security events" (failed logins, password resets, role changes, private-identity access, infected files, audit chain breaks) |
| Disk / vault growth | `du -sh shared/storage/app/private/vault`, standard node exporter if available |
| MySQL | slow query log, connections |

Sentry/Prometheus/Grafana are optional and not required; wire them only if the deployment has them.

## Routine maintenance

- Security updates (OS, PHP, Node); `composer audit` / `npm audit` (also in CI and Dependabot).
- Review the moderation queue (admin dashboard), failed scans and quarantined evidence.
- Review `security_events` for private-identity access and role changes.
- Verify backups and rehearse a restore quarterly.
- Log rotation: Laravel `daily` channel (14 days) + `deploy/logrotate/onushondhan`.
- Stale uploads: evidence is soft-deleted; purge files of long-deleted evidence only after legal review.
- Index/slow-query review as data grows.

## Incident notes

- **Compromised admin account:** block the account via another super admin (or
  `php artisan users:grant-role <email> <role> --revoke`), which revokes all its sessions; rotate
  `APP_KEY` only with a re-encryption plan; review `audit_logs` by `actor_user_id`.
- **Malicious upload reported:** the file is quarantined automatically when ClamAV flags it; otherwise
  hide/remove via the Evidence resource (audited) and investigate `evidence_access_logs`.
