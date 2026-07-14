#!/bin/bash
# culturinfo - Entrypoint: ejecuta seed (idempotente) y arranca Apache
set -e

echo "[entrypoint] Iniciando WordPress..."

# Esperar a la DB (intenta wp db check)
for i in $(seq 1 30); do
  if wp --path=/var/www/html db check --allow-root 2>/dev/null; then
    echo "[entrypoint] DB OK"
    break
  fi
  echo "[entrypoint] Esperando DB ($i/30)..."
  sleep 2
done

# Ejecutar seed en background para no bloquear Apache
(
  /usr/local/bin/seed.sh > /tmp/seed.log 2>&1 && echo "[seed] done" || echo "[seed] failed, see /tmp/seed.log"
) &

# Iniciar Apache en primer plano
exec apache2-foreground
