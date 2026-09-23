# Definition of Done

A release is DONE only when all items below are true.

## Product
- [x] All core public journeys work.
- [x] All investigator journeys work.
- [x] Comments, tips, follows, support, bookmarks and reactions work.
- [x] Moderation and complaint workflows work.
- [x] Admin workflows work.

## Data
- [x] Clean database migration succeeds.
- [x] Seed data succeeds.
- [x] Foreign keys/unique constraints are correct.
- [x] Indexes support major queries.

## Security
- [x] Authorization policies cover every sensitive resource.
- [x] IDOR/BOLA tests pass.
- [x] Upload validation/security tests pass.
- [x] Private identity is not publicly exposed.
- [x] Private evidence is protected.
- [x] Rate limits are active on abuse-prone routes.
- [x] Secrets are outside source control.

## Quality
- [x] Backend tests pass.
- [x] E2E smoke tests pass.
- [x] Frontend production build passes.
- [x] Lint/format/static checks pass.
- [x] Accessibility review completed for core flows.

## Operations
- [x] Production configuration templates exist.
- [x] Scheduler/queue process configuration exists.
- [x] Backup procedure exists. (`scripts/backup.sh`; rehearse on target server — see RELEASE_CHECKLIST)
- [x] Restore procedure exists. (`scripts/restore.sh`, docs/OPERATIONS.md)
- [x] Health check exists.
- [x] Rollback procedure exists.
- [x] Logs are manageable/rotated.

## Documentation
- [x] README explains setup.
- [x] API docs are current.
- [x] Environment variables documented.
- [x] Legal/policy templates included with review notice. (legal review itself is an external input)
- [x] BUILD_STATUS updated.
- [x] RELEASE_CHECKLIST generated.
- [x] Any external/manual prerequisites listed.

_Status verified 2026-09-24 — see `docs/RELEASE_CHECKLIST.md` for the evidence behind each item._
