#!/usr/bin/env bash
# Restore a backup made by backup.sh. DESTRUCTIVE: overwrites the target database and
# vault. Run only with explicit human authorisation, ideally first into a staging DB.
#
#   AGE_IDENTITY=/secure/path/key.txt DB_DATABASE=onushondhan_restore_test \
#   VAULT_PATH=/tmp/vault-restore scripts/restore.sh <timestamp> --i-understand-this-overwrites-data
set -euo pipefail
TS="${1:?usage: restore.sh <timestamp> --i-understand-this-overwrites-data}"
[ "${2:-}" = "--i-understand-this-overwrites-data" ] || { echo "Refusing: pass --i-understand-this-overwrites-data" >&2; exit 1; }
: "${BACKUP_DIR:?}" "${AGE_IDENTITY:?}" "${DB_DATABASE:?}" "${DB_USERNAME:?}" "${VAULT_PATH:?}"

(cd "$BACKUP_DIR" && sha256sum -c "SHA256SUMS-$TS")
MYCNF="$(mktemp)"; trap 'rm -f "$MYCNF"' EXIT
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "${DB_HOST:-127.0.0.1}" "${DB_PORT:-3306}" "$DB_USERNAME" "${DB_PASSWORD:-}" > "$MYCNF"

echo "Restoring database into $DB_DATABASE"
age -d -i "$AGE_IDENTITY" "$BACKUP_DIR/db-$TS.sql.gz.age" | gunzip | mysql --defaults-extra-file="$MYCNF" "$DB_DATABASE"

echo "Restoring vault into $VAULT_PATH"
mkdir -p "$VAULT_PATH"
age -d -i "$AGE_IDENTITY" "$BACKUP_DIR/vault-$TS.tar.gz.age" | tar -C "$VAULT_PATH" --strip-components=1 -xzf -

echo "Restore complete. Next: php artisan audit:verify && php artisan migrate:status"
