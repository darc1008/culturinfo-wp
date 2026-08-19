#!/bin/bash
# culturinfo - Entrypoint: inicializa MariaDB local, ejecuta seed, arranca Apache
set -euo pipefail

echo "[entrypoint] Iniciando culturinfo..."

MARIADB_DATABASE="${MARIADB_DATABASE:-culturinfo}"
MARIADB_USER="${MARIADB_USER:-culturinfo}"
if [ -z "${MARIADB_PASSWORD:-}" ]; then
  echo "ERROR: falta la variable obligatoria MARIADB_PASSWORD"
  exit 1
fi
if [[ ! "$MARIADB_DATABASE" =~ ^[A-Za-z0-9_]+$ ]]; then
  echo "ERROR: MARIADB_DATABASE solo puede contener letras, números y guion bajo"
  exit 1
fi
if [[ ! "$MARIADB_USER" =~ ^[A-Za-z0-9_]+$ ]]; then
  echo "ERROR: MARIADB_USER solo puede contener letras, números y guion bajo"
  exit 1
fi

sql_string() {
  local value="$1"
  value="${value//\\/\\\\}"
  value="${value//\'/\'\'}"
  printf '%s' "$value"
}

MARIADB_PASSWORD_SQL="$(sql_string "$MARIADB_PASSWORD")"

culturinfo_backups_enabled() {
  case "${CULTURINFO_BACKUPS_ENABLED:-false}" in
    true|TRUE|1|yes|YES) return 0 ;;
    *) return 1 ;;
  esac
}

validate_wordpress_path() {
  local path="$1"
  local resolved
  case "$path" in
    /var/www/html|/var/www/html/*) ;;
    *) echo "ERROR: ruta fuera de WordPress: $path"; exit 1 ;;
  esac
  case "/$path/" in
    */../*|*/./*) echo "ERROR: ruta WordPress no normalizada: $path"; exit 1 ;;
  esac
  if [ -L "$path" ]; then
    echo "ERROR: no se permite enlace simbólico: $path"
    exit 1
  fi
  mkdir -p "$path"
  resolved="$(readlink -f "$path")"
  if [ "$resolved" != "/var/www/html" ] && [ "${resolved#/var/www/html/}" = "$resolved" ]; then
    echo "ERROR: ruta resuelta fuera de WordPress: $resolved"
    exit 1
  fi
}

sync_versioned_tree() {
  local source="$1"
  local destination="$2"
  validate_wordpress_path "$destination"
  if [ ! -d "$source" ] || [ -L "$source" ]; then
    echo "ERROR: fuente versionada inválida: $source"
    exit 1
  fi
  cp -a "$source"/. "$destination"/
}

# Inicializar MariaDB si la base está vacía
if [ ! -d /var/lib/mysql/mysql ]; then
  echo "[entrypoint] Inicializando MariaDB..."
  mysql_install_db --user=mysql --datadir=/var/lib/mysql > /dev/null
fi

# Si /var/www/html está vacío (volumen nuevo en primer arranque), copiar el WP de la imagen
if [ ! -f /var/www/html/wp-load.php ]; then
  echo "[entrypoint] Copiando WordPress core al volumen..."
  cp -a /usr/src/wordpress/. /var/www/html/
fi

# Sincronizar el tema editorial versionado en cada despliegue.
# El directorio de WordPress es persistente, por eso el tema se copia al arrancar.
echo "[entrypoint] Sincronizando código versionado..."
sync_versioned_tree /opt/culturinfo/theme /var/www/html/wp-content/themes/culturinfo
for plugin in culturinfo-ads culturinfo-authors culturinfo-stats culturinfo-publishing culturinfo-contact culturinfo-audio culturinfo-security; do
  sync_versioned_tree "/opt/culturinfo/plugins/$plugin" "/var/www/html/wp-content/plugins/$plugin"
done

# Asegurar permisos
chown -R mysql:mysql /var/lib/mysql /var/run/mysqld

# Iniciar MariaDB
echo "[entrypoint] Iniciando MariaDB..."
/usr/bin/mysqld_safe --datadir=/var/lib/mysql --user=mysql > /var/log/mariadb-startup.log 2>&1 &
sleep 5

# Esperar a MariaDB
for i in $(seq 1 20); do
  if mysqladmin ping --silent 2>/dev/null; then
    echo "[entrypoint] MariaDB OK"
    break
  fi
  sleep 1
done

# Crear DB y usuario si no existen
mysql -uroot <<EOSQL
CREATE DATABASE IF NOT EXISTS \`${MARIADB_DATABASE}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${MARIADB_USER}'@'localhost' IDENTIFIED BY '${MARIADB_PASSWORD_SQL}';
CREATE USER IF NOT EXISTS '${MARIADB_USER}'@'127.0.0.1' IDENTIFIED BY '${MARIADB_PASSWORD_SQL}';
GRANT ALL PRIVILEGES ON \`${MARIADB_DATABASE}\`.* TO '${MARIADB_USER}'@'localhost';
GRANT ALL PRIVILEGES ON \`${MARIADB_DATABASE}\`.* TO '${MARIADB_USER}'@'127.0.0.1';
FLUSH PRIVILEGES;
EOSQL
echo "[entrypoint] MariaDB ready"

# Generar wp-config.php (NEEDED - the image doesn't ship one for WP 6.7+)
cd /var/www/html
if [ ! -f wp-config.php ]; then
  echo "[entrypoint] Creando wp-config.php..."
  wp config create \
    --dbhost=127.0.0.1 \
    --dbname="${MARIADB_DATABASE}" \
    --dbuser="${MARIADB_USER}" \
    --dbpass="${MARIADB_PASSWORD}" \
    --dbcharset=utf8mb4 \
    --dbcollate=utf8mb4_unicode_ci \
    --locale="${WP_LOCALE:-es_ES}" \
    --allow-root
fi

# WordPress está detrás del proxy HTTPS de Cloudflare/Coolify. Esta corrección
# también se aplica a wp-config.php persistentes creados en despliegues previos.
echo "[entrypoint] Configurando detección HTTPS del proxy..."
php /seed/configure_proxy.php /var/www/html/wp-config.php

echo "[entrypoint] Configurando seguridad de archivos..."
php /seed/configure_security.php /var/www/html/wp-config.php

# El core vive en un volumen. Actualizarlo explícitamente desde el paquete
# inmutable de la imagen antes de cargar tema o plugins.
CURRENT_CORE_VERSION="$(wp core version --allow-root)"
if [ "$CURRENT_CORE_VERSION" != "7.0.2" ]; then
  if wp core is-installed --skip-plugins --skip-themes --allow-root >/dev/null 2>&1; then
    if ! culturinfo_backups_enabled; then
      echo "ERROR: se requiere CULTURINFO_BACKUPS_ENABLED=true para respaldar WordPress antes de migrar $CURRENT_CORE_VERSION -> 7.0.2"
      exit 1
    fi
    echo "[entrypoint] Creando respaldo obligatorio antes de actualizar WordPress..."
    /usr/local/bin/culturinfo-backup.sh
  fi
  echo "[entrypoint] Actualizando WordPress $CURRENT_CORE_VERSION -> 7.0.2..."
  wp core update /opt/culturinfo/core/wordpress-7.0.2-no-content.zip \
    --force --skip-plugins --skip-themes --allow-root
fi
if [ "$(wp core version --allow-root)" != "7.0.2" ]; then
  echo "ERROR: WordPress no quedó en la versión 7.0.2"
  exit 1
fi
if wp core is-installed --skip-plugins --skip-themes --allow-root >/dev/null 2>&1; then
  wp core update-db --skip-plugins --skip-themes --allow-root
fi

# Run seed (foreground, with output to stdout)
echo "[entrypoint] Ejecutando seed..."
/usr/local/bin/seed.sh 2>&1 | tee /tmp/seed.log
echo "[seed] done"

# El seed usa WP-CLI como root y puede crear uploads/año/mes con propietario
# root. Apache necesita escritura únicamente en uploads para recibir medios.
UPLOADS_DIR="/var/www/html/wp-content/uploads"
if [ -L "$UPLOADS_DIR" ]; then
  echo "ERROR: uploads no puede ser un enlace simbólico"
  exit 1
fi

mkdir -p "$UPLOADS_DIR"
UPLOADS_REAL="$(readlink -f "$UPLOADS_DIR")"
if [ "$UPLOADS_REAL" != "$UPLOADS_DIR" ]; then
  echo "ERROR: ruta de uploads inesperada: $UPLOADS_REAL"
  exit 1
fi

echo "[entrypoint] Ajustando permisos de medios..."
find "$UPLOADS_REAL" -xdev -type d -exec chown www-data:www-data {} + -exec chmod 775 {} +
find "$UPLOADS_REAL" -xdev -type f -exec chown www-data:www-data {} + -exec chmod 664 {} +

echo "[entrypoint] Bloqueando core, temas y plugins contra escritura web..."
find /var/www/html -xdev -path "$UPLOADS_REAL" -prune -o -type d -exec chown root:www-data {} + -exec chmod 755 {} +
find /var/www/html -xdev -path "$UPLOADS_REAL" -prune -o -type f -exec chown root:www-data {} + -exec chmod 644 {} +
chown root:www-data /var/www/html/wp-config.php
chmod 640 /var/www/html/wp-config.php

echo "[entrypoint] Iniciando trabajador de audio..."
runuser -u www-data -- /usr/local/bin/audio-worker.sh &

if culturinfo_backups_enabled; then
  echo "[entrypoint] Iniciando respaldos automaticos..."
  /usr/local/bin/backup-worker.sh &
else
  echo "[entrypoint] Respaldos automaticos deshabilitados"
fi

# Iniciar Apache
echo "[entrypoint] Iniciando Apache..."
exec apache2-foreground
