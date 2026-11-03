#!/usr/bin/env bash
# Roll the *code* back to a previous release. Database migrations are NOT reversed
# automatically — see docs/DEPLOYMENT.md#rollback. Usage: scripts/rollback.sh [release-dir-name]
set -euo pipefail
BASE="${DEPLOY_BASE:-/var/www/onushondhan}"
CUR="$(basename "$(readlink -f "$BASE/current")")"
TARGET="${1:-$(ls -1t "$BASE/releases" | grep -v "^$CUR$" | head -1)}"
[ -d "$BASE/releases/$TARGET" ] || { echo "No such release: $TARGET" >&2; exit 1; }

echo "Rolling back from $CUR to $TARGET"
ln -sfn "$BASE/releases/$TARGET" "$BASE/current.tmp" && mv -Tf "$BASE/current.tmp" "$BASE/current"
(cd "$BASE/current/backend" && php artisan optimize && php artisan queue:restart)
sudo systemctl reload php8.3-fpm
sudo systemctl restart onushondhan-queue onushondhan-next
echo "rollback $CUR -> $TARGET $(date -u +%FT%TZ)" >> "$BASE/releases.log"
echo "Done. Verify: curl -fsS http://127.0.0.1:8080/api/v1/health"
