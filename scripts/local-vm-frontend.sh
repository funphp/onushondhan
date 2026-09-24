#!/usr/bin/env bash
# Rebuild the Next.js frontend on the host and ship it into the local Vagrant VM,
# where the onushondhan-next systemd unit serves it behind Apache (onusondhan.local).
# Usage (on the Mac): scripts/local-vm-frontend.sh
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"
VAGRANT_DIR="${VAGRANT_DIR:-$ROOT/..}"
SITE="${SITE:-http://onusondhan.local}"

cd "$ROOT/frontend"
rm -rf .next/cache/fetch-cache
API_INTERNAL_URL="$SITE" NEXT_PUBLIC_SITE_URL="$SITE" NEXT_PUBLIC_ADMIN_URL="$SITE/admin" npm run build
cp -r .next/static .next/standalone/.next/static
cp -r public .next/standalone/public

cd .next/standalone
COPYFILE_DISABLE=1 tar czf - --no-xattrs --exclude='./.env*' . \
  | (cd "$VAGRANT_DIR" && vagrant ssh -c 'sudo systemctl stop onushondhan-next && sudo rm -rf /opt/onushondhan-web && sudo mkdir -p /opt/onushondhan-web && sudo tar xzf - -C /opt/onushondhan-web 2>/dev/null; sudo chown -R www-data:www-data /opt/onushondhan-web && sudo systemctl start onushondhan-next && systemctl is-active onushondhan-next')
echo "Deployed. Open $SITE"
