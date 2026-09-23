# Environment & Secrets

Templates: `backend/.env.example`, `frontend/.env.example`. Real values live only in the server
environment / secret manager (`/var/www/onushondhan/shared/*.env`, `/etc/onushondhan/*.env`) and are
never committed. `.gitignore` excludes every `.env*` except the examples.

## Backend (Laravel)

| Variable | Production value / guidance | Secret? |
|---|---|---|
| `APP_NAME` | `Onushondhan` (also names the session cookie) | |
| `APP_ENV` | `production` | |
| `APP_KEY` | `php artisan key:generate --show`. Encrypts sessions, private identity, tip contact details and MFA secrets — **back it up securely; losing it makes encrypted data unreadable**. | **yes** |
| `APP_DEBUG` | `false` (deploy script refuses otherwise) | |
| `APP_URL` | `https://<domain>` | |
| `FRONTEND_URL` | `https://<domain>` (links in emails) | |
| `TRUSTED_PROXIES` | IPs of the reverse proxy / load balancer (default `127.0.0.1,::1`) | |
| `LOG_CHANNEL`, `LOG_STACK`, `LOG_DAILY_DAYS`, `LOG_LEVEL` | `stack`, `daily`, `14`, `warning` | |
| `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` | MySQL 8 connection (dedicated least-privilege user) | |
| `DB_PASSWORD` | | **yes** |
| `SESSION_DRIVER` | `database` | |
| `SESSION_LIFETIME` | `120` (minutes) | |
| `SESSION_ENCRYPT` | `true` | |
| `SESSION_SECURE_COOKIE` | `true` (HTTPS only) | |
| `SESSION_SAME_SITE` | `lax` | |
| `SESSION_DOMAIN` | empty (host-only cookie) | |
| `SANCTUM_STATEFUL_DOMAINS` | `<domain>` (host[:port] list that receives cookie auth) | |
| `CORS_ALLOWED_ORIGINS` | empty in the single-origin topology | |
| `QUEUE_CONNECTION` | `database` (no Redis) | |
| `CACHE_STORE` | `database` (session revocation and rate limits rely on a shared cache) | |
| `HASH_DRIVER` | `argon2id` | |
| `VAULT_DISK`, `VAULT_ROOT` | `vault`, `/var/www/onushondhan/shared/storage/app/private/vault` (outside any web root) | |
| `EVIDENCE_MAX_UPLOAD_MB` / `TIP_ATTACHMENT_MAX_MB` | `100` / `20` (keep Nginx/PHP limits above these) | |
| `MALWARE_SCANNER` | `clamav` recommended (`none` marks files `not_scanned`) | |
| `CLAMAV_BINARY` | `/usr/bin/clamdscan` | |
| `EVIDENCE_REQUIRE_CLEAN_SCAN` | `true` once ClamAV is running (public evidence served only when `clean`) | |
| `ADMIN_MFA_REQUIRED` | `true` | |
| `ANALYTICS_FINGERPRINT_KEY` | random 32 bytes base64 (`php -r "echo base64_encode(random_bytes(32));"`) | **yes** |
| `ANALYTICS_RETENTION_DAYS`, `LOGIN_LOG_RETENTION_DAYS` | `180`, `365` | |
| `SMS_DRIVER` | `log` only in dev; bind a real `App\Contracts\SmsSender` for production phone verification | |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_SCHEME`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | SMTP provider settings | |
| `MAIL_PASSWORD` | | **yes** |
| `MAIL_LOG_CHANNEL`, `MAIL_LOG_PATH` | dev/E2E only (`mail` → `storage/logs/mail.log`) | |
| `SEED_DEMO_DATA` | ignored in production (demo seed never runs when `APP_ENV=production`) | |

## Frontend (Next.js)

| Variable | Guidance |
|---|---|
| `API_INTERNAL_URL` | Where the Next.js server reaches Laravel for SSR data, e.g. `http://127.0.0.1` (via Nginx) or the internal PHP host. Server-only. |
| `NEXT_PUBLIC_SITE_URL` | `https://<domain>` — canonical URLs, OG tags, sitemap. **Baked in at build time.** |
| `NEXT_PUBLIC_ADMIN_URL` | `https://<domain>/admin` — shown to staff. Baked in at build time. |

Only `NEXT_PUBLIC_*` values reach the browser; none are secret.

## Backups (`/etc/onushondhan/backup.env`)

`BACKUP_DIR`, `BACKUP_AGE_RECIPIENT` (public key; the private key is kept offline),
`DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (read-only backup user),
`VAULT_PATH`, `BACKUP_RETENTION_DAYS`, `BACKUP_REMOTE` (separate location).

## Local development

- Backend: copy `backend/.env.example` → `backend/.env`, set DB credentials, `php artisan key:generate`,
  `php artisan migrate --seed` (seeds demo accounts — **local only**, password `Onushondhan#2026`).
- Frontend: copy `frontend/.env.example` → `frontend/.env.local` and point `API_INTERNAL_URL` at the API.
