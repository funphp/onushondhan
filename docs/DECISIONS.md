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
