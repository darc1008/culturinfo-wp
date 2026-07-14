#!/bin/bash
# culturinfo - Entrypoint: inicializa MariaDB local, ejecuta seed, arranca Apache
set -e

echo "[entrypoint] Iniciando culturinfo..."

# Inicializar MariaDB si la base está vacía
if [ ! -d /var/lib/mysql/mysql ]; then
  echo "[entrypoint] Inicializando MariaDB..."
  mysql_install_db --user=mysql --datadir=/var/lib/mysql > /dev/null
fi

# Si /var/www/html está vacío (volumen nuevo en primer arranque), copiar el WP de la imagen
if [ ! -f /var/www/html/wp-load.php ]; then
  echo "[entrypoint] Copiando WordPress core al volumen..."
  cp -a /usr/src/wordpress/. /var/www/html/
  chown -R www-data:www-data /var/www/html
fi

# Asegurar permisos
chown -R mysql:mysql /var/lib/mysql /var/run/mysqld 2>/dev/null || true

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
CREATE DATABASE IF NOT EXISTS \`${MARIADB_DATABASE:-culturinfo}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '${MARIADB_USER:-culturinfo}'@'localhost' IDENTIFIED BY '${MARIADB_PASSWORD}';
CREATE USER IF NOT EXISTS '${MARIADB_USER:-culturinfo}'@'127.0.0.1' IDENTIFIED BY '${MARIADB_PASSWORD}';
GRANT ALL PRIVILEGES ON \`${MARIADB_DATABASE:-culturinfo}\`.* TO '${MARIADB_USER:-culturinfo}'@'localhost';
GRANT ALL PRIVILEGES ON \`${MARIADB_DATABASE:-culturinfo}\`.* TO '${MARIADB_USER:-culturinfo}'@'127.0.0.1';
FLUSH PRIVILEGES;
EOSQL
echo "[entrypoint] MariaDB ready"

# Generar wp-config.php (NEEDED - the image doesn't ship one for WP 6.7+)
cd /var/www/html
if [ ! -f wp-config.php ]; then
  echo "[entrypoint] Creando wp-config.php..."
  wp config create \
    --dbhost=127.0.0.1 \
    --dbname="${MARIADB_DATABASE:-culturinfo}" \
    --dbuser="${MARIADB_USER:-culturinfo}" \
    --dbpass="${MARIADB_PASSWORD}" \
    --dbcharset=utf8mb4 \
    --dbcollate=utf8mb4_unicode_ci \
    --locale=es_ES \
    --allow-root
fi

# Run seed (foreground, with output to stdout)
echo "[entrypoint] Ejecutando seed..."
/usr/local/bin/seed.sh 2>&1 | tee /tmp/seed.log
echo "[seed] done"

# Iniciar Apache
echo "[entrypoint] Iniciando Apache..."
exec apache2-foreground
