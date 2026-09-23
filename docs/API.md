# REST API — `/api/v1`

Implemented in `backend/routes/api.php` (controllers in `backend/app/Http/Controllers/Api/V1`).
All paths below are relative to `/api/v1`. Regenerate the raw list with
`php artisan route:list --path=api`.

## Conventions

- JSON only (`Accept: application/json`). Collections are paginated (`data`, `meta`, `links`); `per_page` max 50.
- **Authentication:** Laravel Sanctum SPA cookie sessions. The browser calls the API on the
  *same origin* as the site (Next.js proxies `/api` and `/sanctum` in development; Nginx routes
  them in production). Before the first write: `GET /sanctum/csrf-cookie`, then echo the
  `XSRF-TOKEN` cookie in the `X-XSRF-TOKEN` header. Requests are treated as SPA/session requests
  only when `Origin`/`Referer` matches `SANCTUM_STATEFUL_DOMAINS`.
- **Public identifiers only.** Investigations use `INV-YYYY-XXXXXXXX` (non-sequential); users,
  investigators, evidence, comments, tips and complaints use ULIDs. Numeric ids never leave the API.
- **Object-level authorisation** is enforced in policies/services. Requests for another user's
  private objects return **404** (existence is not disclosed).
- Every response carries `X-Request-Id` (an inbound well-formed `X-Request-Id` is honoured).

### Error envelope

```json
{ "message": "Human-readable summary", "code": "stable_error_code", "errors": { "field": ["…"] }, "request_id": "…" }
```

| HTTP | `code` examples |
|---|---|
| 401 | `unauthenticated` |
| 403 | `forbidden`, `account_inactive`, `email_unverified`, `agreements_required` (`errors.agreements` lists types) |
| 404 | `not_found`, `tip_not_found` |
| 409 | `revision_conflict`, `duplicate_evidence`, `already_investigator`, `agreement_outdated` |
| 413 | `payload_too_large` |
| 419 | `csrf_mismatch` |
| 422 | `validation_failed`, `invalid_credentials`, `not_ready`, `confirmation_required`, `change_reason_required`, `comments_locked`, `invalid_state`, `self_support`, … |
| 429 | `rate_limited`, `support_cooldown` |
| 500 | `server_error` (details only when `APP_DEBUG=true`) |

### Rate limits (per user, or per IP for guests)

| Limiter | Limit | Routes |
|---|---|---|
| `api` | 240/min | everything |
| `login` | 5/min per email+IP, 50/h per IP | login |
| `register` | 10/h per IP | register |
| `password-reset` | 3/min per email, 20/h per IP | forgot/reset password |
| `verification` | 6/h | resend verification, phone code |
| `comments` | 6/min, 200/day | create/edit comment |
| `support` | 10/min, 100/day | support/revoke |
| `engagement` | 60/min | bookmark, react, follow, vote |
| `tips` | 3/min, 30/day | tip submit/replies |
| `tip-access` | 10/min per IP | guest access-code lookups |
| `complaints` | 3/min, 30/day | complaints, comment reports |
| `uploads` | 120/h | evidence, avatar |
| `downloads` | 60/min | evidence/tip attachment files |
| `analytics` | 60/min per IP | view/share beacons |

## Health

| Method | Path | Notes |
|---|---|---|
| GET | `/health` | `{"status":"ok","checks":{database,storage,queue,recent_failed_jobs}}`; 503 when DB or storage fails. No versions/hosts disclosed. |

## Auth & account

| Method | Path | Auth | Notes |
|---|---|---|---|
| POST | `/auth/register` | guest | `name` (private), `username?` (public handle), `email`, `password`, `password_confirmation`, `accept_agreements: true`. Records acceptance of current Terms/Privacy/Content Policy, sends verification email, starts a session. |
| POST | `/auth/login` | guest | `email`, `password`, `remember?`. Session id regenerated. |
| POST | `/auth/logout` | user | Invalidates and **revokes** the session id. |
| GET | `/auth/me` | user | Own account incl. roles, permissions, investigator summary, `pending_agreements`. |
| POST | `/auth/verify-email` | guest | `id, hash, expires, signature` from the emailed link (HMAC-signed, 60 min). |
| POST | `/auth/email/verification-notification` | user | Resend. |
| POST | `/auth/forgot-password` | guest | Identical response whether or not the account exists. |
| POST | `/auth/reset-password` | guest | `token, email, password, password_confirmation`; single use; revokes all sessions. |
| PUT | `/auth/password` | user | `current_password, password, password_confirmation`; revokes other sessions. |
| PATCH | `/me/profile` | user | `name`, `username`, `email` (re-verification), `notification_preferences.email.{comments,tips,followed_investigators,moderation}`. |
| POST | `/me/phone` · `/me/phone/verify` | user | Optional phone verification (6-digit code, 10 min, 5 attempts). |
| POST | `/me/deactivate` | user | `password`; deactivates and revokes sessions. |
| GET | `/me/notifications` (`?unread=1`) · POST `/me/notifications/{id}/read` · POST `/me/notifications/read-all` | user | Own notifications only. |

## Agreements

| Method | Path | Auth | Notes |
|---|---|---|---|
| GET | `/agreements` | public | Current published version of each agreement type. |
| GET | `/agreements/{type}` | public | `terms_of_use`, `privacy_policy`, `content_policy`, `investigator_responsibility`. |
| POST | `/agreements/accept` | user | `terms_version_ids[]` — must be the *current* versions. |

Community and publishing writes require a verified email **and** acceptance of all current applicable agreements.

## Public discovery

| Method | Path | Notes |
|---|---|---|
| GET | `/investigations` | Filters: `q` (MySQL full-text, prefix match), `category` (slug; includes subcategory), `tag`, `country` (ISO-2), `location` (district/city/area), `investigator` (slug), `from`, `to` (published date), `sort` = `latest\|trending\|most_read\|most_shared\|most_discussed\|relevance\|oldest`, `page`, `per_page`. Only published + public. |
| GET | `/investigations/{id}` | By public id (or slug). Renders the **current published version snapshot**. Withdrawn/restricted/removed return a tombstone (`notice`, no body/evidence). `robots.index` tells clients whether to index. `viewer` block when signed in. |
| GET | `/investigations/{id}/versions` | Public version metadata (number, date, change note). |
| GET | `/investigations/{id}/related` | Up to 6. |
| GET | `/investigations/{id}/comments` | `sort=top\|new`, threaded (3 levels). |
| POST | `/investigations/{id}/view` | Read beacon; deduplicated per visitor for 30 min. |
| POST | `/investigations/{id}/share` | `platform` = `facebook\|x\|linkedin\|whatsapp\|telegram\|messenger\|email\|copy_link\|other`. |
| GET | `/categories` · `/categories/{slug}` · `/tags` (`?q=`) · `/tags/{slug}` · `/locations` | Taxonomy with published counts. |
| GET | `/investigators` (`?q`, `sort=published\|followers\|supporters\|views\|recent`) · `/investigators/{slug}` · `/investigators/{slug}/investigations` · `/investigators/{slug}/avatar` | Public identity only. |
| GET | `/leaderboards/support` · `/leaderboards/{board}` | Boards: `top_supported`, `rising`, `most_published`, `most_read`, `most_shared`, `most_discussed`. Response includes a `note` that rankings are engagement, not accuracy. |
| GET | `/pages` · `/pages/{slug}` · `/announcements` · `/sitemap` | CMS and sitemap feed. |

## Community (signed in, verified, agreements accepted)

| Method | Path | Notes |
|---|---|---|
| POST | `/investigations/{id}/comments` | `body` (plain text; HTML stripped), `parent_id?`. |
| PATCH / DELETE | `/comments/{comment}` | Author only. |
| PUT / DELETE | `/comments/{comment}/vote` | `value` = `1\|-1`; not on own comment; one per user. |
| POST | `/comments/{comment}/report` | `reason`, `details?`; 5 distinct open reports auto-hide pending review. |
| PUT / DELETE | `/investigations/{id}/bookmark` | Idempotent. |
| PUT / DELETE | `/investigations/{id}/reaction` | `type` = `useful\|important\|interesting` (separate from investigator support). |
| PUT / DELETE | `/investigators/{slug}/follow` | Not yourself. |
| PUT / DELETE | `/investigators/{slug}/support` | One active support per user per investigator (DB unique key); 60-min cooldown after revoking; audited. |
| GET | `/me/bookmarks` · `/me/following` · `/me/supporting` | |

## Tips

| Method | Path | Auth | Notes |
|---|---|---|---|
| POST | `/tips` | guest or user | multipart: `investigation` or `investigator` (slug), `subject`, `message`, `anonymous_to_investigator?`, `contact_preference` (`platform_messaging\|email\|phone\|none`), `contact_value?`, `attachments[]` (≤5), `agree_to_content_policy`. Guests receive a one-time `access_code` (only its hash is stored). |
| POST | `/tips/access` · `/tips/access/messages` | guest | `access_code` (+ `body`) — read/reply to a guest tip. |
| GET | `/me/tips` · `/me/tips/{tip}` · POST `/me/tips/{tip}/messages` | user | Tips you sent. |
| GET | `/me/inbox/tips` (`?status=unread\|important\|archived\|closed`) · GET/PATCH `/me/inbox/tips/{tip}` · POST `/me/inbox/tips/{tip}/messages` | investigator | Recipient only. Sender identity shown only if the tipster opted in. |
| GET | `/tip-attachments/{attachment}/download` | recipient, sender, or `tips.view_any` (audited) | Infected files are never served. |

## Investigator workspace (`investigations.manage_own`, verified, agreements)

| Method | Path | Notes |
|---|---|---|
| POST | `/me/investigator` | Onboarding: `public_name`, `identity_mode` (`real_name\|protected_public_identity`), `bio?`, `website_url?`, `private_identity{…}?`, `accept_investigator_agreement`. |
| GET / PATCH | `/me/investigator` | Changing `public_name` changes the slug (no redirect, by design). |
| POST | `/me/investigator/avatar` | JPEG/PNG/WebP ≤5 MB, re-encoded without metadata. |
| GET / PUT | `/me/investigator/private-identity` | Owner only; encrypted at rest; changes audited without values. |
| GET | `/me/investigator/analytics` (`?days=7..365&investigation=`) · `/followers` · `/supporters` | Aggregates only. |
| GET / POST | `/me/investigations` | List (`?status=`) / create draft. |
| GET / PATCH / DELETE | `/me/investigations/{id}` | PATCH = autosave of the working copy (send `revision`; stale → 409). DELETE only for never-published drafts. Child collections (`findings`, `timeline`, `sources`, `locations`, `recommendations`, `entities`, `tags`) use replace-all semantics; `category`/`subcategory` by slug. |
| GET | `/me/investigations/{id}/preview` | Working copy rendered exactly like the public page. |
| GET | `/me/investigations/confirmation-statements` | Statements the publish UI must present. |
| POST | `/me/investigations/{id}/publish` | `confirmations{responsible_for_claims, has_rights_to_material, subject_to_policies_and_law, no_platform_verification}` (all `true`), `change_reason` (required for updates). Creates an immutable version + confirmation record. |
| POST | `/me/investigations/{id}/withdraw` (`reason`) · `/archive` | Lifecycle. |
| GET | `/me/investigations/{id}/versions` · `/versions/{n}` · `/diff?from=&to=` | Version history and line diff. |
| GET / POST | `/me/investigations/{id}/evidence` | Upload multipart `file`, `caption?`, `visibility` (`public\|restricted\|private`, default private). |
| PATCH / DELETE | `/me/evidence/{evidence}` | `caption`, `visibility`, `sort_order`. |
| GET / POST | `/me/evidence/{evidence}/versions` | Replace file (new version; history kept). |
| GET | `/me/evidence/{evidence}/access-log` | When/how accessed (not by whom). |

## Evidence access

| Method | Path | Notes |
|---|---|---|
| GET | `/evidence/{id}` | Metadata (owner/staff get the extended view). |
| GET | `/evidence/{id}/download` · `/preview` | Guests: public + scan-acceptable evidence on a publicly readable investigation. Owners: own evidence. `evidence.view_restricted`: restricted evidence (audited). Public images are served as metadata-stripped renditions. Every attempt is logged; failures are 404. |
| POST | `/evidence/{id}/download-link` | Signed relative link (10 min) bound to the requesting user; permissions are re-checked when used. |

## Complaints

| Method | Path | Auth | Notes |
|---|---|---|---|
| POST | `/complaints` | guest (email required) or user | `complaint_type` (`factual_dispute\|privacy\|copyright\|personal_information\|prohibited_content\|harassment\|spam\|other_terms_violation`), `description`, one of `investigation`/`comment`/`evidence`, `good_faith`. Factual disputes are recorded without any verification claim. |
| GET | `/me/complaints` | user | Own complaints (no internal notes). |

## Staff moderation API (`/admin/*`, verified; permission per route)

The Filament panel at `/admin` is the primary staff UI; these endpoints mirror its core actions.

| Method | Path | Permission |
|---|---|---|
| GET | `/admin/moderation/queue` | `moderation.view` |
| GET / GET / PATCH | `/admin/complaints` · `/admin/complaints/{id}` | `complaints.manage` |
| POST | `/admin/moderation/actions` | per target: investigation→`investigations.moderate`, comment→`comments.moderate`, evidence→`evidence.moderate`, user→`users.manage`, investigator→`investigators.manage_any` |
| GET | `/admin/audit-logs` | `audit.view` |
| POST | `/admin/investigators/{publicId}/private-identity` | `identity.view_private` (+ `reason`, audited, raises a security event) |

`/admin/moderation/actions` body: `target_type`, `target_id` (public id), `action`
(`no_action, request_correction, warn, restrict, hide, remove, restore, lock_comments,
unlock_comments, suspend_user, ban_user, reinstate_user, escalate`), `reason`,
`details{public_note?, days?}`, `complaint_id?`. Staff cannot act on accounts of equal or higher
rank; super-admin accounts cannot be moderated.
