#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT"

echo "== Onushondhan project verification =="

if [ -d backend ]; then
  echo "-- Laravel checks"
  (cd backend && php artisan --version)
  (cd backend && php artisan migrate:status)
  (cd backend && php artisan test)
fi

if [ -d frontend ]; then
  echo "-- Next.js checks"
  (cd frontend && npm run lint --if-present)
  (cd frontend && npm run build)
fi

echo "Verification commands completed."
