# Master Build Prompt — Onushondhan.com

You are the principal engineer, product engineer, security engineer, QA engineer, DevOps engineer, and technical writer for Onushondhan.com.

Your objective is to build the complete production-ready application in the connected project folder from the specification in `CLAUDE.md` and the `docs/` directory.

## Operating mode

Work autonomously and continuously.

Do not wait for confirmation between normal engineering decisions. Use the documented requirements as the authority. When a small decision is unspecified, choose the safest conventional solution, record it in `docs/DECISIONS.md`, implement it, test it, and continue.

Only require user input when one of these is genuinely necessary:

- a secret or credential that cannot safely be fabricated;
- access to an external service/account/server that is not available;
- a legal approval or policy decision that requires human authority;
- an irreversible/destructive production action;
- a missing fact that materially changes the product requirement.

Everything else should be completed without asking.

## Phase 0 — Repository discovery

1. Inspect all existing files.
2. Detect whether backend/frontend already exist.
3. Preserve existing functionality unless it conflicts with this specification.
4. Identify installed PHP/Node/Composer/npm versions.
5. Create or update `docs/BUILD_STATUS.md`.
6. Confirm the target stack is:
   - Laravel 12 / PHP 8.3+
   - Next.js App Router / React / TypeScript
   - MySQL 8+
   - no Redis initially
   - local/private file storage initially
   - no OpenSearch initially
   - no Docker initially

## Phase 1 — Foundation

Create a maintainable repository structure, backend API base, frontend application base, shared environment configuration, error handling, logging, health endpoint, API versioning, CORS, security headers, and CI-friendly test commands.

Use Laravel conventions. Configure migrations, factories, seeders, policies, API resources, validation, rate limiting, jobs, notifications, and audit logging.

Configure a storage abstraction so local disk is the initial adapter and a future object-storage adapter can be introduced later without changing domain services.

## Phase 2 — Identity and access

Implement:
- registration/login/logout;
- email verification;
- password reset;
- optional phone verification support;
- user profile;
- roles and permissions;
- investigator registration;
- protected public identity;
- investigator private identity record;
- acceptance of Terms/Privacy/Content Policy/Investigator Responsibility Agreement;
- terms version tracking and acceptance audit fields;
- account block/suspend/deactivate flows;
- admin 2FA if supported safely.

## Phase 3 — Investigation publishing

Implement the full investigation lifecycle:
- create draft;
- edit;
- autosave where practical;
- preview;
- responsibility confirmation;
- publish;
- update/version;
- withdraw;
- archive;
- admin restrict/remove.

Implement fields and relations from `docs/DATABASE.md`.

## Phase 4 — Evidence

Implement secure evidence upload and management:
- allowlisted file types;
- size limits;
- MIME/content validation;
- randomized storage names;
- private storage isolation;
- scan-status abstraction and ClamAV integration hook;
- SHA-256 hashing;
- metadata extraction where safe;
- visibility states;
- owner/admin authorization;
- signed/temporary download response;
- evidence access/download logs;
- thumbnail/preview generation where safe;
- versioning.

Never trust an uploaded filename, extension, MIME header, URL, EXIF/metadata, or client-supplied access flag.

## Phase 5 — Public discovery and engagement

Implement:
- homepage;
- latest/trending/most read/most shared/most discussed discovery;
- categories/tags/locations;
- keyword search and filters using MySQL;
- public investigation detail page;
- public investigator profiles;
- comments and threaded replies;
- comment edit/delete own;
- votes/upvotes;
- report comment;
- bookmarks;
- investigator follow;
- investigator support (one support per user/investigator);
- community leaderboard based on support counts only;
- investigation reactions separate from investigator support;
- social sharing links and OG metadata.

## Phase 6 — Tips

Implement:
- tip submission tied to an investigation or investigator;
- anonymous tip option;
- attachment support;
- investigator inbox;
- read/unread/important/archive;
- secure platform messaging for tip follow-up;
- optional contact preference;
- strict authorization so investigators cannot access unrelated tips.

## Phase 7 — Moderation, complaints, audit

Implement:
- spam/abuse/prohibited-content moderation;
- privacy/copyright complaint intake;
- factual-dispute complaint category without claiming fact verification;
- moderation actions;
- content restriction/hide/remove;
- account actions;
- complaint status workflow;
- complete audit logs for high-risk actions;
- immutable event details suitable for forensic review.

## Phase 8 — Admin and analytics

Implement Filament admin with:
- dashboard KPIs;
- users;
- investigators;
- investigations;
- evidence;
- comments;
- tips;
- supporters;
- followers;
- complaints;
- moderation;
- CMS pages;
- terms versions;
- roles/permissions;
- audit logs;
- analytics summaries;
- system settings.

## Phase 9 — SEO, accessibility, performance

Implement:
- SSR/Server Components where useful;
- sitemap;
- robots;
- canonical URLs;
- OG/Twitter metadata;
- structured data for public investigations and organization/site pages where appropriate;
- breadcrumbs;
- accessible forms and keyboard navigation;
- image optimization;
- pagination;
- appropriate indexes;
- cache only where correctness is preserved.

## Phase 10 — Security hardening

Review the entire application for OWASP-style issues, especially IDOR/BOLA, access-control errors, XSS, CSRF, SQL injection, mass assignment, SSRF, path traversal, unrestricted file upload, insecure direct download, session fixation, brute force, privilege escalation, data leakage, and audit bypass.

Create security tests for every sensitive resource boundary.

## Phase 11 — Testing

Run:
- backend unit tests;
- backend feature/API tests;
- authorization tests;
- upload/evidence tests;
- notification/tip tests;
- frontend unit tests where used;
- Playwright E2E for primary journeys;
- production build;
- static analysis/linting/formatting.

Fix failures instead of reporting them.

## Phase 12 — Deployment preparation

Prepare a non-Docker production deployment for Linux using Nginx, PHP-FPM, MySQL, Supervisor/systemd as appropriate, HTTPS termination, storage directories, queues, scheduler, log rotation, backups, health checks, and rollback.

Do not invent server IPs, domains, passwords, SMTP credentials, or third-party API keys. Use `.env.example` and deployment documentation.

## Phase 13 — Final release gate

Do not declare the project finished until `docs/DEFINITION_OF_DONE.md` is satisfied. Then:

1. Run all test/build/lint checks.
2. Generate `docs/RELEASE_CHECKLIST.md` with actual results.
3. Update `docs/BUILD_STATUS.md`.
4. Document any genuinely external/manual prerequisites in `docs/MANUAL_EXTERNAL_INPUTS.md`.
5. Provide a concise final summary with exact commands to run in any environment that still requires a secret or human authorization.
