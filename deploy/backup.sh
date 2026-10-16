#!/usr/bin/env bash
# Còpia de seguretat de GestPartits: base de dades (mysqldump consistent) i fitxers privats
# (escuts i imatges generades). Es conserven els últims KEEP_DAYS dies.
#
# Ús: deploy/backup.sh            (l'executa el cron cada nit, vegeu deploy/cron/gestpartits)
# Restauració:
#   gunzip -c db-AAAAMMDD-HHMM.sql.gz | mariadb gestpartits
#   tar -xzf storage-AAAAMMDD-HHMM.tar.gz -C /var/www/gestpartits
set -euo pipefail

APP_DIR="${APP_DIR:-/var/www/gestpartits}"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/gestpartits}"
KEEP_DAYS="${KEEP_DAYS:-30}"
STAMP="$(date +%Y%m%d-%H%M)"

env_value() {
    grep -E "^$1=" "$APP_DIR/.env" | tail -n1 | cut -d= -f2- | sed -e 's/^"//' -e 's/"$//'
}

DB_NAME="$(env_value DB_DATABASE)"
DB_USER="$(env_value DB_USERNAME)"
DB_PASS="$(env_value DB_PASSWORD)"
DB_HOST="$(env_value DB_HOST)"

umask 077
mkdir -p "$BACKUP_DIR"

# La contrasenya es passa per un fitxer temporal (no apareix a la llista de processos).
CNF="$(mktemp)"
trap 'rm -f "$CNF"' EXIT
printf '[client]\nuser=%s\npassword=%s\nhost=%s\n' "$DB_USER" "$DB_PASS" "${DB_HOST:-127.0.0.1}" > "$CNF"

mariadb-dump --defaults-extra-file="$CNF" --single-transaction --routines --triggers "$DB_NAME" | gzip > "$BACKUP_DIR/db-$STAMP.sql.gz"
tar -czf "$BACKUP_DIR/storage-$STAMP.tar.gz" -C "$APP_DIR" storage/app/private
cp "$APP_DIR/.env" "$BACKUP_DIR/env-$STAMP"

find "$BACKUP_DIR" -type f -mtime +"$KEEP_DAYS" -delete

echo "$(date -Is) Còpia feta a $BACKUP_DIR ($STAMP)"
