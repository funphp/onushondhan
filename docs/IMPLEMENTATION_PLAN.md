# Implementation Plan

The agent should use this as the authoritative build order. A phase may only be marked complete after code and tests exist.

## Phase 1 — Bootstrap
- repository structure
- Laravel API
- Next.js frontend
- MySQL connection
- environment handling
- health endpoint
- base exception/error contract
- logging/request IDs

## Phase 2 — Auth & RBAC
- users
- roles
- permissions
- auth flows
- email verification
- password reset
- agreements
- investigator onboarding

## Phase 3 — Investigator
- investigator profile
- public/protected identity
- private identity security
- ownership policies

## Phase 4 — Investigation core
- categories/tags/locations
- investigation CRUD
- draft/preview/publish/withdraw/archive
- versions
- sources/findings/timeline

## Phase 5 — Evidence
- upload
- storage abstraction
- validation
- scanning hook
- hashes
- metadata
- download authorization
- access logs

## Phase 6 — Public discovery
- home
- search
- filters
- investigator profiles
- investigation pages
- related content
- SEO

## Phase 7 — Community
- comments
- votes
- reports
- bookmarks
- follow
- support
- reactions
- share tracking
- leaderboard

## Phase 8 — Tips
- tip submission
- attachments
- inbox
- secure messaging
- statuses

## Phase 9 — Moderation
- moderation queue
- complaints
- actions
- restrictions/removals
- audit logs

## Phase 10 — Admin/CMS
- Filament admin
- dashboards
- CMS
- agreements
- settings
- analytics

## Phase 11 — Hardening
- authorization review
- security tests
- upload review
- privacy review
- performance review
- accessibility review

## Phase 12 — Release
- complete test suite
- production builds
- Nginx/PHP-FPM/Node configs
- systemd/Supervisor
- cron scheduler
- backup scripts
- health checks
- rollback docs
- release checklist
