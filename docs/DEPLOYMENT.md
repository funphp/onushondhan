# Production Deployment — Linux, no Docker

Stack: Nginx → Next.js (Node, systemd) for pages · Nginx → PHP-FPM (Laravel) for `/api`, `/sanctum`,
`/admin` · MySQL 8 · database queue worker (systemd) · scheduler (systemd timer) · local private vault.
All templates are in `deploy/`, scripts in `scripts/`.

## 1. One-time server preparation

```bash
# Packages (Ubuntu 22.04/24.04 example)
sudo apt install nginx mysql-server php8.3-fpm php8.3-{cli,mysql,mbstring,xml,curl,zip,gd,intl,bcmath} \
  composer git unzip clamav-daemon age
# Node.js 20.11+ (22 LTS recommended) from NodeSource or your distribution.

sudo useradd --system --create-home --home-dir /var/www/onushondhan onushondhan
sudo -u onushondhan mkdir -p /var/www/onushondhan/{releases,shared/storage}
sudo mkdir -p /etc/onushondhan /var/log/onushondhan && sudo chown onushondhan /var/log/onushondhan
```

MySQL: create the database (`utf8mb4`, `utf8mb4_unicode_ci`) and an application user with rights on that
schema only; a separate read-only user for backups.

Secrets (from your secret manager — never commit):
- `/var/www/onushondhan/shared/backend.env` from `backend/.env.example` (see `docs/ENVIRONMENT.md`;
  generate `APP_KEY` with `php artisan key:generate --show`).
- `/var/www/onushondhan/shared/frontend.env` from `frontend/.env.example`.
- `/etc/onushondhan/frontend.env` (runtime env for the Next.js unit: `API_INTERNAL_URL=http://127.0.0.1`).
- `/etc/onushondhan/backup.env`.

Install templates (edit domain/paths first):

```bash
sudo cp deploy/nginx/onushondhan.conf /etc/nginx/sites-available/ && sudo ln -s ../sites-available/onushondhan.conf /etc/nginx/sites-enabled/
sudo cp deploy/php-fpm/onushondhan.conf /etc/php/8.3/fpm/pool.d/
sudo cp deploy/systemd/*.service deploy/systemd/*.timer /etc/systemd/system/
sudo cp deploy/logrotate/onushondhan /etc/logrotate.d/
sudo systemctl daemon-reload
sudo systemctl enable --now onushondhan-scheduler.timer onushondhan-backup.timer
sudo systemctl enable onushondhan-queue onushondhan-next
```

TLS: obtain certificates (e.g. `certbot certonly --webroot -w /var/www/letsencrypt -d <domain>`).
Enable the HSTS header in the Nginx file only once HTTPS is permanent for the domain.

Sudoers (deploy user may only restart the app units):
```
onushondhan ALL=(root) NOPASSWD: /bin/systemctl reload php8.3-fpm, /bin/systemctl restart onushondhan-queue onushondhan-next
```

Malware scanning: `sudo systemctl enable --now clamav-daemon clamav-freshclam`, then set
`MALWARE_SCANNER=clamav` and `EVIDENCE_REQUIRE_CLEAN_SCAN=true`.

## 2. Deploy a release

```bash
DEPLOY_REPO=git@github.com:<org>/onushondhan.git scripts/deploy.sh <tag-or-branch>
```

The script: clones into `releases/<timestamp>` → links shared `.env` and `storage` → `composer install
--no-dev` (also publishes Filament assets) → `npm ci && npm run build` (standalone output) → refuses
`APP_DEBUG=true` → `php artisan migrate --force` → `optimize` + `filament:optimize` → atomically switches
`current` → reloads PHP-FPM, restarts queue and Next.js → polls `/api/v1/health`. If health fails it
rolls the **code** back to the previous release automatically. It records `releases.log` and keeps the
last 5 releases.

First deploy only: `cd /var/www/onushondhan/current/backend && php artisan db:seed --force` seeds roles,
permissions, categories, settings and the agreement/CMS templates (no demo data in production).
Then create the first super admin (see `docs/MANUAL_EXTERNAL_INPUTS.md`).

## 3. Migrations policy

Migrations run before traffic switches, so they must be **backward compatible** with the previous
release (expand → migrate data → contract in a later release). Never ship a destructive migration
together with the code that stops using the column.

## 4. Rollback

```bash
scripts/rollback.sh            # previous release
scripts/rollback.sh 20260924120000
```

- **Code:** switches `current`, re-caches config/routes, restarts queue and Next.js.
- **Database:** migrations are *not* reversed automatically. Because migrations are additive, the
  previous code runs against the newer schema. If a migration must be undone, restore from backup
  (docs/OPERATIONS.md) or run a reviewed `php artisan migrate:rollback --step=1` manually.
- **Frontend:** each release carries its own `.next` build; rollback restarts the previous one.
- **Storage:** the vault is shared across releases and not rolled back; evidence rows reference keys
  that remain valid.
- **Queue:** `queue:restart` makes workers load the rolled-back code; failed jobs can be retried with
  `php artisan queue:retry all` after review.
- **Caches:** `php artisan optimize` rebuilds config/route/view caches for the active release.

## 5. Verification after deploy

```bash
SITE_URL=https://<domain> scripts/healthcheck.sh
curl -sI https://<domain>/ | grep -i content-security-policy
cd /var/www/onushondhan/current/backend && php artisan about && php artisan audit:verify
```
