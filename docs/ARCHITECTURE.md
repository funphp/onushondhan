# Architecture

## 1. Initial topology

Browser
  -> Cloudflare/WAF (optional but recommended)
  -> Next.js frontend
  -> Laravel API
  -> MySQL 8+
  -> local/private file storage

Background processing initially uses Laravel's available queue backend without Redis. Keep the queue abstraction so a Redis worker can be added later if required.

## 2. Repository layout

```text
/
  CLAUDE.md
  backend/                 Laravel application
  frontend/                Next.js application
  docs/                    project documentation
  deploy/                  Nginx/systemd/Supervisor templates
  scripts/                 build/test/deploy helpers
```

If the repository is structured differently, keep existing conventions when they are sound.

## 3. Backend layering

```text
HTTP Route
  -> Middleware / Auth / Rate Limit
  -> Controller
  -> Form Request
  -> Service / Action
  -> Domain Model / Repository only where justified
  -> Event/Job/Notification
  -> API Resource
```

Do not create repositories for every model by default. Use Laravel's ORM directly unless abstraction solves a real problem.

## 4. Frontend layering

```text
App Router
  -> layout / route group
  -> page / server component
  -> feature component
  -> form/action/query layer
  -> typed API client
  -> Laravel REST API
```

Keep API contracts typed. Prefer server-side data fetching for public SEO pages where practical.

## 5. Storage abstraction

Initial adapter: local private filesystem.

Domain services should interact with a storage service interface rather than assuming a local path. Public evidence may be stored in a separate public-safe area, but sensitive evidence always remains private.

Future adapters are explicitly deferred:
- S3
- Cloudflare R2

## 6. Search

Initial search uses MySQL full-text indexes plus structured filters and indexes. Avoid introducing OpenSearch until measurable search requirements justify it.

## 7. Async processing

Use Laravel jobs for:
- evidence hashing
- malware-scan orchestration
- media metadata extraction
- thumbnails
- notification dispatch
- analytics aggregation
- cleanup

The code must support a database/sync queue suitable for the initial deployment without Redis.

## 8. Security boundaries

Separate public, authenticated, investigator-owner, moderator, admin, and super-admin policies. Never rely on UI hiding for authorization.

## 9. Deployment model

Single or small number of Linux application instances initially. Nginx serves frontend and Laravel public assets; PHP-FPM serves Laravel; Next.js runs as a Node service behind Nginx when SSR is required. MySQL is managed or hosted separately where practical.

No container runtime is required for the initial release.
