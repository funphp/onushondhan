# Onushondhan — backend (Laravel 12)

REST API (`/api/v1`), Filament admin (`/admin`), queue jobs and scheduler. See the root `README.md`
for setup and `../docs/` for the specification, API and operations.

- `app/Http/Controllers/Api/V1` — thin controllers · `app/Services` — domain logic ·
  `app/Policies` — authorisation · `app/Http/Resources` — public/owner views ·
  `app/Filament` — admin panel · `app/Jobs` — evidence processing, notifications ·
  `resources/content` — legal/CMS templates (require legal review).
- `php artisan test` · `vendor/bin/pint --test` · `vendor/bin/phpstan analyse`
- Useful commands: `audit:verify`, `investigations:trending`, `retention:prune`,
  `users:grant-role <email> <role>`.
