# Onushondhan.com — Claude Desktop Build Package

This package is a complete product/engineering specification intended to be placed into a Claude Desktop/Claude project workspace and used as the project source of truth.

## Recommended use

1. Create/open a Claude Desktop project from the Onushondhan repository folder.
2. Put this package in the repository root (or upload it into project knowledge).
3. Ensure `CLAUDE.md` is at the repository root so Claude Code can automatically read it in that directory.
4. Open `prompts/MASTER_BUILD_PROMPT.md` and send it as the first build task.
5. Let Claude work through the implementation plan, using `docs/BUILD_STATUS.md` to maintain progress.

Claude Desktop currently supports local-file access and code execution for supported plans, and its current project workflow supports local folders, project instructions, files, and memory. See the official Claude Help Center for current availability. 

## Important limitation

The package is designed to minimize intervention, but no documentation can safely invent production secrets, DNS control, SMTP credentials, payment credentials, server access, or legal approvals. Those are external configuration inputs. Claude should prepare everything around them and stop only where a credential or irreversible authorization is genuinely required.

## Deferred infrastructure

The initial project deliberately does NOT use:

- Redis
- Amazon S3 / Cloudflare R2
- OpenSearch
- Docker / Kubernetes

Those can be introduced later without redesigning the domain model.

## Primary architecture

`Next.js -> Laravel REST API -> MySQL`

Local/private file storage is used initially, with a storage abstraction so future object storage can be added later.

## Package contents

- `CLAUDE.md` — permanent agent instructions
- `prompts/MASTER_BUILD_PROMPT.md` — first-run autonomous build prompt
- `prompts/RESUME_BUILD_PROMPT.md` — resume/recover prompt
- `docs/PRODUCT_REQUIREMENTS.md` — product requirements
- `docs/ARCHITECTURE.md` — technical architecture
- `docs/DATABASE.md` — database model and constraints
- `docs/API.md` — REST API contract
- `docs/ROLES_PERMISSIONS.md` — RBAC matrix
- `docs/UI_UX.md` — screens and UX behavior
- `docs/SECURITY.md` — application and evidence security
- `docs/MODERATION_AND_POLICY.md` — moderation, complaints, responsibility policies
- `docs/NOTIFICATIONS_ANALYTICS.md` — notifications and analytics
- `docs/SEO.md` — SEO and sharing
- `docs/TESTING.md` — test strategy and acceptance coverage
- `docs/DEPLOYMENT.md` — production deployment without Docker
- `docs/OPERATIONS.md` — backups, monitoring, maintenance, rollback
- `docs/IMPLEMENTATION_PLAN.md` — module-by-module build sequence
- `docs/DEFINITION_OF_DONE.md` — release gate
- `docs/DECISIONS.md` — architectural decision log
- `docs/BUILD_STATUS.md` — agent-maintained status file
- `docs/ENVIRONMENT.md` — environment variables and secrets handling
- `deploy/` — deployment templates Claude can adapt into the real project
- `scripts/` — helper scripts Claude can use/extend
