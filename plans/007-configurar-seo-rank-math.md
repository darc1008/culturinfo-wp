# Plan 007: Configurar metadatos SEO, sociales y datos estructurados

> **Reconciliación 2026-08-18**: REJECTED porque el hallazgo fue resuelto por
> cambios independientes. `seed/configure_rank_math.php` se ejecuta en cada seed
> y la documentación ya cubre canonical, Open Graph, Twitter Card y `og:image`.

> **Instrucciones para el ejecutor**: Sigue el plan en orden y ejecuta cada
> comprobación. Si hay deriva, salida duplicada o una condición de parada,
> detente y reporta. Actualiza la fila 007 en `plans/README.md` al terminar,
> salvo que el revisor mantenga el índice.
>
> **Comprobación de deriva (ejecutar primero)**:
> `git diff --stat aa2da49..HEAD -- Dockerfile seed/seed.sh seed README.md wp-content/themes/culturinfo`
> y `git status --short -- Dockerfile seed/seed.sh seed README.md wp-content/themes/culturinfo`.
> El árbol estaba sucio; no descartes cambios y compara el estado actual.

## Estado

- **Prioridad**: P1
- **Esfuerzo**: M
- **Riesgo**: MED
- **Depende de**: `plans/002-corregir-https-proxy.md`, `plans/003-migrar-wordpress-7-0-2.md`, `plans/006-importar-medios-confiablemente.md`
- **Categoría**: tech-debt
- **Planificado en**: commit `aa2da49`, 2026-08-03 (árbol de trabajo sucio)

## Por qué importa

Rank Math está activo pero no configurado. La portada y noticias inspeccionadas no
emitían meta description, Open Graph, Twitter Cards ni JSON-LD; la portada tampoco
tenía canonical. Esto reduce la calidad de indexación y de las vistas previas al
compartir. La configuración debe ser idempotente, mantener la portada como últimas
noticias y producir una sola fuente de metadatos, sin duplicarla en el tema.

## Estado actual

- `seed/seed.sh:51-64` configura identidad, `show_on_front=posts` y permalinks.
- `seed/seed.sh:80-87` instala/activa `seo-by-rank-math`, pero no ejecuta su wizard
  ni escribe opciones SEO.
- El valor esperado de marca es `Culturinfo`; subtítulo exacto:
  `Periódico digital de Horizonte Cultural`.
- La homepage debe continuar mostrando las últimas noticias.
- Diagnóstico observado: 0 meta descriptions, 0 OG/Twitter y 0 JSON-LD; canonical
  solo en artículos por core.
- WordPress core publica `/wp-sitemap.xml`; Rank Math puede servir su propio índice
  cuando el módulo sitemap quede configurado.

## Comandos necesarios

| Propósito | Comando | Resultado esperado |
|---|---|---|
| Sintaxis shell | `wsl.exe -e sh -lc "bash -n /mnt/e/dev/culturinfo-wp/seed/seed.sh"` | exit 0 |
| Sintaxis PHP | `docker run --rm -v "${PWD}:/work" -w /work php:8.3-cli php -l seed/configure_rank_math.php` | sin errores |
| Build | `docker build -t culturinfo-plan-007 .` | exit 0 |
| Portada | `curl.exe -sS http://localhost:8097/` | contiene description, canonical, OG, Twitter y JSON-LD una sola vez |
| Sitemap | `curl.exe -sS -o NUL -w "%{http_code}" http://localhost:8097/sitemap_index.xml` | `200` si Rank Math habilita su sitemap |

## Alcance

**Dentro del alcance**:

- `seed/configure_rank_math.php` (crear)
- `seed/seed.sh`
- `Dockerfile` para copiar el helper
- `README.md`
- `wp-content/themes/culturinfo/functions.php` solo si duplica metadatos que Rank
  Math ya genera; eliminar duplicación, no crear un segundo sistema SEO

**Fuera del alcance**:

- No cambiar contenido periodístico ni inventar keywords.
- No conectar Search Console/Analytics sin cuentas y autorización.
- No comprar ni activar Rank Math Pro.
- No cambiar `show_on_front=posts`.
- No añadir schema manual paralelo al de Rank Math.

## Flujo Git

- Rama: `codex/007-rank-math-seo`
- Commit sugerido: `feat: configure default SEO metadata`
- No hagas push, conexión OAuth ni despliegue sin autorización.

## Pasos

### Paso 1: Inspeccionar la versión y forma de opciones

En el contenedor WordPress 7.0.2 del plan 003, registra la versión activa de
Rank Math y exporta, sin credenciales, las opciones:
`rank-math-options-titles`, `rank-math-options-general`,
`rank-math-options-sitemap` y `rank_math_modules`. Comprueba en el código instalado
del plugin que las claves objetivo existen para esa versión. No adivines claves;
si difieren de las siguientes, detente y ajusta este plan con evidencia antes de
implementar.

Claves objetivo:

- Identidad/Knowledge Graph: organización `Culturinfo`, tipo organización y URL
  pública; logo desde el logo del sitio.
- Homepage: título con marca y description exacta del subtítulo.
- Posts: patrón de título que incluya `%title%` y `%sitename%`, descripción desde
  `%excerpt%`, rich snippet `article`, tipo de artículo `NewsArticle`.
- Categorías: título con nombre de categoría + marca y descripción de categoría.
- Social: Open Graph y Twitter Cards habilitados; imagen destacada como default de
  cada noticia.
- Sitemap: módulo habilitado para posts, páginas y categorías; anuncios excluidos.

**Verificar**:
`docker exec culturinfo-plan-007 wp plugin get seo-by-rank-math --field=status --allow-root`
→ `active`.

### Paso 2: Crear configuración idempotente

Crea `seed/configure_rank_math.php`, cargado con `wp eval-file`, que:

1. Falla si Rank Math no está activo o si falta una opción/clave verificada.
2. Lee arrays existentes y actualiza solo las claves listadas, preservando el
   resto de preferencias.
3. Habilita el módulo sitemap en `rank_math_modules` sin borrar otros módulos.
4. Marca la configuración como completada con la opción que use la versión
   instalada, solo después de escribir y releer correctamente todo.
5. Escribe un marcador propio versionado `culturinfo_rank_math_seed_version` para
   migraciones futuras.
6. No almacena tokens, cuentas, contraseñas ni IDs externos.

Cópialo en `Dockerfile` a `/seed/configure_rank_math.php` e invócalo desde
`seed/seed.sh` inmediatamente después de activar plugins y antes de flush de
rewrites/sitemap. La segunda ejecución debe producir el mismo estado.

**Verificar**:
`docker exec culturinfo-plan-007 wp eval-file /seed/configure_rank_math.php --allow-root`
→ exit 0 dos veces, sin duplicación.

### Paso 3: Garantizar contenido base y exclusiones

Confirma mediante WP-CLI:

- `blogname=Culturinfo`.
- `blogdescription=Periódico digital de Horizonte Cultural` con codificación UTF-8.
- `show_on_front=posts`.
- Cada categoría tiene descripción no vacía.
- Cada post tiene excerpt e imagen destacada.
- `culturinfo_ad` está excluido de sitemap, búsqueda y schema público.

No indexes páginas de administración, búsquedas internas ni archivos vacíos. No
apliques `noindex` a las seis secciones editoriales.

**Verificar**:
`docker exec culturinfo-plan-007 wp option get show_on_front --allow-root`
→ `posts`.

### Paso 4: Verificar salida HTML sin duplicados

Arranca en puerto 8097. Para portada, una categoría y una noticia, parsea el HTML
y exige exactamente:

- Una `<meta name="description">` no vacía.
- Un `<link rel="canonical">` absoluto.
- Un `og:title`, `og:description`, `og:url`, `og:type` y `og:image`.
- Un `twitter:card`, `twitter:title`, `twitter:description` y `twitter:image`.
- Un bloque JSON-LD válido.

La portada debe contener `WebSite` y `Organization`; la noticia, `NewsArticle`
con headline, datePublished, dateModified, author, publisher, mainEntityOfPage e
image. Las URLs deben usar el esquema/dominio configurado. Cuenta cada etiqueta;
si aparece más de una, elimina la fuente duplicada (tema o plugin), no ignores.

**Verificar**:
`$html = curl.exe -sS http://localhost:8097/; ([regex]::Matches($html, '<meta name="description"')).Count`
→ `1`.

**Verificar**: extraer cada bloque `application/ld+json` y decodificarlo con
`ConvertFrom-Json`
→ exit 0, sin JSON inválido.

### Paso 5: Verificar sitemap y robots

Regenera rewrites. Si Rank Math habilita `/sitemap_index.xml`, debe responder 200
y listar posts, páginas y categorías; `culturinfo_ad` no debe aparecer. El
`robots.txt` debe apuntar al sitemap efectivo y no bloquear assets, posts o
categorías. Evita publicar dos índices contradictorios; documenta cuál es el
canónico.

**Verificar**:
`curl.exe -sS -L --max-redirs 3 -o NUL -w "%{http_code}" http://localhost:8097/sitemap_index.xml`
→ `200`.

**Verificar**:
`curl.exe -sS http://localhost:8097/robots.txt | Select-String 'Sitemap:'`
→ una línea con el índice canónico.

### Paso 6: Verificar producción después del despliegue

Tras promover la misma imagen probada, repite las comprobaciones sobre
`https://culturinfo.statusloop.app` para portada, una sección y una noticia.
Valida que ningún canonical, OG, JSON-LD o sitemap contenga localhost o HTTP.
Prueba las URLs con Rich Results Test/Search Console solo si el operador dispone
de acceso; no conectes cuentas por tu cuenta.

**Verificar**:
`$html = curl.exe -sS https://culturinfo.statusloop.app/; if ($html -match 'localhost|http://culturinfo\.statusloop\.app') { exit 1 }`
→ exit 0.

## Plan de pruebas

- Primera configuración y segunda ejecución idempotente.
- Portada de últimas noticias conserva estructura y emite WebSite/Organization.
- Seis categorías: title/description/canonical únicos e indexables.
- Noticia: NewsArticle completo con imagen destacada.
- Anuncio: ausente del sitemap y sin URL pública indexable.
- JSON-LD válido mediante parser, no solo búsqueda de texto.
- Sitemap/robots coherentes, sin redirección circular.
- Producción sin localhost, HTTP mixto ni tags duplicados.

## Criterios de finalización

- [ ] Rank Math queda configurado idempotentemente y con versión de seed.
- [ ] Home continúa mostrando posts recientes.
- [ ] Home, categoría y noticia tienen description/canonical/OG/Twitter únicos.
- [ ] JSON-LD es válido y usa `NewsArticle` para noticias.
- [ ] Todas las noticias seed tienen `og:image` válida.
- [ ] Sitemap canónico responde 200 y excluye anuncios.
- [ ] Robots referencia el sitemap correcto.
- [ ] Producción no emite localhost ni HTTP del dominio.
- [ ] Solo se tocaron archivos dentro del alcance y el índice.
- [ ] La fila 007 está actualizada.

## Condiciones de parada

- La versión instalada de Rank Math usa opciones distintas a las verificadas.
- El tema ya emite metadatos/schema y no puede eliminarse la duplicación sin un
  cambio fuera de alcance.
- Falta logo o imagen social con derechos aprobados.
- Sitemap o canonical siguen en HTTP después de completar el plan 002.
- El cliente quiere una página estática como home; contradice el requisito actual.
- Una verificación falla dos veces.

## Notas de mantenimiento

- Incrementar `culturinfo_rank_math_seed_version` al cambiar defaults.
- Probar nuevamente las claves internas al actualizar Rank Math; son el punto más
  sensible de este plan.
- Search Console, Analytics y redes sociales requieren credenciales/decisión del
  cliente y quedan fuera.
