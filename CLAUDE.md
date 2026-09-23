# Onushondhan.com — Claude Master Project Instructions

## Mission
Build the complete production-ready Onushondhan.com platform from this specification. Work autonomously through implementation, testing, hardening, documentation, and deployment preparation. Do not stop after scaffolding. Continue until the Definition of Done in `docs/DEFINITION_OF_DONE.md` is satisfied.

## Product identity
Onushondhan is a digital publishing, hosting, discovery, and community platform for independent/solo investigators. It is not itself an investigative organization. Investigators are responsible for their own reports, claims, evidence, sources, permissions, legality, and consequences. The platform provides technology, publishing, moderation, discovery, and community infrastructure and does not by default independently verify or endorse every report.

## Core engineering decisions
- Backend: Laravel 12, PHP 8.3+
- Frontend: Next.js App Router, React, TypeScript
- Database: MySQL 8.0+
- Admin panel: Filament
- Cache/queue: Laravel/database/file based mechanisms initially; Redis is explicitly deferred
- File storage: local/private server storage initially; S3/object storage is explicitly deferred
- Search: MySQL full-text/search initially; OpenSearch is explicitly deferred
- Containers: do not add Docker in the initial release; Docker is deferred
- Web: Nginx or Apache with Laravel served only from `backend/public`
- API: REST JSON, versioned under `/api/v1`
- Authentication: secure session/token approach appropriate for the split frontend/backend architecture; do not invent an unsafe custom auth mechanism
- Testing: Pest/PHPUnit for Laravel; Playwright for browser E2E; frontend unit tests where appropriate

## Non-negotiable autonomy rules
1. Do not repeatedly ask the user what to implement when the answer is already covered by these documents.
2. Make reasonable engineering decisions using the specification and record them in `docs/DECISIONS.md`.
3. Never leave TODO/FIXME placeholders for core functionality.
4. Do not claim a feature is complete until it is implemented and tested.
5. Run the relevant test/lint/build commands after meaningful changes and fix failures before moving on.
6. Prefer simple production-safe implementations over speculative infrastructure.
7. Do not introduce Redis, S3, OpenSearch, Docker, Kubernetes, microservices, or other infrastructure in the initial release.
8. Do not expose private evidence or protected investigator identity through predictable URLs or client-side data.
9. Do not use an investigator support count as a measure of factual accuracy, truth, or investigative quality.
10. Legal text must be written as product-policy templates and clearly flagged for legal review; do not represent it as jurisdiction-specific legal advice.
11. Never hard-code credentials, API keys, passwords, production hosts, or secrets.
12. Do not execute destructive production actions automatically. Deployment automation may prepare, migrate, restart, and verify a release, but production secrets/DNS/payment credentials must come from environment configuration.

## Required implementation order
Follow `docs/IMPLEMENTATION_PLAN.md` in order unless dependency analysis shows a safer order. Maintain a live status in `docs/BUILD_STATUS.md`.

## Before coding
- Inspect the repository and existing files if any.
- Read `docs/PRODUCT_REQUIREMENTS.md`, `docs/ARCHITECTURE.md`, `docs/DATABASE.md`, `docs/SECURITY.md`, `docs/ROLES_PERMISSIONS.md`, and `docs/DEFINITION_OF_DONE.md`.
- If Laravel exists, install/configure Laravel Boost as appropriate and use Laravel conventions. Current Laravel documentation describes Laravel 12 as agent-ready and documents Boost integration with Claude Code. 
- Preserve good existing code where possible; do not rewrite working modules merely for style.

## Code quality
- PHP: PSR-12, strict types where practical, Form Requests, Policies, Services, Jobs, Resources, Events, Notifications, typed DTOs where useful.
- Use database transactions around multi-step state changes.
- Use indexes intentionally and avoid N+1 queries.
- Validate uploads by MIME/content, size, extension allowlists, storage isolation, and malware scanning hook/interface.
- Escape output and protect against XSS, SQL injection, CSRF, SSRF, mass assignment, IDOR, broken access control, and unsafe redirects.
- Prefer UUID/ULID/public identifiers where appropriate so public IDs do not reveal sequential database IDs.
- Keep public/private boundaries explicit in policies and API resources.
- Use soft deletes where appropriate for user-generated content and auditable records.
- Record immutable audit events for high-risk actions.

## Frontend expectations
- Responsive and accessible.
- Server-render where it improves SEO and performance; client components only where interactivity is needed.
- Use a consistent design system and reusable components.
- Public report pages must be indexable unless the report status/policy says otherwise.
- Do not render private investigator identity in public HTML, API payloads, page source, analytics payloads, or client logs.

## Investigation lifecycle
Draft -> Preview -> Responsibility Confirmation -> Published -> Updated / Withdrawn / Archived.
Admin may Restrict or Remove according to policy. Preserve history and auditability.

## Evidence rules
Evidence may be PDF, image, video, audio, or document. Store metadata, SHA-256 hash, scan status, visibility, timestamps, and ownership. Use private storage paths for non-public evidence. Generate safe download URLs server-side and log access/download events.

## Anonymous/protected identity
Investigators may publish under real name or protected public identity. Internally retain the account relationship. Do not promise absolute anonymity. Identity access must be tightly permissioned and audited.

## Moderation is not verification
Moderation handles spam, abuse, prohibited content, privacy/copyright complaints, malicious files, and policy violations. It does not mean Onushondhan has independently verified factual claims.

## Git and change management
- Keep commits focused.
- Never commit `.env` or secrets.
- Update relevant documentation with architectural or behavior changes.
- Before finalizing a module, record changed files, tests run, known limitations, and any required manual secret/configuration steps in `docs/BUILD_STATUS.md`.

## Completion standard
The project is complete only when:
- application builds successfully;
- migrations run from a clean database;
- seed data works;
- authentication and RBAC work;
- all core user/investigator/report/evidence/comment/tip/support/follow/moderation/admin workflows work end-to-end;
- security tests cover major authorization boundaries;
- frontend production build succeeds;
- E2E smoke tests pass;
- production deployment configuration and rollback procedure are documented;
- health check and monitoring hooks exist;
- legal-policy templates exist with legal-review notices;
- no core feature remains as a placeholder.

## First instruction to Claude
Read all files in this package. Then inspect the repository. Create or update the implementation according to the package. Work module-by-module without asking for design confirmation unless an external secret, legal approval, irreversible production action, or genuinely missing business fact is required. For everything else, decide, implement, test, document, and continue.
