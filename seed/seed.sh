#!/bin/bash
# Culturinfo — inicialización idempotente de WordPress.
set -e

DB_HOST="${WORDPRESS_DB_HOST:-127.0.0.1}"
DB_USER="${WORDPRESS_DB_USER:-culturinfo}"
DB_PASS="${WORDPRESS_DB_PASSWORD:-Cult1nf0_M4r1adb_2026!}"
DB_NAME="${WORDPRESS_DB_NAME:-culturinfo}"

export WORDPRESS_DB_HOST="$DB_HOST"
export WORDPRESS_DB_USER="$DB_USER"
export WORDPRESS_DB_PASSWORD="$DB_PASS"
export WORDPRESS_DB_NAME="$DB_NAME"

echo "==> WordPress DB: $DB_USER@$DB_HOST/$DB_NAME"
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
    --locale="${WP_LOCALE:-es_ES}" \
    --allow-root
fi

for i in {1..30}; do
  if wp db check --allow-root >/dev/null 2>&1; then
    break
  fi
  echo "  esperando la base de datos ($i)..."
  sleep 2
done

if ! wp core is-installed --allow-root >/dev/null 2>&1; then
  echo "==> Instalando WordPress"
  wp core install \
    --url="${WP_SITEURL:-https://culturinfo.statusloop.app}" \
    --title="${WP_TITLE:-Culturinfo}" \
    --admin_user="${WP_ADMIN_USER:-admin}" \
    --admin_password="${WP_ADMIN_PASSWORD}" \
    --admin_email="${WP_ADMIN_EMAIL:-admin@culturinfo.statusloop.app}" \
    --skip-email \
    --allow-root
fi

echo "==> Idioma de WordPress"
WP_LOCALE="${WP_LOCALE:-es_ES}"
wp language core install "$WP_LOCALE" --activate --allow-root
wp option update WPLANG "$WP_LOCALE" --allow-root

echo "==> Identidad y ajustes del sitio"
SITE_URL="${WP_SITEURL:-https://culturinfo.statusloop.app}"
wp option update home "$SITE_URL" --allow-root
wp option update siteurl "$SITE_URL" --allow-root
wp option update blogname "${WP_TITLE:-Culturinfo}" --allow-root
wp option update blogdescription "${WP_TAGLINE:-Periódico digital de Horizonte Cultural}" --allow-root
wp option update timezone_string "${WP_TIMEZONE:-America/Santo_Domingo}" --allow-root
wp option update date_format "j \d\e F \d\e Y" --allow-root
wp option update time_format "H:i" --allow-root
wp option update start_of_week "1" --allow-root
wp option update posts_per_page "10" --allow-root
wp option update default_comment_status "open" --allow-root
wp option update show_on_front "posts" --allow-root

echo "==> Enlaces permanentes"
wp rewrite structure "/%postname%/" --allow-root
wp rewrite flush --hard --allow-root

echo "==> Activando Culturinfo Editorial"
if ! wp theme is-installed culturinfo --allow-root >/dev/null 2>&1; then
  echo "ERROR: el tema culturinfo no fue copiado al volumen de WordPress"
  exit 1
fi
wp theme activate culturinfo --allow-root

if wp plugin is-installed culturinfo-ads --allow-root >/dev/null 2>&1; then
  wp plugin activate culturinfo-ads --allow-root >/dev/null 2>&1 || true
else
  echo "ERROR: el gestor de anuncios de Culturinfo no fue copiado a WordPress"
  exit 1
fi

if wp plugin is-installed culturinfo-authors --allow-root >/dev/null 2>&1; then
  wp plugin activate culturinfo-authors --allow-root >/dev/null 2>&1 || true
else
  echo "ERROR: el gestor de autores de Culturinfo no fue copiado a WordPress"
  exit 1
fi

if wp plugin is-installed culturinfo-stats --allow-root >/dev/null 2>&1; then
  wp plugin activate culturinfo-stats --allow-root >/dev/null
else
  echo "ERROR: las estadisticas de Culturinfo no fueron copiadas a WordPress"
  exit 1
fi

if wp plugin is-installed culturinfo-publishing --allow-root >/dev/null 2>&1; then
  wp plugin activate culturinfo-publishing --allow-root >/dev/null
else
  echo "ERROR: la programacion editorial de Culturinfo no fue copiada a WordPress"
  exit 1
fi

echo "==> Plugins esenciales"
for PLUGIN in akismet contact-form-7 classic-editor seo-by-rank-math independent-analytics; do
  if ! wp plugin is-installed "$PLUGIN" --allow-root >/dev/null 2>&1; then
    wp plugin install "$PLUGIN" --activate --allow-root 2>&1 | tail -2
  else
    wp plugin activate "$PLUGIN" --allow-root >/dev/null 2>&1 || true
  fi
done
wp option update classic-editor-replace "classic" --allow-root
wp option update classic-editor-allow-users "allow" --allow-root

echo "==> Configurando SEO y vistas previas sociales"
wp eval-file /seed/configure_rank_math.php --allow-root
wp rewrite flush --hard --allow-root

echo "==> Secciones editoriales"
declare -A SECTIONS=(
  [con-palabras]="Con Palabras"
  [arte-plural]="Arte Plural"
  [reflexiones-filo-linguisticas]="Reflexiones Filo-lingüísticas"
  [anfora-cultura]="Ánfora Cultura"
  [ventana-social]="Ventana Social"
  [aula-abierta]="Aula Abierta"
)
declare -A DESCRIPTIONS=(
  [con-palabras]="Crónicas, entrevistas y relatos donde la palabra abre nuevas maneras de mirar."
  [arte-plural]="Creación, lenguajes artísticos y las voces que transforman nuestra sensibilidad."
  [reflexiones-filo-linguisticas]="Ideas sobre lenguaje, pensamiento y los significados que construyen el mundo."
  [anfora-cultura]="Patrimonio, memoria e identidad: el legado cultural puesto en conversación."
  [ventana-social]="La sociedad en movimiento, sus desafíos y las iniciativas que generan encuentro."
  [aula-abierta]="Educación sin fronteras: herramientas, experiencias y saberes para compartir."
)
SECTION_ORDER=(con-palabras arte-plural reflexiones-filo-linguisticas anfora-cultura ventana-social aula-abierta)

for SLUG in "${SECTION_ORDER[@]}"; do
  if ! wp term get category "$SLUG" --by=slug --allow-root >/dev/null 2>&1; then
    wp term create category "${SECTIONS[$SLUG]}" --slug="$SLUG" --description="${DESCRIPTIONS[$SLUG]}" --allow-root >/dev/null
  else
    wp term update category "$SLUG" --by=slug --name="${SECTIONS[$SLUG]}" --description="${DESCRIPTIONS[$SLUG]}" --allow-root >/dev/null
  fi
done

echo "==> Menú principal"
if ! wp menu list --allow-root 2>/dev/null | grep -q "Menú Principal"; then
  wp menu create "Menú Principal" --allow-root >/dev/null
fi
EXISTING_ITEMS=$(wp menu item list "Menú Principal" --field=db_id --format=ids --allow-root 2>/dev/null || true)
for ITEM_ID in $EXISTING_ITEMS; do
  wp menu item delete "$ITEM_ID" --allow-root >/dev/null 2>&1 || true
done
for SLUG in "${SECTION_ORDER[@]}"; do
  wp menu item add-custom "Menú Principal" "${SECTIONS[$SLUG]}" "/category/$SLUG/" --allow-root >/dev/null
done

MENU_ID=$(wp menu list --fields=term_id,name --allow-root 2>/dev/null | awk -F'|' '/Menú Principal/ {gsub(/ /,"",$1); print $1; exit}')
if [ -n "$MENU_ID" ]; then
  CULTURINFO_MENU_ID="$MENU_ID" wp eval-file /seed/assign_menu.php --allow-root >/dev/null
fi

parse_frontmatter() {
  local FILE="$1"
  local FIELD="$2"
  sed -n "/^${FIELD}:/p" "$FILE" | head -1 | tr -d '\r' | sed "s/^${FIELD}:[[:space:]]*//" | sed 's/^"//;s/"$//'
}

parse_categories() {
  local FILE="$1"
  parse_frontmatter "$FILE" categories | tr -d '[] ' | tr ',' ' '
}

echo "==> Contenido inicial"
DEFAULT_AUTHOR_ID=$(wp user get "${WP_ADMIN_USER:-admin}" --field=ID --allow-root 2>/dev/null || true)
if [ -z "$DEFAULT_AUTHOR_ID" ]; then
  echo "ERROR: no se encontró el usuario autor ${WP_ADMIN_USER:-admin}"
  exit 1
fi

for FILE in /seed/articles/*.md; do
  [ -f "$FILE" ] || continue
  SLUG=$(parse_frontmatter "$FILE" slug)
  [ -n "$SLUG" ] || SLUG=$(basename "$FILE" .md | sed 's/^[0-9]*-//')
  TITLE=$(parse_frontmatter "$FILE" title)
  EXCERPT=$(parse_frontmatter "$FILE" excerpt)
  CATEGORY_SLUGS=$(parse_categories "$FILE")
  POST_ID=$(wp post list --post_type=post --name="$SLUG" --field=ID --allow-root 2>/dev/null | head -1)

  if [ -z "$POST_ID" ]; then
    BODY_FILE="/tmp/culturinfo-${SLUG}.html"
    awk 'BEGIN{fm=0} /^---$/{fm=!fm; next} !fm{print}' "$FILE" \
      | sed 's/^#\+[[:space:]]*//' \
      | sed 's/\*\*//g' \
      | sed 's/^>[[:space:]]*//' > "$BODY_FILE"
    POST_ID=$(wp post create "$BODY_FILE" \
      --post_type=post \
      --post_status=publish \
      --post_title="$TITLE" \
      --post_name="$SLUG" \
      --post_excerpt="$EXCERPT" \
      --post_author="$DEFAULT_AUTHOR_ID" \
      --porcelain \
      --allow-root)
    echo "  + $TITLE"
  fi

  POST_AUTHOR_ID=$(wp post get "$POST_ID" --field=post_author --allow-root 2>/dev/null || true)
  if [ "$POST_AUTHOR_ID" = "0" ]; then
    wp post update "$POST_ID" --post_author="$DEFAULT_AUTHOR_ID" --allow-root >/dev/null
  fi

  if [ -n "$CATEGORY_SLUGS" ]; then
    wp post term set "$POST_ID" category $CATEGORY_SLUGS --by=slug --allow-root >/dev/null
  fi

  CURRENT_THUMB=$(wp post meta get "$POST_ID" _thumbnail_id --allow-root 2>/dev/null || true)
  IMG_URL=$(parse_frontmatter "$FILE" featured_image)
  if [ -z "$CURRENT_THUMB" ] && [ -n "$IMG_URL" ]; then
    wp media import "$IMG_URL" --post_id="$POST_ID" --featured_image --allow-root >/dev/null 2>&1 || true
  fi
done

echo "==> Página de contacto"
CONTACT_ID=$(wp post list --post_type=page --name="contacto" --field=ID --allow-root 2>/dev/null | head -1)
if [ -z "$CONTACT_ID" ]; then
  wp post create \
    --post_type=page \
    --post_status=publish \
    --post_title="Contacto" \
    --post_name="contacto" \
    --post_content="¿Quieres proponer una colaboración, enviar una historia o conversar con el equipo editorial? Escríbenos a través de los canales oficiales de Horizonte Cultural." \
    --allow-root >/dev/null
fi

# Limpieza suave de contenido de ejemplo de WordPress; no elimina contenido editorial.
SAMPLE_ID=$(wp post list --post_type=page --name="sample-page" --field=ID --allow-root 2>/dev/null | head -1)
[ -n "$SAMPLE_ID" ] && wp post delete "$SAMPLE_ID" --force --allow-root >/dev/null 2>&1 || true
wp post delete 1 --force --allow-root >/dev/null 2>&1 || true

echo "==> ✓ Culturinfo listo"
wp theme status culturinfo --allow-root | head -4
wp post list --post_type=post --post_status=publish --format=count --allow-root
