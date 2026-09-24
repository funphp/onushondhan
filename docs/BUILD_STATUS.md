# Build Status

_Last verified: 2026-09-24 (UTC), against the repository and the automated suites listed below._

| Phase | Status | Key implementation | Tests |
|---|---|---|---|
| 1 Bootstrap | COMPLETE | `backend/` Laravel 12 + Sanctum, `frontend/` Next.js 16; `/api/v1` versioning; error envelope + `X-Request-Id` (`bootstrap/app.php`, `AssignRequestId`); security headers; health endpoint; logging | `PublicContentTest`, `RegistrationTest` (envelope) |
| 2 Auth & RBAC | COMPLETE | register/login/logout, HMAC email verification, password reset, password change, optional phone verification, profile, deactivation; RBAC over spec tables (`HasRoles`, `RolesAndPermissionsSeeder`); agreements with version tracking + re-acceptance gate; admin TOTP MFA; session revocation | `Feature/Auth/*`, `AdminPanelTest` |
| 3 Investigator | COMPLETE | onboarding, real-name / protected identity, encrypted private identity (owner + audited staff access), avatar sanitisation, ownership policies | `OnboardingTest`, `AuthorizationBoundariesTest` |
| 4 Investigation core | COMPLETE | categories/tags/locations, working copy + autosave (revision conflicts), preview, responsibility confirmation, immutable versions, update with change note, withdraw, archive, draft delete, restrict/remove via moderation, diff | `LifecycleTest`, E2E 04 |
| 5 Evidence | COMPLETE | `FileVault` abstraction (local private disk), content-based validation, SHA-256, random keys, scan hook (`none`/ClamAV) + quarantine, metadata-stripped image renditions, visibility (public/restricted/private, default private), versioning, signed links, access logs | `UploadSecurityTest`, `AccessTest`, E2E 04 |
| 6 Public discovery | COMPLETE | home rails, MySQL full-text search + filters, category/tag/location pages, investigator directory/profiles, investigation page (snapshot render, tombstones), related, SEO (metadata, canonical, OG/Twitter + generated OG images, JSON-LD Article/Breadcrumb/WebSite/Organization/ProfilePage, sitemap, robots) | `SearchTest`, `PublicContentTest`, E2E 01 |
| 7 Community | COMPLETE | threaded comments (edit/delete own, votes, reports + auto-hide), bookmarks, reactions, follows, one-per-user support with cooldown + audit, neutral leaderboards, share tracking | `CommentsTest`, `EngagementTest`, E2E 03 |
| 8 Tips | COMPLETE | guest (anonymous to platform, access code hash) and member (optionally anonymous to investigator) tips, attachments (scanned), inbox statuses, two-way messaging, strict recipient scoping, need-to-know staff oversight | `TipsTest`, E2E 05 |
| 9 Moderation | COMPLETE | complaints (guest/member, factual-dispute wording), status workflow + notifications, moderation actions on investigation/comment/evidence/user/investigator with role hierarchy, append-only records, hash-chained audit log + `audit:verify` | `ComplaintsTest`, `ModerationActionsTest`, `AuditChainTest`, E2E 06 |
| 10 Admin/CMS | COMPLETE | Filament panel: KPIs, moderation queue, engagement chart; resources for users, investigators, investigations, evidence, comments, tips, supporters, followers, complaints, moderation actions, CMS pages, agreement versions (publish → immutable), categories, roles/permissions, settings, audit/login/security logs; audited config changes | `AdminPanelTest`, E2E 06 |
| 11 Hardening | COMPLETE | IDOR/BOLA, privacy-leak, header, mass-assignment, XSS, SQLi, upload, rate-limit, session tests; axe WCAG scans; fixes from E2E (session resurrection, sticky evidence visibility, partial relation) | `Feature/Security/*`, E2E 07 |
| 12 Release | COMPLETE (code/config) | Nginx, PHP-FPM, systemd (Next, queue, scheduler, backup), logrotate, deploy/rollback/backup/restore/health scripts, CI | see `docs/RELEASE_CHECKLIST.md` |

## Commands executed for this status

```
backend:  composer validate · vendor/bin/pint --test · vendor/bin/phpstan analyse (level 5) · composer audit
          php artisan migrate:fresh --seed (clean DB) · APP_ENV=production migrate:fresh --seed (no demo data)
          php artisan test → 145 passed (666 assertions)
frontend: npm run lint · npm run typecheck · npm test → 10 passed · npm run build · npm audit --omit=dev → 0 vulnerabilities
e2e:      scripts/e2e-backend.sh (fresh onushondhan_e2e) + npx playwright test → 28 passed (10 journeys + 15 axe scans + setup)
```

## Local deployment

`http://onusondhan.local` (Vagrant VM, Apache + PHP 8.3-FPM + Next.js under systemd) — see `docs/LOCAL_VM.md`.
The Playwright suite was also run against it: 28/28 passed after fixing an empty-sitemap-at-build issue.

## Known limitations

- **PHP version:** developed on PHP 8.2.21; the full suite and Larastan also pass on PHP 8.3.34 (`/opt/php8.3` in the VM), which serves the local site. CI and production target 8.3 (ADR-008).
- **Media metadata:** only images are re-encoded without metadata; video/audio/PDF/office files are served as uploaded (investigators are warned in the UI).
- **Malware scanning:** ClamAV integration is implemented and tested through a fake scanner; no scanner runs in local dev (`MALWARE_SCANNER=none` → `not_scanned`). Enable on the server.
- **SMS:** phone verification uses a log driver; a production SMS provider must be bound to `App\Contracts\SmsSender`.
- **OG images** use the default Latin font; Bengali titles would need an embedded Bengali font.
- **Admin panel CSP** allows inline scripts/styles (Livewire/Alpine requirement); the public site and API have strict CSPs.
- Investigators moderate discussion on their reports by reporting comments; there is no investigator-side comment-hiding tool (staff moderate).
- Backups/restores are scripted but must be rehearsed on the target infrastructure.

## External inputs required

See `docs/MANUAL_EXTERNAL_INPUTS.md`.
