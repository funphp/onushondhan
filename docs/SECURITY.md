# Security Specification

## Authentication
- Strong password policy appropriate to current standards.
- Modern password hashing via framework.
- Email verification.
- Password reset with expiring single-use tokens.
- Session invalidation after sensitive credential changes.
- Brute-force/rate-limit controls.
- Admin MFA/2FA using a maintained library or provider where available.

## Authorization
Treat every protected object as untrusted. Enforce server-side policies for all reads and writes.

Test IDOR/BOLA cases explicitly:
- investigator A cannot read investigator B private data;
- investigator A cannot download investigator B private evidence;
- investigator A cannot edit investigator B investigation;
- users cannot read another user's private tips/notifications;
- moderators cannot access private identity without permission;
- public APIs never return private identity fields.

## Upload security
- Allowlist required types.
- Enforce size limits.
- Validate detected MIME type.
- Reject executable/script formats.
- Generate randomized filenames.
- Store outside directly executable webroot.
- Use safe content disposition.
- Scan when possible.
- Hash with SHA-256.
- Do not render active content inline unless explicitly safe.
- Sanitize document previews.

## Web security
- CSRF protections where cookie/session auth is used.
- CSP and modern security headers.
- Secure cookies.
- HSTS only when HTTPS is permanently enabled.
- X-Content-Type-Options.
- Referrer-Policy.
- Frame protection via CSP/frame-ancestors.
- Rate limits on abuse-prone endpoints.
- Request size limits.

## Privacy
Collect only necessary data. Define retention/deletion rules for login logs, analytics events, tips, complaints, and audit logs.

Do not expose:
- private identity;
- private evidence path;
- raw storage paths;
- internal moderation notes;
- secret tokens;
- server filesystem information.

## Audit
Audit:
- login/security events;
- role changes;
- permission changes;
- private identity access;
- investigation publication/update/withdrawal;
- evidence access/download;
- moderation actions;
- complaint resolution;
- agreement acceptance;
- support actions where anti-abuse investigation needs traceability.

## Threat model
Review at minimum:
- account takeover;
- privilege escalation;
- malicious uploads;
- anonymous tip abuse;
- harassment/spam;
- data leakage;
- unsafe public identifiers;
- scraping/abuse;
- compromised admin account;
- backup compromise;
- server filesystem exposure.

---

# Implementation notes (current build)

| Area | Implementation | Tests |
|---|---|---|
| Password hashing | Argon2id (`HASH_DRIVER`), 10+ chars mixed case + digit, breached-password check in production | `RegistrationTest` |
| Session security | Sanctum SPA sessions, encrypted, HttpOnly, SameSite=Lax, Secure in prod; id regenerated on login; revocation list on logout/credential change/suspension | `LoginTest` |
| Brute force | per email+IP and per IP login limits; limits on every abuse-prone route (see `docs/API.md`) | `LoginTest`, `CommentsTest` |
| Account status | suspended/blocked/deactivated rejected at login *and* on every request | `LoginTest` |
| Admin MFA | Filament TOTP + recovery codes, enforced by `ADMIN_MFA_REQUIRED` | `AdminPanelTest` |
| RBAC | spec tables + `permission:` middleware + policies; role hierarchy in moderation; super admin only for identity/tip oversight | `AuthorizationBoundariesTest`, `ModerationActionsTest`, `AdminPanelTest` |
| IDOR/BOLA | owner checks return 404; public ids only | `AuthorizationBoundariesTest`, `AccessTest`, `TipsTest`, E2E 04/05 |
| Mass assignment | explicit `$fillable`; status/role/verification never fillable; `preventSilentlyDiscardingAttributes` outside production | `RegistrationTest` |
| XSS | Markdown without raw HTML, comment tags stripped, http(s)-only links, JSON-LD escaped, CSP | `CommentsTest`, `Markdown.test.tsx` |
| CSRF | Sanctum XSRF cookie/header for SPA; Filament CSRF | `api.test.ts` |
| SQL injection | query builder bindings; full-text operators stripped; LIKE wildcards escaped | `AuthorizationBoundariesTest`, `SearchTest` |
| Uploads | content-type detection, allowlist, extension match, executable/script/macro/double-extension rejection, size limits, random keys outside web root, SHA-256, async scan hook (ClamAV), quarantine, metadata-stripped public images, safe download headers, access logs, private by default | `UploadSecurityTest`, `AccessTest`, E2E 04 |
| Open redirects | `safeNext()` for post-login redirects | `format.test.ts` |
| SSRF | the server never fetches user-supplied URLs (source URLs are stored and rendered only) | — |
| Path traversal | original filenames are sanitised and never used for storage paths | `UploadSecurityTest` |
| Private identity | encrypted at rest, never in public resources, owner access, staff access requires `identity.view_private` + reason, audited + security event | `OnboardingTest`, `AuthorizationBoundariesTest` |
| Data leakage | public payloads never contain email, account name, private identity, storage paths, user ids | `AuthorizationBoundariesTest`, E2E 01 |
| Audit | hash-chained, append-only, secrets redacted, daily verification | `AuditChainTest` |
| Headers | API: `CSP default-src 'none'; sandbox`, nosniff, DENY, no-store; site: CSP without third-party origins, frame-ancestors none | `AuthorizationBoundariesTest` |

## Data retention

| Data | Retention |
|---|---|
| Analytics events | 180 days (`ANALYTICS_RETENTION_DAYS`); visitor fingerprints nulled after 30 days; no raw IPs |
| Login logs | 365 days |
| Security events | 730 days |
| Phone verification codes | 7 days (hashed) |
| Sessions | `SESSION_LIFETIME` |
| Tips & messages | kept while the recipient investigator account exists; closed tips are candidates for deletion after 730 days (manual, policy decision) |
| Audit logs, moderation actions, complaints, agreement acceptances, investigation versions, evidence access logs | retained as the forensic record |

## Threat-model review (summary)

| Threat | Mitigations |
|---|---|
| Account takeover | strong hashing, rate limits, session regeneration/revocation, email verification, admin MFA, security events on failures/resets |
| Privilege escalation | server-side permissions everywhere; role changes only by `roles.manage` (super admin) or console, audited; staff cannot act on equal/higher ranks |
| Malicious uploads | see Uploads above; files never executed or served inline except sanitised images/media with `sandbox` CSP |
| Anonymous tip abuse | per-IP/user limits, content-policy consent, attachments scanned, guest follow-up only via high-entropy code |
| Harassment/spam | comment limits, reports with auto-hide threshold, moderation actions, account suspension |
| Data leakage | resource-level allowlists, 404 on foreign objects, no personal data in SSR caches, analytics without raw IPs |
| Unsafe public identifiers | ULIDs and random investigation ids; no sequential ids exposed |
| Scraping/abuse | Nginx `limit_req` + application limits; public API returns only published content |
| Compromised admin | MFA, least-privilege roles, identity/tip access gated and audited, tamper-evident audit log |
| Backup compromise | age encryption with an offline private key; separate backup location; least-privilege DB backup user |
| Filesystem exposure | Laravel web root is `backend/public` only; vault outside it; Nginx denies other `.php` and dotfiles; no `storage:link` needed |
