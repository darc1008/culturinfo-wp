#!/bin/bash
# Ejecuta un respaldo diario a la hora configurada y reintenta si falla.
set -u

BACKUP_HOUR_RAW="${CULTURINFO_BACKUP_HOUR:-3}"
POLL_SECONDS="${CULTURINFO_BACKUP_POLL_SECONDS:-300}"
BACKUP_ROOT="/backups"

case "$BACKUP_HOUR_RAW" in
  ''|*[!0-9]*)
    echo "[backup-worker] ERROR: CULTURINFO_BACKUP_HOUR debe ser un entero entre 0 y 23" >&2
    exit 1
    ;;
esac
BACKUP_HOUR=$((10#$BACKUP_HOUR_RAW))
if [ "$BACKUP_HOUR" -lt 0 ] || [ "$BACKUP_HOUR" -gt 23 ]; then
  echo "[backup-worker] ERROR: CULTURINFO_BACKUP_HOUR debe estar entre 0 y 23" >&2
  exit 1
fi

case "$POLL_SECONDS" in
  ''|*[!0-9]*)
    echo "[backup-worker] ERROR: CULTURINFO_BACKUP_POLL_SECONDS debe ser un entero" >&2
    exit 1
    ;;
esac
if [ "$POLL_SECONDS" -lt 60 ] || [ "$POLL_SECONDS" -gt 3600 ]; then
  echo "[backup-worker] ERROR: CULTURINFO_BACKUP_POLL_SECONDS debe estar entre 60 y 3600" >&2
  exit 1
fi

printf '[backup-worker] Respaldo diario habilitado a las %02d:00; zona horaria: %s\n' \
  "$BACKUP_HOUR" "${TZ:-UTC}"

while true; do
  today="$(date '+%F')"
  now_hour=$((10#$(date '+%H')))
  last_success=""
  if [ -f "$BACKUP_ROOT/.last-success-date" ] && [ ! -L "$BACKUP_ROOT/.last-success-date" ]; then
    last_success="$(head -n 1 "$BACKUP_ROOT/.last-success-date" 2>/dev/null || true)"
  fi

  if [ "$now_hour" -ge "$BACKUP_HOUR" ] && [ "$last_success" != "$today" ]; then
    if ! /usr/local/bin/culturinfo-backup.sh; then
      echo "[backup-worker] El respaldo fallo; se reintentara en $POLL_SECONDS segundos" >&2
    fi
  fi

  sleep "$POLL_SECONDS"
done
