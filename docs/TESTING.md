# Testing Strategy

## Backend
- unit tests for services/domain rules;
- feature tests for every API family;
- policy tests for authorization;
- model relationship tests where behavior is non-trivial;
- upload/evidence security tests;
- job/notification tests;
- migration/seed smoke test on clean database.

## Frontend
- component tests for reusable interactive components where practical;
- accessibility checks for major forms;
- API error-state tests.

## End-to-end
Playwright journeys:
1. visitor discovers and reads a published investigation;
2. user registration/login/email verification;
3. comment/reply/report;
4. bookmark/follow/support;
5. investigator creates/publishes a report;
6. investigator uploads evidence and accesses only owned private evidence;
7. anonymous tip submission;
8. investigator reads/processes tip;
9. moderator reviews a complaint;
10. admin manages user/content and sees audit log.

## Security tests
Explicitly test:
- unauthorized access;
- IDOR/BOLA;
- role escalation;
- rate limits;
- malicious uploads;
- unsafe filenames/paths;
- XSS payload rejection/sanitization;
- CSRF behavior;
- private API field leakage;
- private evidence download authorization.

## Release gate
All automated checks must pass before production deployment preparation is declared complete.
