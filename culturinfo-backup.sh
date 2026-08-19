#!/bin/bash
# Respaldo consistente de Culturinfo: exportacion SQL + archivos de WordPress.
set -euo pipefail

BACKUP_ROOT="/backups"
WORDPRESS_ROOT="/var/www/html"
DATABASE_NAME="${MARIADB_DATABASE:-culturinfo}"
RETENTION_DAYS="${CULTURINFO_BACKUP_RETENTION_DAYS:-30}"

log() {
  printf '[backup] %s\n' "$*"
}

fail() {
  printf '[backup] ERROR: %s\n' "$*" >&2
  exit 1
}

case "$DATABASE_NAME" in
  ''|*[!A-Za-z0-9_]*) fail "MARIADB_DATABASE contiene caracteres invalidos" ;;
esac

case "$RETENTION_DAYS" in
  ''|*[!0-9]*) fail "CULTURINFO_BACKUP_RETENTION_DAYS debe ser un numero entero" ;;
esac
if [ "$RETENTION_DAYS" -lt 1 ] || [ "$RETENTION_DAYS" -gt 365 ]; then
  fail "CULTURINFO_BACKUP_RETENTION_DAYS debe estar entre 1 y 365"
fi

if [ ! -d "$BACKUP_ROOT" ] || [ -L "$BACKUP_ROOT" ]; then
  fail "$BACKUP_ROOT no existe o es un enlace simbolico"
fi
if ! mountpoint -q "$BACKUP_ROOT"; then
  fail "$BACKUP_ROOT no es un volumen montado; no se creara un respaldo efimero"
fi
if [ ! -d "$WORDPRESS_ROOT" ] || [ -L "$WORDPRESS_ROOT" ]; then
  fail "la ruta de WordPress no es valida"
fi

umask 077
exec 9>"$BACKUP_ROOT/.culturinfo-backup.lock"
if ! flock -n 9; then
  fail "ya existe otro respaldo en ejecucion"
fi

if ! mysqladmin ping --silent >/dev/null 2>&1; then
  fail "MariaDB no esta disponible"
fi

BACKUP_ID="culturinfo-$(date '+%Y%m%d-%H%M%S')"
FINAL_DIRECTORY="$BACKUP_ROOT/$BACKUP_ID"
TEMP_DIRECTORY="$(mktemp -d "$BACKUP_ROOT/.${BACKUP_ID}.tmp.XXXXXX")"

cleanup() {
  if [ -n "${TEMP_DIRECTORY:-}" ] && [ -d "$TEMP_DIRECTORY" ]; then
    case "$TEMP_DIRECTORY" in
      "$BACKUP_ROOT"/.culturinfo-*.tmp.*) rm -rf -- "$TEMP_DIRECTORY" ;;
    esac
  fi
}
trap cleanup EXIT INT TERM

log "Exportando la base de datos..."
mariadb-dump \
  --user=root \
  --single-transaction \
  --quick \
  --routines \
  --triggers \
  --events \
  --hex-blob \
  "$DATABASE_NAME" \
  | gzip -6 > "$TEMP_DIRECTORY/database.sql.gz"

log "Archivando los archivos persistentes de WordPress..."
tar \
  --one-file-system \
  --numeric-owner \
  --ignore-failed-read \
  --warning=no-file-changed \
  --exclude='./wp-content/cache' \
  --exclude='./wp-content/upgrade' \
  --exclude='./.maintenance' \
  -czf "$TEMP_DIRECTORY/wordpress-files.tar.gz" \
  -C "$WORDPRESS_ROOT" .

WORDPRESS_VERSION="$(wp core version --path="$WORDPRESS_ROOT" --skip-plugins --skip-themes --allow-root 2>/dev/null || printf 'desconocida')"
{
  printf 'site=Culturinfo\n'
  printf 'created_at=%s\n' "$(date --iso-8601=seconds)"
  printf 'database=%s\n' "$DATABASE_NAME"
  printf 'wordpress_version=%s\n' "$WORDPRESS_VERSION"
  printf 'contents=database.sql.gz,wordpress-files.tar.gz\n'
} > "$TEMP_DIRECTORY/manifest.txt"

log "Verificando los archivos generados..."
gzip -t "$TEMP_DIRECTORY/database.sql.gz"
tar -tzf "$TEMP_DIRECTORY/wordpress-files.tar.gz" >/dev/null
(
  cd "$TEMP_DIRECTORY"
  sha256sum database.sql.gz wordpress-files.tar.gz manifest.txt > SHA256SUMS
  sha256sum -c SHA256SUMS >/dev/null
)

mv -- "$TEMP_DIRECTORY" "$FINAL_DIRECTORY"
TEMP_DIRECTORY=""

printf '%s\n' "$(date '+%F')" > "$BACKUP_ROOT/.last-success-date.tmp"
mv -- "$BACKUP_ROOT/.last-success-date.tmp" "$BACKUP_ROOT/.last-success-date"

log "Eliminando respaldos con mas de $RETENTION_DAYS dias..."
while IFS= read -r -d '' old_backup; do
  case "$old_backup" in
    "$BACKUP_ROOT"/culturinfo-[0-9]*)
      if [ -d "$old_backup" ] && [ ! -L "$old_backup" ]; then
        rm -rf -- "$old_backup"
      fi
      ;;
  esac
done < <(
  find "$BACKUP_ROOT" -mindepth 1 -maxdepth 1 -type d \
    -name 'culturinfo-[0-9]*' -mtime "+$RETENTION_DAYS" -print0
)

log "Respaldo completado: $FINAL_DIRECTORY"
