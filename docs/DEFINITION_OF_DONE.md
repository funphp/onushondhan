# Definition of Done

A release is DONE only when all items below are true.

## Product
- [ ] All core public journeys work.
- [ ] All investigator journeys work.
- [ ] Comments, tips, follows, support, bookmarks and reactions work.
- [ ] Moderation and complaint workflows work.
- [ ] Admin workflows work.

## Data
- [ ] Clean database migration succeeds.
- [ ] Seed data succeeds.
- [ ] Foreign keys/unique constraints are correct.
- [ ] Indexes support major queries.

## Security
- [ ] Authorization policies cover every sensitive resource.
- [ ] IDOR/BOLA tests pass.
- [ ] Upload validation/security tests pass.
- [ ] Private identity is not publicly exposed.
- [ ] Private evidence is protected.
- [ ] Rate limits are active on abuse-prone routes.
- [ ] Secrets are outside source control.

## Quality
- [ ] Backend tests pass.
- [ ] E2E smoke tests pass.
- [ ] Frontend production build passes.
- [ ] Lint/format/static checks pass.
- [ ] Accessibility review completed for core flows.

## Operations
- [ ] Production configuration templates exist.
- [ ] Scheduler/queue process configuration exists.
- [ ] Backup procedure exists.
- [ ] Restore procedure exists.
- [ ] Health check exists.
- [ ] Rollback procedure exists.
- [ ] Logs are manageable/rotated.

## Documentation
- [ ] README explains setup.
- [ ] API docs are current.
- [ ] Environment variables documented.
- [ ] Legal/policy templates included with review notice.
- [ ] BUILD_STATUS updated.
- [ ] RELEASE_CHECKLIST generated.
- [ ] Any external/manual prerequisites listed.
