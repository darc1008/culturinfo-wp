# Culturinfo — Periódico Digital

WordPress auto-hospedado en [culturinfo.statusloop.app](https://culturinfo.statusloop.app) vía Coolify.

## Stack

- WordPress 6.7 (PHP 8.3 Apache)
- MariaDB 11.4
- Theme: **Newscrunch** (gratuito, optimizado para SEO y noticias)
- Plugins: Akismet, WPForms Lite, Classic Editor, WP Super Cache, Yoast SEO

## Deploy (Coolify)

1. Crear app en Coolify: `New Resource → Application → GitHub App → darc1008/culturinfo-wp`
2. Build pack: **dockerfile** (usa el `Dockerfile` de este repo)
3. Domain: `culturinfo.statusloop.app`
4. Volúmenes persistentes:
   - `db_data` → /var/lib/mysql
   - `wp_data` → /var/www/html
5. Copiar `.env.example` a `.env` y completar passwords
6. Deploy → esperar a que el healthcheck del contenedor WP pase
7. Inicialización: `docker compose --profile seed run wpcli` (una sola vez)

## Estructura

```
.
├── docker-compose.yml       # WP + MariaDB + wpcli (seed)
├── Dockerfile               # Build del front (no usado en compose, solo si quieres migrar)
├── .env.example
├── seed/
│   ├── bootstrap.sh         # WP-CLI: instala theme/plugins/crea admin/categorías/artículos
│   └── articles/            # 5 artículos Markdown con frontmatter
└── wp-content/              # Themes y plugins preinstalados (referencia, NO versionado)
```

## Acceso admin

`https://culturinfo.statusloop.app/wp-admin/`
