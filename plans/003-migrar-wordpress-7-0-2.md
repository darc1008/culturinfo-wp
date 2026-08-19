# Plan 003: Migrar la plataforma a WordPress 7.0.2

> **Reconciliación 2026-08-18**: REJECTED por deriva sustancial. El plan 008 lo
> supersede e incorpora los plugins propios añadidos y la migración real del core
> dentro del volumen persistente, que este documento no resolvía.

> **Instrucciones para el ejecutor**: Ejecuta cada paso y sus verificaciones.
> No avances ante una condición de parada. Al terminar, actualiza la fila 003
> de `plans/README.md`, salvo que el revisor mantenga el índice.
>
> **Comprobación de deriva (ejecutar primero)**:
> `git diff --stat aa2da49..HEAD -- Dockerfile README.md entrypoint.sh seed/seed.sh wp-content/themes/culturinfo wp-content/plugins/culturinfo-ads`
> y `git status --short`.
> El plan se redactó con cambios sin commit; no los descartes. Si los extractos
> no coinciden, detente.

## Estado

- **Prioridad**: P1
- **Esfuerzo**: L
- **Riesgo**: HIGH
- **Depende de**: `plans/001-retirar-y-rotar-secretos.md`, `plans/002-corregir-https-proxy.md`
- **Categoría**: migration
- **Planificado en**: commit `aa2da49`, 2026-08-03 (árbol de trabajo sucio)

## Por qué importa

La imagen está fijada a WordPress 6.7, mientras que WordPress 7.0.2 ya está
publicado como etiqueta oficial para PHP 8.3. Migrar elimina deuda de seguridad
y evita lanzar un sitio nuevo sobre una rama antigua. Por ser un salto mayor,
la promoción requiere respaldo, matriz de compatibilidad y una prueba de
restauración, no solo cambiar una línea del Dockerfile.

## Estado actual

- `Dockerfile:1` usa `FROM wordpress:6.7-php8.3-apache`.
- `README.md:32` declara WordPress 6.7 con PHP 8.3.
- `seed/seed.sh:66-89` activa el tema propio, el gestor de anuncios y cuatro
  plugins de terceros.
- Plugins observados en la instancia: Akismet 5.3.6 (actualización disponible),
  Classic Editor 1.7.0, Contact Form 7 6.1.6, Culturinfo Ads 1.0.0 y Rank Math
  1.0.275.
- Se verificó el 2026-08-03 que existe la etiqueta oficial
  `wordpress:7.0.2-php8.3-apache` para amd64 y arm64.
- No existe una suite automatizada del repositorio; la aceptación se realizará
  con un contenedor efímero y smoke tests reproducibles.

## Comandos necesarios

| Propósito | Comando | Resultado esperado |
|---|---|---|
| Verificar etiqueta | `docker manifest inspect wordpress:7.0.2-php8.3-apache` | exit 0 |
| Construir | `docker build --pull -t culturinfo-wp:7.0.2-plan .` | exit 0 |
| Versión core | `docker exec culturinfo-plan-003 wp core version --allow-root` | `7.0.2` |
| Integridad core | `docker exec culturinfo-plan-003 wp core verify-checksums --allow-root` | éxito |
| Estado plugins | `docker exec culturinfo-plan-003 wp plugin status --allow-root` | plugins requeridos activos |

## Alcance

**Dentro del alcance**:

- `Dockerfile`
- `README.md`
- `entrypoint.sh` y `seed/seed.sh` solo si WordPress 7 exige compatibilidad
- `wp-content/themes/culturinfo/**` solo para defectos reproducibles de compatibilidad
- `wp-content/plugins/culturinfo-ads/**` solo para defectos reproducibles de compatibilidad

**Fuera del alcance**:

- No rediseñar la portada ni añadir funciones editoriales.
- No cambiar PHP 8.3, Apache o MariaDB salvo incompatibilidad demostrada.
- No actualizar a una versión distinta de WordPress 7.0.2.
- No actualizar plugins “a ciegas” en producción sin probarlos en clon.
- No desplegar sin respaldo restaurable.

## Flujo Git

- Rama: `codex/003-wordpress-7-0-2`
- Primer commit: `build: upgrade WordPress to 7.0.2`.
- Correcciones propias separadas: `fix: support WordPress 7 in <componente>`.
- No hagas push ni despliegue sin autorización del operador.

## Pasos

### Paso 1: Capturar respaldo y línea base

Antes de tocar producción, exporta la base con `wp db export` desde el contenedor
actual y crea un snapshot/export del volumen `/var/www/html`. Registra fuera del
repositorio la ubicación, fecha, tamaño y checksum SHA-256. Restaura ambos en un
entorno efímero aislado y confirma que la portada abre; un respaldo no probado no
cumple el gate.

Captura además en texto de revisión: `wp core version`, `wp plugin list`,
`wp theme list`, `wp option get home` y `wp option get siteurl`.

**Verificar**: calcular SHA-256 del export y compararlo después de copiarlo
→ hashes idénticos; el clon restaurado responde 200.

### Paso 2: Fijar WordPress 7.0.2

Cambia únicamente la primera línea de `Dockerfile` a
`FROM wordpress:7.0.2-php8.3-apache`. Actualiza `README.md` para indicar
WordPress 7.0.2 + PHP 8.3 y documentar que el pin es intencional.

**Verificar**:
`git grep -n 'wordpress:7.0.2-php8.3-apache' -- Dockerfile`
→ exactamente una coincidencia en `Dockerfile:1`.

### Paso 3: Construir y ejecutar desde volumen vacío

Construye con `--pull`. Arranca `culturinfo-plan-003` en el puerto 8093 con
secretos efímeros y `WP_SITEURL=http://localhost:8093`. Espera a que el seed
termine y no aceptes un contenedor “saludable” si los logs contienen `Fatal
error`, `Uncaught`, `Deprecated:` repetido, `ERROR:` o fallo de plugin.

Ejecuta actualización de base explícita e idempotente:
`wp core update-db --allow-root`; debe decir que está actualizada o completarla.

**Verificar**:
`docker exec culturinfo-plan-003 wp core version --allow-root`
→ `7.0.2`.

**Verificar**:
`docker exec culturinfo-plan-003 wp core verify-checksums --allow-root`
→ exit 0.

### Paso 4: Cerrar la matriz de plugins

Dentro del contenedor de prueba, ejecuta `wp plugin list --update=available`.
Actualiza los plugins de terceros a versiones que declaren soporte para WordPress
7.0.2 y PHP 8.3, uno por uno, comprobando portada, noticia, formulario y admin
después de cada actualización. No cambies el plugin propio salvo que aparezca un
error reproducible.

Registra en `README.md` o en una tabla de compatibilidad del mismo archivo la
versión verificada de cada plugin requerido. Si un plugin no soporta WordPress 7,
detén la promoción; no lo desactives silenciosamente.

**Verificar**:
`docker exec culturinfo-plan-003 wp plugin list --status=active --allow-root --format=table`
→ `akismet`, `classic-editor`, `contact-form-7`, `culturinfo-ads` y
`seo-by-rank-math` activos.

### Paso 5: Ejecutar smoke tests funcionales

Comprueba con respuestas 200:

- `/`
- las seis categorías editoriales
- al menos una noticia
- `/contacto/`
- `/wp-login.php`
- `/wp-sitemap.xml`

Entra al admin con un usuario de prueba y crea/edita/publica una noticia con
imagen, categoría y anuncio; verifica después que el frontend no tiene errores
PHP. Borra solo el contenido de prueba creado y conserva los datos seed.

**Verificar**:
`docker logs culturinfo-plan-003 2>&1 | Select-String -Pattern 'Fatal error|Uncaught|Parse error'`
→ sin coincidencias.

### Paso 6: Probar actualización sobre una copia de producción

Aplica la imagen 7.0.2 al clon restaurado del Paso 1, no a producción. Ejecuta
`wp core update-db`, verifica checksums y repite el smoke test. Compara conteos de
posts, páginas, categorías, anuncios y adjuntos antes/después; deben permanecer
iguales salvo migraciones documentadas.

Solo después promueve la misma imagen inmutable a producción. Conserva rollback
a la imagen anterior y al snapshot durante la ventana acordada.

**Verificar**:
`docker exec <contenedor-clon> wp core version --allow-root`
→ `7.0.2`; todos los conteos coinciden.

## Plan de pruebas

- Instalación limpia desde volumen vacío.
- Actualización sobre clon de los volúmenes actuales.
- Checksums de core.
- Activación y actualización individual de cada plugin.
- Tema propio: portada, seis secciones, noticia, navegación responsive.
- Plugin propio: CRUD de anuncio y render en home/sección/noticia.
- Contact Form 7: formulario renderiza y el REST endpoint no da fatal.
- Rank Math: sitemap responde y editar noticia no falla.
- Rollback: restaurar snapshot en entorno aislado y obtener portada 200.

## Criterios de finalización

- [ ] Dockerfile fija `wordpress:7.0.2-php8.3-apache`.
- [ ] `wp core version` devuelve exactamente `7.0.2`.
- [ ] Checksums de core son válidos.
- [ ] Todos los plugins requeridos están activos y compatibles.
- [ ] Instalación limpia y clon actualizado pasan los smoke tests.
- [ ] No hay fatales PHP en logs.
- [ ] Existe respaldo probado y procedimiento de rollback.
- [ ] README refleja la versión y matriz probadas.
- [ ] Solo se tocaron archivos dentro del alcance y el índice.
- [ ] La fila 003 está actualizada.

## Condiciones de parada

- No existe respaldo restaurable de base y volúmenes.
- Un plugin requerido no declara o no demuestra compatibilidad con WordPress 7.
- Cambian conteos de contenido durante la actualización sin explicación.
- Aparecen errores fatales, pérdida de datos o corrupción de medios.
- La corrección exige cambiar PHP/MariaDB o rediseñar componentes fuera de alcance.
- Una verificación falla dos veces.

## Notas de mantenimiento

- Mantener un pin exacto permite reproducibilidad; programar revisiones de
  parches de seguridad posteriores a 7.0.2.
- Revisar especialmente hooks obsoletos del tema/plugin y cambios en REST.
- No eliminar el respaldo hasta cerrar la ventana de observación en producción.
