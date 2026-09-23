# Architectural Decision Log

## ADR-001 — MySQL
Status: Accepted.

MySQL is the initial primary relational database.

## ADR-002 — Redis deferred
Status: Accepted.

Do not require Redis for the first release. Use Laravel-supported database/file mechanisms where practical.

## ADR-003 — Object storage deferred
Status: Accepted.

Use local/private server storage initially. Keep a storage abstraction for future S3/R2 migration.

## ADR-004 — OpenSearch deferred
Status: Accepted.

Use MySQL search/full-text initially. Design indexes and query services so a future dedicated search engine can be inserted later.

## ADR-005 — Docker deferred
Status: Accepted.

Initial deployment is conventional Linux + Nginx/PHP-FPM/Node/MySQL.

## ADR-006 — Publishing responsibility
Status: Accepted.

The investigator is responsible for their own report and supporting material. Onushondhan provides platform infrastructure and moderation; publication does not equal factual verification or endorsement.

## ADR-007 — Support versus accuracy
Status: Accepted.

Support counts are community engagement metrics and must not be presented as factual accuracy, truth, or investigative quality.

## ADR-008 — PHP runtime floor 8.2 in development, 8.3+ in production/CI
Status: Accepted (environment constraint).

The only PHP available in the local development VM (Ubuntu 20.04) is 8.2.21; PHP 8.3 packages are
not published for that release and none could be installed on the host. Laravel 12, Filament 4 and
Pest 3 all support PHP ^8.2, so `composer.json` declares `"php": "^8.2"` and the code avoids
8.3-only syntax. **Production and CI target PHP 8.3** (see `deploy/`, `.github/workflows/ci.yml`).
Revisit (raise to `^8.3`) once every environment runs 8.3.

## ADR-009 — Authentication: Sanctum SPA cookie sessions on a single origin
Status: Accepted.

The browser talks only to the site origin. `/api/*` and `/sanctum/*` are routed to Laravel (Next.js
rewrites in development, Nginx in production), so the Laravel session and `XSRF-TOKEN` cookies are
first-party, HttpOnly/SameSite=Lax, and no CORS or bearer tokens are needed in the browser. Server
components fetch only public data (no user cookies) so nothing personal is cached. Filament shares
the same session. No custom crypto: Laravel hashing (Argon2id), password broker, signed URLs.

## ADR-010 — Email verification links are HMAC-signed and land on the frontend
Status: Accepted.

`AuthService::verificationUrl()` signs `(user id, sha1(email), expiry)` with `APP_KEY` and links to
`FRONTEND_URL/verify-email`, which posts the parameters to the API. Verification does not require
being signed in (links may be opened on another device).

## ADR-011 — Versioned publishing: working copy + immutable published snapshots
Status: Accepted.

Owners edit a working copy (autosaved, optimistic `revision` check). Each publication (first or
update) creates an immutable `investigation_versions` snapshot (content, findings, recommendations,
timeline, **public** sources, entities, taxonomy meta, identity snapshot) plus an
`investigation_publish_confirmations` row. Public pages render the current snapshot; search uses the
`published_*` projection columns. Edits to a published report are therefore private until "Publish
update" (with a public change note). Taxonomy relations (tags/locations) are live metadata. Superseded
version *content* is kept for audit but only version metadata is public (so a correction removing
personal data is not re-exposed).

## ADR-012 — Public attribution always uses the investigator's current public identity
Status: Accepted (privacy).

Snapshots record the identity at publication for audit, but public pages show the *current* public
name. When a public name changes, the slug is regenerated and **no redirect** is kept from the old
slug, so an investigator who moves from real name to a protected identity cannot be linked through
old URLs. Community activity uses a separate public `username` (never the private account `name`);
comments by the investigation's own investigator display the public investigator identity.

## ADR-013 — Non-sequential investigation ids
Status: Accepted.

`docs/SEO.md` suggests `INV-2026-00123`; sequential numbers would leak publication volume and ease
enumeration, so ids are `INV-YYYY-` + 8 random Crockford-base32 characters. Canonical URL:
`/investigation/{public_id}/{slug}`; slug mismatches 301 to canonical.

## ADR-014 — Evidence storage and delivery
Status: Accepted.

Files go through `App\Contracts\FileVault` (local private disk `vault`; swap the Flysystem driver
later without touching services). Keys are random (`evidence/YYYY/MM/<40 chars>.bin`), never derived
from user input. Type is detected from content (libmagic + OOXML/ODF structure checks), cross-checked
with an allowlist and the claimed extension; executables, scripts, HTML, SVG, macro documents and
double extensions are rejected. SHA-256 is computed on upload. Scanning is an async job behind
`App\Contracts\MalwareScanner` (`none` → `not_scanned`, `clamav` → clamdscan); infected files are
quarantined and never served. Public images are served as GD re-encoded renditions (EXIF/GPS removed);
other types are served as uploaded (UI warns investigators). All bytes leave via `EvidenceDelivery`
with `attachment`/`nosniff`/`CSP sandbox` headers and an access-log row per attempt. Public viewers
never see original filenames. New uploads default to **private**.

## ADR-015 — Tips: two kinds of anonymity
Status: Accepted.

Signed-in tipsters can be anonymous *to the investigator* (the platform still knows the account).
Guests are anonymous *to the platform*: no account, IP or user agent is stored with the tip and
analytics for tips never carry a user id; follow-up uses a one-time access code of which only a
SHA-256 hash is stored. Tip contents are need-to-know: staff oversight requires `tips.view_any`
(super admin by default) and a justification, and is audited.

## ADR-016 — RBAC implemented over the specified tables
Status: Accepted.

Rather than a package with its own schema, a small `HasRoles` trait implements the exact
`roles / permissions / role_permissions / user_roles` tables from `docs/DATABASE.md`. `super_admin`
implicitly holds every permission. `identity.*` and `tips.view_any` are granted to no role except
super admin by default.

## ADR-017 — Tamper-evident audit log
Status: Accepted.

`audit_logs` rows are written only by `AuditLogger` (query builder insert, microsecond timestamps)
with `hash = sha256(prev_hash + canonical row)`. A single-row `audit_chain` head is locked
`FOR UPDATE` while appending so concurrent transactions serialise rather than fork the chain.
Models refuse update/delete; `php artisan audit:verify` (scheduled daily) detects tampering.
Secrets and private identity values are never written into payloads.

## ADR-018 — Session revocation list
Status: Accepted (found by E2E).

Deleting a session row is not enough: a request already in flight writes the session back when it
finishes (observed as "logout undone" in E2E). Logout, password change/reset, deactivation and
suspension now record revoked session ids (`SessionRevoker`, cache store, lifetime-bounded) and the
`RejectRevokedSession` middleware turns any request carrying one into a fresh guest session. The SPA
also serialises logout after in-flight `/auth/me` calls and reloads the page.

## ADR-019 — Search, trending and counters
Status: Accepted.

MySQL FULLTEXT over `published_title/summary/body` in boolean mode with user operators stripped and
prefix matching; LIKE fallback for very short queries. `SearchService` encapsulates it so a search
engine can replace it later. Trending = distinct-visitor weighted engagement over 7 days with age decay,
recomputed hourly. Engagement counters are recomputed from source rows inside the same transaction
(not blindly incremented) so toggling cannot inflate them.

## ADR-020 — Analytics privacy
Status: Accepted.

No raw IPs in analytics. Visitors are an HMAC(ip, user agent, day) with a server key
(`ANALYTICS_FINGERPRINT_KEY`), which rotates daily, is dropped after 30 days, and events are pruned
after `ANALYTICS_RETENTION_DAYS` (180). View counts are recorded by a client beacon (crawlers and
prefetches don't count) and deduplicated per visitor for 30 minutes.

## ADR-021 — Markdown content, never raw HTML
Status: Accepted.

Report bodies, findings and CMS pages are Markdown rendered by `react-markdown` without `rehype-raw`
(raw HTML skipped), links restricted to http(s) with `rel="noopener noreferrer ugc nofollow"`, remote
images not embedded. Comments are plain text (tags stripped server-side). JSON-LD is serialised with
`<` escaped.

## ADR-022 — Local development topology
Status: Accepted (environment).

Backend runs in the existing Vagrant VM (`/var/www/html/onusondhan/backend`, MySQL databases
`onushondhan_dev`, `onushondhan_test`, `onushondhan_e2e`); the frontend and Playwright run on the
host. Because macOS blocks the app-launched Node process from the VM's private network, the API is
reached through an SSH tunnel on `127.0.0.1:8000` (dev) / `:8001` (E2E). None of this affects
production.

## ADR-023 — Admin panel
Status: Accepted.

Filament 4 at `/admin` for staff only (`User::canAccessPanel`: active, verified, moderator/admin/
super admin). Resources are gated by policies; moderation actions in the panel call the same
`ModerationService` as the API. TOTP 2FA with recovery codes is enforced when `ADMIN_MFA_REQUIRED=true`
(production default). Configuration changes (CMS, agreements, categories, settings, roles) are audited
by `AdminChangeObserver`; published agreement versions are immutable.
