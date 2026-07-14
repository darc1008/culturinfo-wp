#!/bin/bash
# One-shot WordPress bootstrap for culturinfo.
# Idempotent. Triggered by Coolify "Post-startup" or manually via `docker exec`.
DB_HOST="${WORDPRESS_DB_HOST:-127.0.0.1}"
DB_USER="${WORDPRESS_DB_USER:-culturinfo}"
DB_PASS="${WORDPRESS_DB_PASSWORD:-Cult1nf0_M4r1adb_2026!}"
DB_NAME="${WORDPRESS_DB_NAME:-culturinfo}"

# Set DB env for wp-cli
export WORDPRESS_DB_HOST="$DB_HOST"
export WORDPRESS_DB_USER="$DB_USER"
export WORDPRESS_DB_PASSWORD="$DB_PASS"
export WORDPRESS_DB_NAME="$DB_NAME"

echo "==> WordPress DB target: $DB_USER@$DB_HOST/$DB_NAME"

# Generate wp-config.php if missing
cd /var/www/html
if [ ! -f wp-config.php ]; then
  echo "==> Creando wp-config.php"
  wp config create \
    --dbhost="$DB_HOST" \
    --dbname="$DB_NAME" \
    --dbuser="$DB_USER" \
    --dbpass="$DB_PASS" \
    --dbcharset=utf8mb4 \
    --dbcollate=utf8mb4_unicode_ci \
    --locale=es_ES \
    --allow-root
fi

# Wait for DB
for i in {1..30}; do
  if wp --path=/var/www/html db check --allow-root 2>/dev/null; then
    echo "  db OK"
    break
  fi
  echo "  waiting for db ($i)..."
  sleep 2
done

# Install WP if not installed
if ! wp --path=/var/www/html core is-installed --allow-root 2>/dev/null; then
  echo "==> Installing WordPress core"
  wp --path=/var/www/html core install \
    --url="${WP_SITEURL:-https://culturinfo.statusloop.app}" \
    --title="${WP_TITLE:-Culturinfo}" \
    --admin_user="${WP_ADMIN_USER:-admin}" \
    --admin_password="${WP_ADMIN_PASSWORD}" \
    --admin_email="${WP_ADMIN_EMAIL:-admin@culturinfo.statusloop.app}" \
    --skip-email --allow-root
else
  echo "==> WordPress ya instalado, saltando install"
fi

echo "==> Site settings"
wp --path=/var/www/html option update blogdescription "${WP_TAGLINE:-Periódico digital de cultura, política y actualidad}" --allow-root
wp --path=/var/www/html option update timezone_string "America/Santo_Domingo" --allow-root
wp --path=/var/www/html option update date_format "d/m/Y" --allow-root
wp --path=/var/www/html option update time_format "H:i" --allow-root
wp --path=/var/www/html option update start_of_week "1" --allow-root
wp --path=/var/www/html option update posts_per_page "10" --allow-root
wp --path=/var/www/html option update default_comment_status "open" --allow-root

echo "==> Permalinks"
wp --path=/var/www/html rewrite structure "/%postname%/" --allow-root
wp --path=/var/www/html rewrite flush --hard --allow-root

echo "==> ColorMag theme (newspaper/magazine layout)"
# Limpiar newscrunch
wp --path=/var/www/html theme uninstall newscrunch --allow-root 2>/dev/null || true
# Instalar ColorMag
if ! wp --path=/var/www/html theme is-installed colormag --allow-root 2>/dev/null; then
  echo "  Instalando ColorMag..."
  wp --path=/var/www/html theme install "https://downloads.wordpress.org/theme/colormag.3.2.1.zip" --allow-root 2>&1 | tail -3
fi
wp --path=/var/www/html theme activate colormag --allow-root 2>&1 | tail -1

# Companion plugin (ThemeGrill Toolkit) para widgets y customizer
if ! wp --path=/var/www/html plugin is-installed themegrill-tools --allow-root 2>/dev/null; then
  wp --path=/var/www/html plugin install themegrill-tools --allow-root 2>&1 | tail -2
fi
wp --path=/var/www/html plugin activate themegrill-tools --allow-root 2>&1 | tail -1

# Configuración ColorMag
wp --path=/var/www/html option update colormag_site_layout "wide_layout" --allow-root
wp --path=/var/www/html option update colormag_primary_color "e74c3c" --allow-root  # rojo periodístico
wp --path=/var/www/html option update colormag_secondary_color "2c3e50" --allow-root
wp --path=/var/www/html option update colormag_header_logo_placement "header_text_only" --allow-root
wp --path=/var/www/html option update colormag_enable_featured_image_slider "1" --allow-root
wp --path=/var/www/html option update colormag_enable_breaking_news "1" --allow-root
wp --path=/var/www/html option update colormag_breaking_news_title "Última Hora" --allow-root
wp --path=/var/www/html option update colormag_hide_blog_post_feature_image "" --allow-root
wp --path=/var/www/html option update colormag_blog_post_excerpt_length "40" --allow-root
wp --path=/var/www/html option update colormag_default_widgets "yes" --allow-root

echo "==> Essential plugins"
for PLUGIN in akismet contact-form-7 classic-editor seo-by-rank-math; do
  if ! wp --path=/var/www/html plugin is-installed "$PLUGIN" --allow-root 2>/dev/null; then
    wp --path=/var/www/html plugin install "$PLUGIN" --allow-root 2>&1 | tail -2
  fi
  wp --path=/var/www/html plugin activate "$PLUGIN" --allow-root 2>&1 | tail -1
done
wp --path=/var/www/html option update classic-editor-replace "classic" --allow-root
wp --path=/var/www/html option update classic-editor-allow-users "allow" --allow-root

echo "==> Categories"
declare -A SECTIONS=(
  [cultura]="Cultura"
  [politica]="Política"
  [economia]="Economía"
  [tecnologia]="Tecnología"
  [deportes]="Deportes"
  [opinion]="Opinión"
  [mundo]="Mundo"
)
for SLUG in "${!SECTIONS[@]}"; do
  wp --path=/var/www/html term create category "${SECTIONS[$SLUG]}" --slug="$SLUG" --description="Sección de ${SECTIONS[$SLUG]}" --allow-root 2>/dev/null || true
done

echo "==> Navigation menu"
MENU_EXISTS=$(wp --path=/var/www/html menu list --fields=term_id --allow-root 2>/dev/null | grep -c . || echo 0)
if [ "$MENU_EXISTS" -eq 0 ]; then
  wp --path=/var/www/html menu create "Menú Principal" --allow-root
  for SLUG in cultura politica economia tecnologia deportes opinion mundo; do
    CAT_ID=$(wp --path=/var/www/html term list category --slug="$SLUG" --field=term_id --allow-root 2>/dev/null | head -1)
    [ -n "$CAT_ID" ] && wp --path=/var/www/html menu item add-post-term "Menú Principal" category "$CAT_ID" --allow-root 2>/dev/null || true
  done
  MENU_ID=$(wp --path=/var/www/html menu list --fields=term_id,name --allow-root 2>/dev/null | awk -F'|' '/Menú Principal/ {gsub(/ /,"",$1); print $1; exit}')
  [ -n "$MENU_ID" ] && wp --path=/var/www/html menu location assign "$MENU_ID" primary --allow-root
fi

parse_frontmatter() {
  local FILE="$1"
  local FIELD="$2"
  # Extrae la primera ocurrencia de "FIELD:" y captura el valor hasta fin de línea
  sed -n "/^${FIELD}:/p" "$FILE" | head -1 | sed "s/^${FIELD}:[[:space:]]*//" | sed 's/^"//' | sed 's/"$//' | sed "s/'$//" | sed "s/'\$//"
}

parse_categories() {
  local FILE="$1"
  # Formato YAML: "categories: [slug1,slug2]" o "categories:\n  - slug1\n  - slug2"
  local VAL=$(parse_frontmatter "$FILE" categories)
  if [[ "$VAL" == \[* ]]; then
    echo "$VAL" | tr -d '[]' | tr ',' '\n' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//' | tr '\n' ',' | sed 's/,$//'
  elif [[ -n "$VAL" ]]; then
    echo "$VAL"
  else
    # Formato lista
    awk '/^categories:/{f=1; next} f && /^- /{sub(/^- /,""); gsub(/[[:space:]]/,""); print; f=0; next} f && /^[^ -]/{f=0}' "$FILE" | tr '\n' ',' | sed 's/,$//'
  fi
}

echo "==> Sample articles"
EXISTING=$(wp --path=/var/www/html post list --post_type=post --post_status=publish --format=count --allow-root 2>/dev/null | tr -d ' ')
if [ "${EXISTING:-0}" -lt 5 ]; then
  # Borrar posts dummy previos (los creados sin frontmatter válido)
  wp --path=/var/www/html post delete $(wp --path=/var/www/html post list --post_type=post --post_status=publish --format=ids --allow-root 2>/dev/null) --force --allow-root 2>/dev/null || true

  for FILE in /seed/articles/*.md; do
    [ -f "$FILE" ] || continue
    SLUG=$(basename "$FILE" .md | sed 's/^[0-9]*-//')
    TITLE=$(parse_frontmatter "$FILE" title)
    CATS=$(parse_categories "$FILE")
    # Quitar ** que YAML usa a veces
    CATS_CLEAN=$(echo "$CATS" | tr ',' '\n' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//;s/^\*\*//;s/\*\*$//' | tr '\n' ',' | sed 's/,$//')

    echo "  + $SLUG | cat=[$CATS_CLEAN] | title='$TITLE'"
    if [ -n "$CATS_CLEAN" ]; then
      # Strip YAML frontmatter and pipe content via stdin
      awk 'BEGIN{fm=0} /^---$/{fm=!fm; next} !fm{print}' "$FILE" | \
        wp --path=/var/www/html post create - \
        --post_type=post \
        --post_status=publish \
        --post_title="$TITLE" \
        --post_name="$SLUG" \
        --post_category="$CATS_CLEAN" \
        --allow-root 2>&1 | tail -1
    else
      awk 'BEGIN{fm=0} /^---$/{fm=!fm; next} !fm{print}' "$FILE" | \
        wp --path=/var/www/html post create - \
        --post_type=post \
        --post_status=publish \
        --post_title="$TITLE" \
        --post_name="$SLUG" \
        --allow-root 2>&1 | tail -1
    fi

    # Descargar imagen destacada y asignarla
    IMG_URL=$(grep '^featured_image:' "$FILE" | head -1 | sed 's/^featured_image:[[:space:]]*//' | sed 's/^"//;s/"$//')
    if [ -n "$IMG_URL" ]; then
      POST_ID=$(wp --path=/var/www/html post list --post_type=post --name="$SLUG" --field=ID --allow-root 2>/dev/null | head -1)
      if [ -n "$POST_ID" ]; then
        echo "    downloading $IMG_URL"
        curl -sL --max-time 30 -o /tmp/feat.jpg "$IMG_URL" 2>/dev/null
        if [ -s /tmp/feat.jpg ]; then
          wp --path=/var/www/html media import /tmp/feat.jpg --post_id="$POST_ID" --featured_image --allow-root 2>&1 | tail -1
        fi
      fi
    fi
  done
fi

echo "==> ✓ Bootstrap done"
wp --path=/var/www/html post list --post_type=post --post_status=publish --format=count --allow-root
