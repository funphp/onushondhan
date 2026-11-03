#!/usr/bin/env bash
# Release-based, zero-downtime-ish deployment for a single Linux host (no Docker).
#
#   /var/www/onushondhan/
#     releases/<timestamp>/   one checkout per release
#     shared/backend.env      production backend .env (from your secret manager)
#     shared/frontend.env     production frontend env (NEXT_PUBLIC_* are baked at build)
#     shared/storage/         Laravel storage (logs, framework cache, private vault if local)
#     current -> releases/<timestamp>
#
# Usage: scripts/deploy.sh <git-ref> [repo-url]
# Requires: git, php 8.3, composer, node 20+, npm, mysql client; sudo rights only for
# `systemctl reload/restart` of the listed units (configure via sudoers).
set -euo pipefail

REF="${1:?usage: deploy.sh <git-ref> [repo-url]}"
REPO="${2:-${DEPLOY_REPO:?set DEPLOY_REPO or pass repo url}}"
BASE="${DEPLOY_BASE:-/var/www/onushondhan}"
KEEP="${DEPLOY_KEEP_RELEASES:-5}"
HEALTH_URL="${DEPLOY_HEALTH_URL:-http://127.0.0.1:8080/api/v1/health}"
TS="$(date -u +%Y%m%d%H%M%S)"
REL="$BASE/releases/$TS"
PREV="$(readlink -f "$BASE/current" 2>/dev/null || true)"

log() { printf '\n==> %s\n' "$*"; }

for f in "$BASE/shared/backend.env" "$BASE/shared/frontend.env"; do
  [ -f "$f" ] || { echo "Missing $f (create it from the .env.example files and your secret manager)" >&2; exit 1; }
done

log "Fetching $REF into $REL"
git clone --quiet --depth 1 --branch "$REF" "$REPO" "$REL" 2>/dev/null || { git clone --quiet "$REPO" "$REL" && git -C "$REL" checkout --quiet "$REF"; }
SHA="$(git -C "$REL" rev-parse HEAD)"

log "Linking shared configuration and storage"
ln -sfn "$BASE/shared/backend.env" "$REL/backend/.env"
rm -rf "$REL/backend/storage"
ln -sfn "$BASE/shared/storage" "$REL/backend/storage"
mkdir -p "$BASE/shared/storage"/{app/private/vault,framework/{cache,sessions,views},logs}

log "Installing backend dependencies"
(cd "$REL/backend" && composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --classmap-authoritative)

log "Building frontend"
set -a; . "$BASE/shared/frontend.env"; set +a
(cd "$REL/frontend" && npm ci --no-audit --no-fund && npm run build)
cp -r "$REL/frontend/.next/static" "$REL/frontend/.next/standalone/.next/static"
cp -r "$REL/frontend/public" "$REL/frontend/.next/standalone/public"
# Writable fetch/ISR cache (the systemd unit only allows writes here; it must exist).
mkdir -p "$REL/frontend/.next/standalone/.next/cache"

log "Verifying configuration"
(cd "$REL/backend" && php artisan about --only=environment >/dev/null)
grep -q '^APP_DEBUG=false' "$BASE/shared/backend.env" || { echo "APP_DEBUG must be false in production" >&2; exit 1; }
grep -q '^APP_ENV=production' "$BASE/shared/backend.env" || { echo "APP_ENV must be production" >&2; exit 1; }
grep -Eq '^APP_KEY=base64:.{40,}' "$BASE/shared/backend.env" || { echo "APP_KEY is missing" >&2; exit 1; }
grep -Eq '^INTERNAL_API_TOKEN=.{32,}' "$BASE/shared/backend.env" || { echo "INTERNAL_API_TOKEN is missing (see deploy/env/)" >&2; exit 1; }
if grep -Eq '=<|<domain>|<secret>' "$BASE/shared/backend.env" "$BASE/shared/frontend.env"; then echo "Unfilled <placeholders> remain in shared/*.env" >&2; exit 1; fi

log "Running database migrations (forward-only, additive migrations expected)"
(cd "$REL/backend" && php artisan migrate --force --no-interaction)

log "Optimising"
(cd "$REL/backend" && php artisan optimize && php artisan filament:optimize)

log "Activating release $TS ($SHA)"
ln -sfn "$REL" "$BASE/current.tmp" && mv -Tf "$BASE/current.tmp" "$BASE/current"
sudo systemctl reload php8.3-fpm
(cd "$BASE/current/backend" && php artisan queue:restart)
sudo systemctl restart onushondhan-queue onushondhan-next

log "Health check"
ok=0
for _ in $(seq 1 20); do
  if curl -fsS -m 5 "$HEALTH_URL" | grep -q '"status":"ok"'; then ok=1; break; fi
  sleep 3
done
if [ "$ok" -ne 1 ]; then
  echo "Health check failed — rolling back code to $PREV" >&2
  if [ -n "$PREV" ]; then "$REL/scripts/rollback.sh" "$(basename "$PREV")"; fi
  exit 1
fi

echo "$TS $SHA $(date -u +%FT%TZ)" >> "$BASE/releases.log"
log "Pruning old releases (keeping $KEEP)"
ls -1dt "$BASE"/releases/*/ | tail -n +"$((KEEP + 1))" | xargs -r rm -rf
log "Deployed $SHA"
