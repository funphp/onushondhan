# Onushondhan.com

Onushondhan is a digital publishing, hosting, discovery and community platform for **independent
investigators**. Investigators publish reports with evidence, sources, findings and timelines under
their real name or a protected public identity; readers discover, discuss, follow and support them and
send tips securely. **Publication is not verification**: investigators are responsible for their own
reports, and moderation enforces platform policy rather than fact-checking.

```
Browser ──► Nginx ──► Next.js 16 (SSR pages)          frontend/
                 └──► PHP-FPM ► Laravel 12 REST API    backend/   /api/v1, /sanctum
                                 └ Filament 4 admin     backend/   /admin
                                 └ MySQL 8 · database queue · private local vault
```

No Redis, S3, OpenSearch or Docker in this release (see `docs/DECISIONS.md`).

## Repository

| Path | Contents |
|---|---|
| `backend/` | Laravel API, Filament admin, jobs, policies, migrations, seeders, Pest tests |
| `frontend/` | Next.js App Router (TypeScript, Tailwind), Vitest unit tests, Playwright E2E + axe |
| `docs/` | Specification, ADRs (`DECISIONS.md`), API, environment, deployment, operations, security, status |
| `deploy/` | Nginx, PHP-FPM, systemd (Next.js, queue, scheduler, backup), logrotate templates |
| `scripts/` | `deploy.sh`, `rollback.sh`, `backup.sh`, `restore.sh`, `healthcheck.sh`, `verify-project.sh`, `e2e-backend.sh` |

## Local development

Requirements: PHP 8.2+ (8.3 in production) with `pdo_mysql, mbstring, intl, gd, exif, fileinfo, zip,
sodium`; Composer 2; MySQL 8; Node 20.11+.

```bash
# Backend
cd backend
cp .env.example .env            # set DB_* for your local MySQL
composer install
php artisan key:generate
php artisan migrate --seed      # roles, categories, legal templates + LOCAL demo data
php artisan serve               # http://127.0.0.1:8000  (admin panel: /admin)
php artisan queue:work          # optional; or QUEUE_CONNECTION=sync

# Frontend
cd ../frontend
cp .env.example .env.local      # API_INTERNAL_URL=http://127.0.0.1:8000
npm install
npm run dev                     # http://localhost:3000
```

Demo accounts (local only, password `Onushondhan#2026`): `reader@`, `reader2@`, `investigator@`,
`investigator2@`, `moderator@`, `admin@`, `superadmin@onushondhan.test`. Emails are written to
`backend/storage/logs/mail.log` when `MAIL_MAILER=log` and `MAIL_LOG_CHANNEL=mail`.

## Quality gates

```bash
scripts/verify-project.sh               # backend: pint, phpstan, pest, composer audit; frontend: lint, types, vitest, build, npm audit
RUN_E2E=1 scripts/verify-project.sh     # + Playwright journeys and accessibility (needs scripts/e2e-backend.sh running)
```

## Documentation

Start with `CLAUDE.md` (principles), `docs/PRODUCT_REQUIREMENTS.md`, `docs/ARCHITECTURE.md`,
`docs/DECISIONS.md`, `docs/API.md`, `docs/SECURITY.md`, `docs/DEPLOYMENT.md`, `docs/OPERATIONS.md`,
`docs/BUILD_STATUS.md` and `docs/RELEASE_CHECKLIST.md`. External prerequisites that require a human
(domain, SMTP, TLS, legal review, first admin) are listed in `docs/MANUAL_EXTERNAL_INPUTS.md`.

Legal and policy texts in `backend/resources/content/legal/` are **templates that require review by
qualified legal counsel** before production use.
