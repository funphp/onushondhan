# Operations, Backup & Recovery

## Backups
At minimum:
- daily encrypted MySQL backup;
- more frequent backups if the expected write volume requires it;
- backup of private evidence storage;
- retention policy;
- separate backup location.

## Restore testing
A backup is not considered valid until a restore has been tested.

## Monitoring
Track:
- HTTP 4xx/5xx rates;
- PHP-FPM health;
- Node process health;
- MySQL availability/connections/slow queries;
- disk usage;
- evidence storage growth;
- failed jobs;
- authentication/security events;
- application exceptions.

Provide a health check and structured logs. Add Sentry/Prometheus/Grafana only if the deployment actually has those services; they are optional, not initial hard dependencies.

## Maintenance
- security updates;
- log rotation;
- backup verification;
- stale upload cleanup;
- failed-scan review;
- moderation queue review;
- database index/slow-query review;
- dependency updates.

## Disaster recovery
Document RPO/RTO assumptions chosen for the deployment and test the restore procedure periodically.
