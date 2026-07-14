#!/bin/bash
# ===========================================
# Culturinfo - WordPress bootstrap
# ===========================================
# Idempotent. Re-running after first success is a no-op.
# Requires: wp-cli available in image (wordpress:cli)

set -e

echo "=== [1/8] Esperando a WordPress ==="
wp --path=/var/www/html core is-installed --allow-root 2>/dev/null || {
  echo "Instalando WordPress core..."
  wp --path=/var/www/html core install \
    --url="${WP_SITEURL}" \
    --title="${WP_TITLE:-Culturinfo}" \
    --admin_user="${WP_ADMIN_USER:-admin}" \
    --admin_password="${WP_ADMIN_PASSWORD}" \
    --admin_email="${WP_ADMIN_EMAIL:-admin@culturinfo.statusloop.app}" \
    --skip-email \
    --allow-root
}

echo "=== [2/8] Ajustes de site ==="
wp --path=/var/www/html option update blogdescription "${WP_TAGLINE:-Periódico digital de cultura, política y actualidad}" --allow-root
wp --path=/var/www/html option update timezone_string "America/Santo_Domingo" --allow-root
wp --path=/var/www/html option update date_format "d/m/Y" --allow-root
wp --path=/var/www/html option update time_format "H:i" --allow-root
wp --path=/var/www/html option update start_of_week "1" --allow-root
wp --path=/var/www/html option update posts_per_page "10" --allow-root
wp --path=/var/www/html option update default_comment_status "open" --allow-root
wp --path=/var/www/html option update show_on_front "posts" --allow-root

echo "=== [3/8] Permalinks ==="
wp --path=/var/www/html rewrite structure "/%postname%/" --allow-root
wp --path=/var/www/html rewrite flush --hard --allow-root

echo "=== [4/8] Instalar tema Newscrunch ==="
wp --path=/var/www/html theme install "https://downloads.wordpress.org/theme/newscrunch.1.5.2.zip" --activate --allow-root

echo "=== [5/8] Plugins esenciales ==="
for PLUGIN in akismet contact-form-7 classic-editor wp-super-cache yoast-seo; do
  if ! wp --path=/var/www/html plugin is-installed "$PLUGIN" --allow-root; then
    wp --path=/var/www/html plugin install "$PLUGIN" --activate --allow-root
  fi
done
# Classic editor: configurar para usar el editor clásico
wp --path=/var/www/html option update classic-editor-replace "classic" --allow-root
wp --path=/var/www/html option update classic-editor-allow-users "allow" --allow-root

echo "=== [6/8] Categorías de secciones ==="
declare -A SECTIONS=(
  ["cultura"]="Cultura"
  ["politica"]="Política"
  ["economia"]="Economía"
  ["tecnologia"]="Tecnología"
  ["deportes"]="Deportes"
  ["opinion"]="Opinión"
  ["mundo"]="Mundo"
)
for SLUG in "${!SECTIONS[@]}"; do
  wp --path=/var/www/html term create category "${SECTIONS[$SLUG]}" --slug="$SLUG" --description="Sección de ${SECTIONS[$SLUG]}" --allow-root 2>/dev/null || true
done

echo "=== [7/8] Menús de navegación ==="
wp --path=/var/www/html menu create "Menú Principal" --allow-root
for SLUG in cultura politica economia tecnologia deportes opinion; do
  CAT_ID=$(wp --path=/var/www/html term list category --slug="$SLUG" --field=term_id --allow-root 2>/dev/null | head -1)
  if [ -n "$CAT_ID" ]; then
    wp --path=/var/www/html menu item add-post-term "Menú Principal" category "$CAT_ID" --allow-root 2>/dev/null || true
  fi
done
MENU_ID=$(wp --path=/var/www/html menu list --fields=term_id,name --allow-root 2>/dev/null | grep "Menú Principal" | awk '{print $1}' | tr -d '|' | head -1)
wp --path=/var/www/html menu location assign "$MENU_ID" primary --allow-root 2>/dev/null || true

echo "=== [8/8] Artículos de ejemplo ==="
wp --path=/var/www/html post list --post_type=post --post_status=publish --format=count --allow-root | head -1 > /tmp/post_count.txt
EXISTING=$(cat /tmp/post_count.txt)
if [ "${EXISTING:-0}" -lt 5 ]; then
  for FILE in /seed/articles/*.md; do
    if [ -f "$FILE" ]; then
      echo "  Importando $(basename "$FILE")"
      wp --path=/var/www/html post create "$FILE" \
        --post_type=post \
        --post_status=publish \
        --post_format=standard \
        --allow-root 2>&1 | tail -1
    fi
  done
else
  echo "  Ya hay $EXISTING artículos, saltando seed"
fi

echo "=== ✓ Bootstrap completado ==="
wp --path=/var/www/html option get blogname --allow-root
wp --path=/var/www/html option get blogdescription --allow-root
wp --path=/var/www/html post list --post_type=post --post_status=publish --format=count --allow-root
