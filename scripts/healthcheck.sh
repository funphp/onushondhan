#!/usr/bin/env bash
# External/cron health probe. Exit 0 when API + frontend are healthy.
#   SITE_URL=https://example.com scripts/healthcheck.sh
set -euo pipefail
SITE="${SITE_URL:?set SITE_URL, e.g. https://example.com}"
api="$(curl -fsS -m 10 "$SITE/api/v1/health")" || { echo "API unreachable"; exit 2; }
echo "$api" | grep -q '"status":"ok"' || { echo "API unhealthy: $api"; exit 1; }
curl -fsS -m 10 -o /dev/null "$SITE/" || { echo "Frontend unreachable"; exit 2; }
echo "OK $api"
