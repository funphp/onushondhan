# Testing Strategy

## Backend
- unit tests for services/domain rules;
- feature tests for every API family;
- policy tests for authorization;
- model relationship tests where behavior is non-trivial;
- upload/evidence security tests;
- job/notification tests;
- migration/seed smoke test on clean database.

## Frontend
- component tests for reusable interactive components where practical;
- accessibility checks for major forms;
- API error-state tests.

## End-to-end
Playwright journeys:
1. visitor discovers and reads a published investigation;
2. user registration/login/email verification;
3. comment/reply/report;
4. bookmark/follow/support;
5. investigator creates/publishes a report;
6. investigator uploads evidence and accesses only owned private evidence;
7. anonymous tip submission;
8. investigator reads/processes tip;
9. moderator reviews a complaint;
10. admin manages user/content and sees audit log.

## Security tests
Explicitly test:
- unauthorized access;
- IDOR/BOLA;
- role escalation;
- rate limits;
- malicious uploads;
- unsafe filenames/paths;
- XSS payload rejection/sanitization;
- CSRF behavior;
- private API field leakage;
- private evidence download authorization.

## Release gate
All automated checks must pass before production deployment preparation is declared complete.

---

# How to run the suites (current build)

| Suite | Command | Needs |
|---|---|---|
| Backend feature/unit/security (Pest) | `cd backend && php artisan test` | MySQL test DB `onushondhan_test` (see `phpunit.xml`), PHP 8.2+ |
| Backend style / static analysis | `vendor/bin/pint --test` · `vendor/bin/phpstan analyse` | |
| Frontend unit (Vitest) | `cd frontend && npm test` | Node 20.11+ |
| Frontend lint / types / build | `npm run lint` · `npm run typecheck` · `npm run build` | |
| E2E + accessibility (Playwright + axe) | `scripts/e2e-backend.sh` (isolated API on :8001 with a fresh `onushondhan_e2e` DB) then `cd frontend && npx playwright test` | Chromium (`npx playwright install chromium`) |
| Everything | `scripts/verify-project.sh` (`RUN_E2E=1` to include E2E) | |

CI (`.github/workflows/ci.yml`) runs all of the above on PHP 8.3 / MySQL 8 / Node 22.

## Coverage map

| Area | Backend (`backend/tests`) | E2E (`frontend/e2e`) |
|---|---|---|
| Registration, login, logout, verification, reset, brute force, session revocation | `Feature/Auth/*` | `02-registration` |
| Agreements & re-acceptance | `Auth/AgreementsTest`, `NotificationsTest` | — |
| Investigator onboarding, protected identity | `Investigator/OnboardingTest` | `04-investigator` |
| Lifecycle (draft/autosave/preview/publish/update/withdraw/archive/delete), versions & diff | `Investigation/LifecycleTest` | `04-investigator` |
| Evidence upload security & access control | `Evidence/UploadSecurityTest`, `Evidence/AccessTest` | `04-investigator` |
| Discovery, search, taxonomy, sitemap | `Search/SearchTest`, `Security/PublicContentTest` | `01-visitor` |
| Comments, votes, reports, bookmarks, reactions, follow, support, leaderboards | `Community/*` | `03-community` |
| Tips (guest & member), inbox, messaging, attachments | `Tips/TipsTest` | `05-tips` |
| Complaints, moderation actions, role hierarchy | `Moderation/*` | `06-moderation` |
| Admin panel access, MFA, audited actions | `Admin/AdminPanelTest` | `06-moderation` |
| IDOR/BOLA, privacy leakage, headers, audit chain | `Security/*` | `04`, `05` |
| Notifications | `NotificationsTest` | — |
| Accessibility (WCAG 2.x A/AA, serious+critical) | — | `07-accessibility` (15 pages) |
| Markdown sanitisation, safe links/redirects, API client, form a11y | `frontend/src/**/*.test.ts(x)` | — |
