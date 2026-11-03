# Deployment — cPanel shared hosting (current production)

Production runs on a cPanel / CloudLinux shared-hosting account with LiteSpeed, PHP 8.3 (EA4),
MariaDB 10.11 and cPanel's Node.js app manager (Passenger). No root, no systemd, no Docker.
The VPS layout in `docs/DEPLOYMENT.md` remains the reference for a dedicated server.

## Topology

| Host | Serves | Document/app root |
|---|---|---|
| `onusondhan.com` | Next.js site (cPanel Node.js app, Node 22, `server.js`) | app root `~/onusondhan/current/frontend`; docroot `~/onusondhan.com` holds only `.htaccess` |
| `admin.onusondhan.com` | Laravel API + Filament admin (`/admin`) | `~/onusondhan/current/backend/public` |

- Browsers only talk to `onusondhan.com`. `/api/*` and `/sanctum/*` are proxied by the Next.js server to
  `admin.onusondhan.com` (rewrites baked in at build from `API_INTERNAL_URL`), so Sanctum cookies stay
  first-party. The proxy buffers request bodies; `proxyClientMaxBodySize` is 110 MB for evidence uploads.
- Laravel sees proxied requests from this host's own IPs; they are listed in `TRUSTED_PROXIES`, so the
  real visitor IP (rate limits, login/security logs) comes from `X-Forwarded-For`.
- Server-side renders send `X-Internal-Token` (`INTERNAL_API_TOKEN`, set in the Node app's environment
  in cPanel and in `shared/backend.env`) and are exempt from the per-IP API limit.
- `current` must be a **real directory**: LiteSpeed returns 404 for a document root reached through a
  symlink. Releases are therefore swapped by renaming directories.
- `www` → apex and HTTP → HTTPS redirects live at the top of `~/onusondhan.com/.htaccess` (cPanel's
  Force-HTTPS is also enabled). The CloudLinux Passenger block in that file is managed by cPanel.
- TLS: cPanel AutoSSL (Let's Encrypt) for both hosts; DNS is at name.com (A records for `@`, `www`,
  `admin` → the server IP).

## Server layout (`~/onusondhan`)

```
current/              live release: backend/, frontend/, RELEASE ("<timestamp> <git sha>")
releases/prev-*       previous releases (last 3) for rollback
shared/backend.env    production .env (chmod 600) — secrets generated on the server, never in git
shared/storage/       Laravel storage incl. private evidence vault (not web-accessible)
shared/backup.my.cnf  DB credentials for the backup job (chmod 600)
bin/composer          Composer (installed with checksum verification)
bin/backup-db.sh      daily compressed DB dump → backups/ (14 days)
backups/
```

## Cron (cPanel → Cron Jobs)

```
* * * * *  php artisan schedule:run                         # scheduler
* * * * *  php artisan queue:work database --stop-when-empty --max-time=50   # queue (emails, notifications)
30 2 * * * ~/onusondhan/bin/backup-db.sh                     # DB backup
```
(All with `cd ~/onusondhan/current/backend` and `/opt/cpanel/ea-php83/root/usr/bin/php`.)

## Deploying an update

From a machine with SSH key access to the account and a clean, committed checkout:

```bash
DEPLOY_SSH=funphpco@funphp.com SITE_URL=https://onusondhan.com ADMIN_URL=https://admin.onusondhan.com \
  scripts/deploy-cpanel.sh
```

It builds the frontend locally, uploads a new release, runs `composer install --no-dev`, migrations
(must be additive/backward compatible) and caches, swaps `current`, restarts the Node app and the queue,
checks `/api/v1/health` and the home page, and restores the previous release automatically if the
check fails. Manual rollback: `DEPLOY_SSH=… scripts/deploy-cpanel.sh --rollback` (code only;
migrations are not reversed — restore a DB dump from `~/onusondhan/backups` if ever needed).

The Node app's environment (`API_INTERNAL_URL`, `INTERNAL_API_TOKEN`, `NODE_ENV`) is stored in
cPanel → Setup Node.js App, not in the release.

## Shared-hosting limits and deliberate differences

- 1 GB memory / 20 entry processes per account (CloudLinux LVE). Builds run on the developer machine.
- No ClamAV: `MALWARE_SCANNER=none` — uploads are type/size checked and kept private, marked `not_scanned`.
- PHP upload limits are set with `php_value` in `backend/public/.htaccess` (LSAPI ignores `.user.ini` here).
- Mail uses the local cPanel MTA (`MAIL_MAILER=sendmail`, from `no-reply@onusondhan.com`) until an SMTP
  provider is configured; add SPF/DKIM records at name.com (cPanel → Email Deliverability shows them).
- Backups: daily DB dumps on the same account plus the host's cPanel backups — copy them off-site too.
