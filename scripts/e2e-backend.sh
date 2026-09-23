#!/usr/bin/env bash
# Starts an isolated Laravel API for Playwright E2E tests on port 8001 using a
# dedicated database (fresh migration + demo seed). Never point this at a real DB.
# Usage (inside the backend host/VM): scripts/e2e-backend.sh
set -euo pipefail
cd "$(dirname "$0")/../backend"

export DB_DATABASE="${E2E_DB_DATABASE:-onushondhan_e2e}"
export APP_ENV=local
export APP_URL="${E2E_APP_URL:-http://localhost:8001}"
export FRONTEND_URL="${E2E_FRONTEND_URL:-http://localhost:3100}"
export SANCTUM_STATEFUL_DOMAINS="localhost:3100,127.0.0.1:3100"
export QUEUE_CONNECTION=sync
export CACHE_STORE=database
export SESSION_DRIVER=database
export SESSION_SECURE_COOKIE=false
export MAIL_MAILER=log
export MAIL_LOG_CHANNEL=mail
export MAIL_LOG_PATH="$(pwd)/storage/logs/e2e-mail.log"
export ADMIN_MFA_REQUIRED=false
export SEED_DEMO_DATA=true
export VAULT_ROOT="$(pwd)/storage/app/private/e2e-vault"

case "$DB_DATABASE" in
  *e2e*|*test*) ;;
  *) echo "Refusing to reset non-E2E database '$DB_DATABASE'" >&2; exit 1 ;;
esac

rm -f "$MAIL_LOG_PATH"
php artisan config:clear >/dev/null
php artisan migrate:fresh --seed --force
export PHP_CLI_SERVER_WORKERS=4
cd public
exec php -S 0.0.0.0:8001 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php
