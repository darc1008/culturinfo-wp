#!/bin/bash
# Compatibilidad con despliegues anteriores.
# La inicialización canónica vive en seed.sh.
set -e

if [ -x /usr/local/bin/seed.sh ]; then
  exec /usr/local/bin/seed.sh
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
exec "$SCRIPT_DIR/seed.sh"
