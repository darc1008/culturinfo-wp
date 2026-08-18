#!/bin/sh
# Procesa un audio por ciclo sin bloquear Apache ni sobrecargar el servidor.
set -u

INTERVAL="${CULTURINFO_AUDIO_WORKER_INTERVAL:-15}"
case "$INTERVAL" in
  ''|*[!0-9]*) INTERVAL=15 ;;
esac
if [ "$INTERVAL" -lt 5 ]; then
  INTERVAL=5
fi

cd /var/www/html
while true; do
  sleep "$INTERVAL"
  if [ -f wp-load.php ]; then
    /usr/local/bin/wp cron event run culturinfo_audio_process_queue --due-now --quiet >/dev/null 2>&1 || true
  fi
done
