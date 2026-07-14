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
# Limpiar newscrunch si quedó
wp --path=/var/www/html theme uninstall newscrunch --allow-root 2>/dev/null || true
# Instalar ColorMag (sin número de versión, wp-cli usa la última)
if ! wp --path=/var/www/html theme is-installed colormag --allow-root 2>/dev/null; then
  echo "  Instalando ColorMag..."
  wp --path=/var/www/html theme install colormag --allow-root 2>&1 | tail -3
fi
wp --path=/var/www/html theme activate colormag --allow-root 2>&1 | tail -1

# Companion plugin (ThemeGrill Toolkit) para widgets y customizer
if ! wp --path=/var/www/html plugin is-installed themegrill-tools --allow-root 2>/dev/null; then
  wp --path=/var/www/html plugin install themegrill-tools --allow-root 2>&1 | tail -2
fi
wp --path=/var/www/html plugin activate themegrill-tools --allow-root 2>&1 | tail -1

# ColorMag options via wp_options (ThemeGrill settings stored as WP options)
wp --path=/var/www/html option update colormag_site_layout "wide_layout" --allow-root
wp --path=/var/www/html option update colormag_primary_color "e74c3c" --allow-root
wp --path=/var/www/html option update colormag_secondary_color "2c3e50" --allow-root
wp --path=/var/www/html option update colormag_header_logo_placement "header_text_only" --allow-root
wp --path=/var/www/html option update colormag_enable_featured_image_slider "1" --allow-root
wp --path=/var/www/html option update colormag_enable_breaking_news "1" --allow-root
wp --path=/var/www/html option update colormag_breaking_news_title "Última Hora" --allow-root
wp --path=/var/www/html option update colormag_blog_post_excerpt_length "40" --allow-root

# Forzar color primario via custom CSS (algunos theme_mods se escapan)
cat > /tmp/extra.css <<'CSS'
:root {
  --tm-color-primary: #e74c3c !important;
  --tm-color-secondary: #2c3e50 !important;
}
a, a:visited { color: #e74c3c; }
.entry-title a:hover, .cm-entry-title a:hover { color: #e74c3c !important; }
.colormag-button, button, input[type="button"], input[type="reset"], input[type="submit"] {
  background-color: #e74c3c !important;
}
CSS
wp --path=/var/www/html option update custom_css_post_id 0 --allow-root
wp --path=/var/www/html post create /tmp/extra.css --post_type=custom_css --post_status=publish --post_title="Culturinfo Custom CSS" --allow-root 2>/dev/null || true
CSS_ID=$(wp --path=/var/www/html post list --post_type=custom_css --field=ID --allow-root 2>/dev/null | head -1)
[ -n "$CSS_ID" ] && wp --path=/var/www/html option update custom_css_post_id "$CSS_ID" --allow-root

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
# Crear menu si no existe
if ! wp --path=/var/www/html menu list --allow-root 2>/dev/null | grep -q "Menú Principal"; then
  wp --path=/var/www/html menu create "Menú Principal" --allow-root 2>&1 | tail -1
fi
# Poblar menu con categorias: limpia items actuales y re-crea
echo "  Limpiando items del menu..."
EXISTING_ITEMS=$(wp --path=/var/www/html menu item list "Menú Principal" --field=db_id --format=ids --allow-root 2>/dev/null)
for ITEM_ID in $EXISTING_ITEMS; do
  wp --path=/var/www/html menu item delete "$ITEM_ID" --allow-root 2>/dev/null
done
echo "  Agregando categorias al menu..."
for SLUG in cultura politica economia tecnologia deportes opinion mundo; do
  CAT_ID=$(wp --path=/var/www/html term list category --slug="$SLUG" --field=term_id --allow-root 2>/dev/null | head -1)
  if [ -n "$CAT_ID" ]; then
    wp --path=/var/www/html menu item add-post-term "Menú Principal" category "$CAT_ID" --allow-root 2>&1 | tail -1
  fi
done
# Mostrar resultado
echo "  Items actuales en Menú Principal:"
wp --path=/var/www/html menu item list "Menú Principal" --fields=db_id,type,title --allow-root 2>&1 | head -15

parse_frontmatter() {
  local FILE="$1"
  local FIELD="$2"
  sed -n "/^${FIELD}:/p" "$FILE" | head -1 | sed "s/^${FIELD}:[[:space:]]*//" | sed 's/^"//' | sed 's/"$//' | sed "s/'$//" | sed "s/'\$//"
}

parse_categories() {
  local FILE="$1"
  local VAL=$(parse_frontmatter "$FILE" categories)
  if [[ "$VAL" == \[* ]]; then
    echo "$VAL" | tr -d '[]' | tr ',' '\n' | sed 's/^[[:space:]]*//;s/[[:space:]]*$//' | tr '\n' ',' | sed 's/,$//'
  elif [[ -n "$VAL" ]]; then
    echo "$VAL"
  else
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
      # Strip YAML frontmatter, strip markdown headers, pipe content via stdin
      awk 'BEGIN{fm=0} /^---$/{fm=!fm; next} !fm{print}' "$FILE" | \
        sed 's/^#\+[[:space:]]*//' | \
        sed 's/\*\*//g' | \
        sed 's/^>//' | \
        wp --path=/var/www/html post create - \
        --post_type=post \
        --post_status=publish \
        --post_title="$TITLE" \
        --post_name="$SLUG" \
        --post_excerpt="$TITLE." \
        --post_category="$CATS_CLEAN" \
        --allow-root 2>&1 | tail -1
    else
      awk 'BEGIN{fm=0} /^---$/{fm=!fm; next} !fm{print}' "$FILE" | \
        sed 's/^#\+[[:space:]]*//' | \
        sed 's/\*\*//g' | \
        sed 's/^>//' | \
        wp --path=/var/www/html post create - \
        --post_type=post \
        --post_status=publish \
        --post_title="$TITLE" \
        --post_name="$SLUG" \
        --post_excerpt="$TITLE." \
        --allow-root 2>&1 | tail -1
    fi

    # Descargar imagen destacada y asignarla
    IMG_URL=$(grep '^featured_image:' "$FILE" | head -1 | sed 's/^featured_image:[[:space:]]*//' | sed 's/^"//;s/"$//')
    if [ -n "$IMG_URL" ]; then
      POST_ID=$(wp --path=/var/www/html post list --post_type=post --name="$SLUG" --field=ID --allow-root 2>/dev/null | head -1)
      if [ -n "$POST_ID" ]; then
        # Si ya tiene imagen destacada, saltar
        CURRENT_THUMB=$(wp --path=/var/www/html post get "$POST_ID" --field=meta_value --meta_key=_thumbnail_id --allow-root 2>/dev/null | head -1)
        if [ -z "$CURRENT_THUMB" ]; then
          echo "    downloading $IMG_URL"
          curl -sL --max-time 30 -o /tmp/feat.jpg "$IMG_URL" 2>/dev/null
          if [ -s /tmp/feat.jpg ] && [ "$(stat -c%s /tmp/feat.jpg 2>/dev/null)" -gt 1000 ]; then
            wp --path=/var/www/html media import /tmp/feat.jpg --post_id="$POST_ID" --featured_image --allow-root 2>&1 | tail -1
          else
            echo "    image too small, skipping"
          fi
        fi
      fi
    fi
    # Re-import post (no, ya existe - skip)
    : # placeholder
  done
fi

# Asignar menu a la posicion 'primary' del tema ColorMag (este es el fix que faltaba)
MENU_ID=$(wp --path=/var/www/html menu list --fields=term_id,name --allow-root 2>/dev/null | awk -F'|' '/Menú Principal/ {gsub(/ /,"",$1); print $1; exit}')
if [ -n "$MENU_ID" ]; then
  # Borra la "Sample Page" del WP
  SAMPLE_ID=$(wp --path=/var/www/html post list --post_type=page --name="sample-page" --field=ID --allow-root 2>/dev/null | head -1)
  if [ -n "$SAMPLE_ID" ]; then
    echo "==> Eliminando Sample Page ID=$SAMPLE_ID"
    wp --path=/var/www/html post delete "$SAMPLE_ID" --force --allow-root 2>&1 | tail -1
  fi
  # Tambien borra la página Hello World (post 1)
  wp --path=/var/www/html post delete 1 --force --allow-root 2>/dev/null || true

  # Borra todos los menu items del menu "Sample" (default) si existe
  SAMPLE_MENU=$(wp --path=/var/www/html menu list --fields=term_id,name --allow-root 2>/dev/null | awk -F'|' '/Sample/ {gsub(/ /,"",$1); print $1; exit}')
  [ -n "$SAMPLE_MENU" ] && [ "$SAMPLE_MENU" != "$MENU_ID" ] && wp --path=/var/www/html menu delete "$SAMPLE_MENU" --allow-root 2>/dev/null

  # Asigna menu al tema via nav_menu_locations (JSON en option)
  CURRENT_LOCATIONS=$(wp --path=/var/www/html option get nav_menu_locations --format=json --allow-root 2>/dev/null || echo "{}")
  NEW_LOCATIONS=$(echo "$CURRENT_LOCATIONS" | python3 -c "
import sys, json
try:
    d = json.load(sys.stdin)
except: d = {}
d['primary'] = $MENU_ID
print(json.dumps(d))
" 2>/dev/null)
  echo "==> Asignando menu $MENU_ID a nav_menu_locations: $NEW_LOCATIONS"
  [ -n "$NEW_LOCATIONS" ] && echo "$NEW_LOCATIONS" | wp --path=/var/www/html option update nav_menu_locations --format=json --allow-root 2>&1 | tail -1

  # Tambien asigna directamente al theme_mods
  THEME_MODS=$(wp --path=/var/www/html option get theme_mods_colormag --format=json --allow-root 2>/dev/null || echo "{}")
  NEW_THEME_MODS=$(echo "$THEME_MODS" | python3 -c "
import sys, json
try: d = json.load(sys.stdin)
except: d = {}
d['nav_menu_locations'] = {'primary': $MENU_ID}
print(json.dumps(d))
" 2>/dev/null)
  [ -n "$NEW_THEME_MODS" ] && echo "$NEW_THEME_MODS" | wp --path=/var/www/html option update theme_mods_colormag --format=json --allow-root 2>&1 | tail -1
fi

echo "==> ✓ Bootstrap done"
wp --path=/var/www/html post list --post_type=post --post_status=publish --format=count --allow-root
