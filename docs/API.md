# REST API Contract — `/api/v1`

## Principles
- JSON only.
- Consistent success/error envelope.
- Pagination for collections.
- Request IDs for traceability.
- Authorization on every protected endpoint.
- Never expose private identity/evidence fields in public resources.

## Error envelope

```json
{
  "message": "Human-readable summary",
  "code": "stable_error_code",
  "errors": {},
  "request_id": "..."
}
```

## Authentication

Public API:
- `POST /auth/register`
- `POST /auth/login`
- `POST /auth/logout`
- `GET /auth/me`
- `POST /auth/verify-email`
- `POST /auth/forgot-password`
- `POST /auth/reset-password`

Use a maintained Laravel authentication approach. Do not implement password cryptography manually.

## Public discovery
- `GET /investigations`
- `GET /investigations/{public_id_or_slug}`
- `GET /investigations/{id}/related`
- `GET /categories`
- `GET /tags`
- `GET /locations`
- `GET /investigators`
- `GET /investigators/{slug}`
- `GET /investigators/{slug}/investigations`
- `GET /leaderboards/support`

Filters: q, category, tag, location, date range, status where public, sort, page, per_page.

## Authenticated user
- profile read/update
- bookmarks list/create/delete
- follows create/delete/list
- supports create/revoke/list
- reactions create/update/delete
- comments create/update/delete/vote/report
- notifications list/read
- tips create/list/update/read/message

## Investigator
- profile management
- protected identity management where permitted
- investigations CRUD
- preview
- publish
- withdraw
- archive
- versions list/diff/read
- evidence CRUD
- sources CRUD
- findings CRUD
- timeline CRUD
- analytics summary
- tip inbox

## Admin
Prefix protected by admin middleware/policies:
- users
- roles/permissions
- investigations moderation
- evidence moderation
- comments moderation
- tips oversight according to strict need-to-know
- complaints
- moderation actions
- CMS
- terms versions
- audit logs
- analytics
- settings

## API implementation rules
- Controllers stay thin.
- Validation in Form Requests.
- Policy checks before mutations.
- Use API Resources.
- Use idempotency protection for repeatable mutation endpoints when needed.
- Rate-limit registration, auth, comment, support, tip, upload, and complaint endpoints.
