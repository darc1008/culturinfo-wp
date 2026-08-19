# Plan 008: Migrar WordPress persistente a 7.0.2

> **Instrucciones para el ejecutor**: ejecuta el plan completo en una rama propia,
> prueba primero una restauración y detente ante cualquier gate fallido. No basta
> con cambiar el `FROM`: `/var/www/html` es persistente y hoy conserva el core
> antiguo en cada redeploy.
>
> **Comprobación de deriva (primero)**:
> `git diff --stat 2b95226..HEAD -- Dockerfile entrypoint.sh seed/seed.sh README.md wp-content`
> y `git status --short`. No incluyas el cambio preexistente de `.env.example`.

## Estado

- **Prioridad**: P1
- **Esfuerzo**: L
- **Riesgo**: HIGH
- **Depende de**: none
- **Categoría**: migration/security
- **Planificado en**: `2b95226`, 2026-08-18

## Por qué importa

La imagen fija `wordpress:6.7-php8.3-apache` (`Dockerfile:1`) aunque la versión
objetivo ya es 7.0.2. Además, `entrypoint.sh:37-42` copia el core de la imagen
solo si no existe `wp-load.php`; por tanto, cambiar la imagen no actualiza una
instalación que usa el volumen `/var/www/html`. La migración debe actualizar ese
core persistentemente, preservar `wp-content` y poder revertirse con base y
archivos sincronizados.

## Estado actual verificado

- `Dockerfile:1`: WordPress 6.7 + PHP 8.3.
- `README.md:160`: documenta todavía WordPress 6.7.
- `entrypoint.sh:38`: un volumen existente evita cualquier copia del nuevo core.
- El seed administra seis plugins propios: ads, authors, stats, publishing,
  contact y audio (`seed/seed.sh:102-142`).
- El seed instala Akismet, Classic Editor, Rank Math e Independent Analytics
  (`seed/seed.sh:144-151`).
- Contact Form 7 se observó activo en producción pero no lo administra el repo.
- Base y WordPress viven en dos volúmenes: `/var/lib/mysql` y `/var/www/html`.

## Alcance

**Dentro**: `Dockerfile`, `entrypoint.sh`, `seed/seed.sh`, `README.md` y cambios
mínimos de compatibilidad demostrados por pruebas en tema/plugins propios.

**Fuera**: rediseño, nuevas funciones, cambio de PHP/MariaDB, eliminación ciega
de plugins o contenido, y despliegue sin respaldo restaurado.

## Flujo Git

- Rama: `codex/008-wordpress-7-0-2-persistent`.
- Commits: primero plataforma, luego cada corrección de compatibilidad separada.
- No hacer push ni desplegar sin autorización expresa.

## Implementación

### Paso 1: inventario y respaldo probado

Antes de editar, capturar desde producción:

```text
wp core version
wp plugin list --format=json
wp theme list --format=json
wp option get home
wp option get siteurl
wp db size
wp post list --post_type=post,page,attachment,culturinfo_ad,culturinfo_author --format=count
```

Exportar la base y crear snapshots consistentes de ambos volúmenes. Guardar fuera
del repositorio fecha, tamaño y SHA-256. Restaurar los dos en un ambiente aislado
y exigir portada, login, una noticia y una imagen con respuesta 200. Un respaldo
sin restauración exitosa no cumple el gate.

Inventariar Contact Form 7 con `wp plugin status contact-form-7` y buscar sus
shortcodes/bloques en posts, widgets y opciones. Si tiene uso, conservarlo y
probarlo; si no tiene uso, documentar evidencia y pedir confirmación antes de
desactivarlo. No borrarlo durante la migración.

### Paso 2: fijar imagen y empaquetar el core de la misma imagen

Cambiar el `FROM` a `wordpress:7.0.2-php8.3-apache`. Crear durante el build un
artefacto local del contenido de `/usr/src/wordpress` y conservarlo en una ruta
inmutable como `/opt/culturinfo/core/wordpress-7.0.2.zip`. No descargar el core
desde Internet en cada arranque ni usar `latest`.

Antes de implementar, confirmar dentro de la imagen la sintaxis soportada por
`wp core update <zip-local> --force`. Si WP-CLI no acepta el zip local, detenerse
y ajustar el mecanismo con una prueba reproducible; no sustituirlo por `cp -R`
ni por `rsync --delete` sobre el volumen.

Actualizar README con la versión exacta y el motivo del pin.

### Paso 3: reconciliar el core del volumen antes del seed

Después de que MariaDB y `wp-config.php` estén disponibles, pero antes de
`seed.sh` y antes de Apache, comparar `wp core version` con `7.0.2`:

- Volumen vacío: conservar la copia inicial del core 7.0.2.
- Volumen en 7.0.2: no modificar archivos.
- Volumen en otra versión: ejecutar `wp core update` desde el artefacto local,
  luego `wp core update-db` y `wp core verify-checksums --version=7.0.2`.
- Cualquier fallo debe detener el contenedor; no usar `|| true`.

Registrar versión anterior/nueva, nunca secretos. Preservar `wp-content`,
`wp-config.php`, `.htaccess`, uploads y archivos raíz ajenos al core. Ejecutar el
flujo dos veces y comprobar que la segunda no vuelve a migrar.

### Paso 4: cerrar compatibilidad de plugins y tema

Construir una instalación limpia y un clon del respaldo. En ambos:

1. Actualizar terceros uno por uno a versiones compatibles con WP 7.0.2/PHP 8.3.
2. Probar después de cada actualización, sin activar servicios de pago.
3. Ejecutar lint PHP de los seis plugins propios y el tema.
4. Revisar logs por `Fatal error`, `Uncaught`, `Parse error` y deprecaciones
   repetitivas provocadas por código propio.
5. Registrar en README las versiones efectivamente probadas.

La lista requerida se deriva del estado real, no de la lista obsoleta del plan
003. Si un tercero no es compatible y sí se usa, detener la promoción.

### Paso 5: smoke tests de datos y funciones

En instalación limpia y clon actualizado validar:

- `/`, las seis categorías, una noticia, `/contacto/`, `/wp-login.php`,
  `/wp-sitemap.xml` y una página de autor.
- Crear borrador, asignar escritor/foto, imagen destacada y categoría; publicarlo.
- Crear/renderizar anuncio sin dar acceso a un rol Author.
- Enviar contacto de prueba y comentario pendiente solo en el clon.
- Ejecutar publicación programada manual de un borrador marcado.
- Generar un MP3 con el worker y servirlo desde uploads.
- Verificar canonical, `og:image`, JSON-LD y favicon.

Comparar antes/después los conteos de posts, páginas, categorías, autores,
anuncios, comentarios y adjuntos; una diferencia no explicada bloquea el plan.

### Paso 6: promover y observar

Promover exactamente la imagen probada en una ventana corta. Conservar imagen y
snapshots anteriores. Confirmar `wp core version`, checksums, admin, uploads,
audio y cron. Observar logs HTTP/PHP/DB al menos durante el primer ciclo de
publicación programada antes de retirar el rollback.

## Verificaciones obligatorias

```text
docker manifest inspect wordpress:7.0.2-php8.3-apache
docker build --pull -t culturinfo-plan-008 .
docker exec culturinfo-plan-008 wp core version --allow-root
docker exec culturinfo-plan-008 wp core verify-checksums --version=7.0.2 --allow-root
docker exec culturinfo-plan-008 wp core update-db --allow-root
```

Resultados: build exitoso, versión exacta `7.0.2`, checksums válidos y base al
día. Reiniciar el mismo contenedor debe conservar conteos y no repetir migración.

## Criterios de finalización

- [ ] Respaldo de DB y ambos volúmenes restaurado con éxito.
- [ ] Imagen y volumen persistente ejecutan exactamente WordPress 7.0.2.
- [ ] La actualización usa un artefacto de core pinneado y falla cerrada.
- [ ] Instalación limpia y clon pasan todos los smoke tests.
- [ ] Tema y todos los plugins activos tienen compatibilidad demostrada.
- [ ] Conteos y archivos editoriales se preservan.
- [ ] Existe rollback probado y README actualizado.
- [ ] Solo se tocaron archivos justificados por fallos reproducibles.

## Condiciones de parada

- No se pueden restaurar los respaldos.
- El artefacto local no puede actualizar el core sin tocar `wp-content`.
- Un plugin usado no funciona en WP 7.0.2.
- Cambian conteos o desaparecen medios.
- Aparece un fatal, corrupción o migración no idempotente.
- Producción no tiene ventana/rollback autorizado.

## Notas de mantenimiento

Revisar mensualmente parches posteriores y crear un plan nuevo para cada salto;
no reemplazar el pin por `latest`. El chequeo de versión del volumen debe quedar
permanente para que futuros redeploys no aparenten actualizar sin hacerlo.
