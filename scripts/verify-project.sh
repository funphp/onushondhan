#!/usr/bin/env bash
# Runs every automated quality gate. Backend commands need PHP 8.2+/MySQL (see
# docs/TESTING.md); E2E additionally needs scripts/e2e-backend.sh running.
set -euo pipefail
ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

if [ "${SKIP_BACKEND:-0}" != "1" ]; then
  echo "== Backend"
  (cd "$ROOT/backend" && composer validate --no-check-publish && vendor/bin/pint --test && vendor/bin/phpstan analyse --memory-limit=1G --no-progress && php artisan test && composer audit)
fi

if [ "${SKIP_FRONTEND:-0}" != "1" ]; then
  echo "== Frontend"
  (cd "$ROOT/frontend" && npm run lint && npm run typecheck && npm test && npm run build && npm audit --omit=dev)
fi

if [ "${RUN_E2E:-0}" = "1" ]; then
  echo "== E2E"
  (cd "$ROOT/frontend" && npx playwright test)
fi
echo "All checks passed."
