#!/usr/bin/env bash
# Encrypted backup of the MySQL database and the private file vault.
# Configure via environment (e.g. /etc/onushondhan/backup.env, never in git):
#   BACKUP_DIR=/var/backups/onushondhan        local staging directory
#   BACKUP_AGE_RECIPIENT=age1...                public key; the private key stays OFF this server
#   DB_HOST DB_PORT DB_DATABASE DB_USERNAME DB_PASSWORD   (a read-only backup user is recommended)
#   VAULT_PATH=/var/www/onushondhan/shared/storage/app/private/vault
#   BACKUP_RETENTION_DAYS=30
#   BACKUP_REMOTE=user@backup-host:/srv/onushondhan   optional rsync target (separate location)
set -euo pipefail
: "${BACKUP_DIR:?}" "${BACKUP_AGE_RECIPIENT:?}" "${DB_DATABASE:?}" "${DB_USERNAME:?}" "${VAULT_PATH:?}"
command -v age >/dev/null || { echo "Install 'age' (https://age-encryption.org)" >&2; exit 1; }

TS="$(date -u +%Y%m%dT%H%M%SZ)"
mkdir -p "$BACKUP_DIR"
umask 077
MYCNF="$(mktemp)"; trap 'rm -f "$MYCNF"' EXIT
printf '[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n' "${DB_HOST:-127.0.0.1}" "${DB_PORT:-3306}" "$DB_USERNAME" "${DB_PASSWORD:-}" > "$MYCNF"

echo "Dumping database $DB_DATABASE"
mysqldump --defaults-extra-file="$MYCNF" --single-transaction --quick --routines --triggers --hex-blob "$DB_DATABASE" \
  | gzip -9 | age -r "$BACKUP_AGE_RECIPIENT" > "$BACKUP_DIR/db-$TS.sql.gz.age"

echo "Archiving private vault"
tar -C "$(dirname "$VAULT_PATH")" -czf - "$(basename "$VAULT_PATH")" | age -r "$BACKUP_AGE_RECIPIENT" > "$BACKUP_DIR/vault-$TS.tar.gz.age"

sha256sum "$BACKUP_DIR"/*-"$TS".*.age > "$BACKUP_DIR/SHA256SUMS-$TS"

if [ -n "${BACKUP_REMOTE:-}" ]; then
  echo "Copying to $BACKUP_REMOTE"
  rsync -a "$BACKUP_DIR"/*-"$TS"* "$BACKUP_REMOTE/"
fi

find "$BACKUP_DIR" -type f -mtime +"${BACKUP_RETENTION_DAYS:-30}" -delete
echo "Backup $TS complete"
