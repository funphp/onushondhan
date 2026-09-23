@AGENTS.md

# Onushondhan backend — project notes

- The root `../CLAUDE.md` and `../docs/` are the product specification; follow them first.
- API lives under `routes/api.php` (`/api/v1`), controllers in `app/Http/Controllers/Api/V1`,
  domain logic in `app/Services`, authorisation in `app/Policies` + `permission:` middleware.
- Never expose private identity, storage paths or account names in public resources.
- Every high-risk change must be written through `App\Services\AuditLogger`.
- Tests: `php artisan test` (MySQL test DB `onushondhan_test`; see `phpunit.xml`).
- Quality gates: `vendor/bin/pint --test`, `vendor/bin/phpstan analyse`.
