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
