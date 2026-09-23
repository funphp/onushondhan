# Release Checklist

_Generated at the release gate, 2026-09-24 (UTC). Results are from actual runs in this repository._

| Item | Result | Evidence |
|---|---|---|
| Backend tests | ✅ 145 passed, 666 assertions | `php artisan test` (MySQL 8.0.25, PHP 8.2.21) |
| Backend static checks | ✅ Pint clean · Larastan level 5: no errors · `composer validate` ok · `composer audit`: no advisories | |
| Frontend tests/build | ✅ ESLint clean · `tsc` clean · Vitest 10 passed · `next build` ok (32 routes) · `npm audit --omit=dev`: 0 vulnerabilities | |
| E2E tests | ✅ 28 passed: 10 journeys from docs/TESTING.md + 15 axe accessibility scans + auth setup | `npx playwright test` against `scripts/e2e-backend.sh` |
| Database migration test | ✅ `migrate:fresh --seed` on a clean DB (10 migrations); production-mode seed creates 0 users, 5 roles, 36 permissions, 4 agreement templates, 9 CMS pages, 37 categories | |
| Authorization/security tests | ✅ `tests/Feature/Security/*`, IDOR checks in evidence/tips/investigation suites and E2E 04/05 | |
| Upload/evidence tests | ✅ `UploadSecurityTest` (disguised PHP, SVG, HTML, EXE, ELF, shell, double extension, script text, content/extension mismatch, size, duplicate, infected quarantine), `AccessTest`, E2E 04 | |
| Accessibility review | ✅ automated (axe WCAG 2.x A/AA, serious+critical = 0 on 15 core pages); keyboard-navigable components (skip link, focus rings, ARIA tabs, native dialogs, labelled fields) | Manual screen-reader pass recommended before launch |
| Production env review | ✅ template + guide (`backend/.env.example`, `docs/ENVIRONMENT.md`); deploy script refuses `APP_DEBUG=true` | real values: operator |
| Nginx config review | ✅ `deploy/nginx/onushondhan.conf` (only `backend/public/index.php` executable, dotfiles denied, body limit, rate limits, TLS) | HSTS to enable once HTTPS is permanent |
| PHP-FPM config review | ✅ `deploy/php-fpm/onushondhan.conf` | |
| Next.js service review | ✅ `deploy/systemd/onushondhan-next.service` (standalone build, loopback only, hardened) | |
| Queue/scheduler review | ✅ `onushondhan-queue.service` (database driver), `onushondhan-scheduler.timer` (every minute) | |
| Backup/restore verification | ⚠️ scripts ready (`backup.sh`, `restore.sh`, age encryption, checksums); **rehearsal must run on the target server** | operator |
| Health check verification | ✅ `/api/v1/health` + `scripts/healthcheck.sh` verified against a production build | |
| Rollback verification | ✅ `scripts/rollback.sh` + automatic code rollback on failed post-deploy health check (syntax-checked; first real exercise on the target server) | |
| Security headers | ✅ site CSP/nosniff/DENY/referrer/permissions; API `default-src 'none'; sandbox`, `no-store`, `X-Request-Id` | verified with curl |
| Legal-policy review status | ⚠️ Terms, Privacy, Content Policy, Investigator Responsibility Agreement and Contact page are **templates flagged "requires legal review"** (shown publicly) | counsel |
| Manual external inputs recorded | ✅ `docs/MANUAL_EXTERNAL_INPUTS.md` | |

Benign log noise: `[WebServer] ⨯ Error: The destination stream closed early.` during E2E is Next.js reporting
streamed responses abandoned by the browser during rapid test navigation; no test request failed.
