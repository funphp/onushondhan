#!/usr/bin/env bash
# Deploy the committed HEAD to a cPanel / CloudLinux shared-hosting account (no root, no systemd).
# Run on a developer machine with SSH key access. See docs/DEPLOYMENT-CPANEL.md.
#
#   DEPLOY_SSH=user@host SITE_URL=https://example.com ADMIN_URL=https://admin.example.com scripts/deploy-cpanel.sh
#
# Server layout (under $HOME/$DEPLOY_DIR):
#   current/            live release (a real directory: LiteSpeed does not serve a symlinked docroot)
#     backend/public    document root of the admin/API subdomain
#     frontend/         cPanel Node.js app root (Next.js standalone server.js)
#   releases/           staged and previous releases (last $KEEP kept for rollback)
#   shared/backend.env  production .env (never in git)    shared/storage/  Laravel storage + private vault
#
# Order: build locally → upload new release → composer install → migrate (additive only) → cache →
# swap directories → restart Node app + queue → health check → automatic swap-back on failure.
# Rollback later: scripts/deploy-cpanel.sh --rollback
set -euo pipefail

: "${DEPLOY_SSH:?set DEPLOY_SSH=user@host}"
DIR="${DEPLOY_DIR:-onusondhan}"
PHP="${DEPLOY_PHP:-/opt/cpanel/ea-php83/root/usr/bin/php}"
KEEP="${DEPLOY_KEEP_RELEASES:-3}"
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SSH=(ssh -o BatchMode=yes "$DEPLOY_SSH")

log() { printf '\n==> %s\n' "$*"; }

remote_swap_back() {
  "${SSH[@]}" "set -e; cd ~/$DIR; prev=\$(ls -1dt releases/prev-* 2>/dev/null | head -1); [ -n \"\$prev\" ] || { echo 'No previous release to roll back to' >&2; exit 1; }
    mv current releases/failed-\$(date -u +%Y%m%d%H%M%S); mv \"\$prev\" current
    cloudlinux-selector restart --json --interpreter nodejs --app-root $DIR/current/frontend >/dev/null
    cd current/backend && $PHP artisan optimize >/dev/null && $PHP artisan queue:restart >/dev/null; echo \"Rolled back to \$(cat ~/$DIR/current/RELEASE)\""
}

if [ "${1:-}" = "--rollback" ]; then
  log "Rolling back to the previous release"
  remote_swap_back
  exit 0
fi

: "${SITE_URL:?set SITE_URL, e.g. https://example.com}"
: "${ADMIN_URL:?set ADMIN_URL (Laravel API/admin host), e.g. https://admin.example.com}"

cd "$ROOT"
git diff --quiet && git diff --cached --quiet || { echo "Commit or stash your changes first — only committed code is deployed." >&2; exit 1; }
SHA="$(git rev-parse --short HEAD)"
TS="$(date -u +%Y%m%d%H%M%S)"
REL="releases/$TS"

log "Building frontend ($SHA) for $SITE_URL"
(cd frontend && rm -rf .next && API_INTERNAL_URL="$ADMIN_URL" NEXT_PUBLIC_SITE_URL="$SITE_URL" NEXT_PUBLIC_ADMIN_URL="$ADMIN_URL/admin" npm run build)
cp -r frontend/.next/static frontend/.next/standalone/.next/static
cp -r frontend/public frontend/.next/standalone/public

log "Uploading release $TS"
"${SSH[@]}" "set -e; umask 027; mkdir -p ~/$DIR/$REL/frontend; echo '$TS $SHA' > ~/$DIR/$REL/RELEASE"
git archive --format=tar.gz HEAD backend scripts deploy | "${SSH[@]}" "tar xzf - -C ~/$DIR/$REL"
# Native image binaries built for this machine are not used (no next/image) and would not load on Linux.
(cd frontend/.next/standalone && COPYFILE_DISABLE=1 tar czf - --exclude='./node_modules/@img' --exclude='./.env*' .) \
  | "${SSH[@]}" "tar xzf - -C ~/$DIR/$REL/frontend 2>/dev/null; mkdir -p ~/$DIR/$REL/frontend/.next/cache ~/$DIR/$REL/frontend/tmp"

log "Installing backend dependencies, migrating and caching"
"${SSH[@]}" "set -e; cd ~/$DIR/$REL/backend
  grep -q '^APP_ENV=production' ~/$DIR/shared/backend.env && grep -q '^APP_DEBUG=false' ~/$DIR/shared/backend.env || { echo 'shared/backend.env must have APP_ENV=production and APP_DEBUG=false' >&2; exit 1; }
  ln -sfn ~/$DIR/shared/backend.env .env
  rm -rf storage && ln -sfn ~/$DIR/shared/storage storage
  $PHP -d memory_limit=1G ~/$DIR/bin/composer install --no-dev --prefer-dist --no-interaction --optimize-autoloader --classmap-authoritative --quiet
  chmod 711 ~/$DIR/$REL ~/$DIR/$REL/backend && chmod -R go+rX public
  $PHP artisan migrate --force --no-interaction
  $PHP artisan optimize >/dev/null && $PHP artisan filament:optimize >/dev/null"

log "Activating release $TS"
"${SSH[@]}" "set -e; cd ~/$DIR
  mv current releases/prev-$TS && mv $REL current
  cloudlinux-selector restart --json --interpreter nodejs --app-root $DIR/current/frontend >/dev/null
  cd current/backend && $PHP artisan queue:restart >/dev/null"

log "Health check"
ok=0
for _ in $(seq 1 12); do
  if curl -fsS -m 10 "$ADMIN_URL/api/v1/health" | grep -q '"status":"ok"' && curl -fsS -m 30 -o /dev/null "$SITE_URL/"; then ok=1; break; fi
  sleep 5
done
if [ "$ok" -ne 1 ]; then
  echo "Health check failed — restoring the previous release (database migrations are not reversed)." >&2
  remote_swap_back
  exit 1
fi

log "Pruning old releases (keeping $KEEP)"
"${SSH[@]}" "cd ~/$DIR/releases && ls -1dt prev-* 2>/dev/null | tail -n +$((KEEP + 1)) | xargs -r rm -rf; ls -1dt failed-* 2>/dev/null | tail -n +2 | xargs -r rm -rf"
log "Deployed $SHA to $SITE_URL"
