# Plan 012: Endurecer cargas, ejecución de archivos y permisos web

> **Instrucciones para el ejecutor**: este plan cambia propietarios y capacidad
> de modificación. Probar en volumen vacío y clon antes de producción. Validar
> cada ruta real y no ejecutar operaciones recursivas sobre variables vacías,
> symlinks o rutas fuera de `/var/www/html`.
>
> **Comprobación de deriva (primero)**:
> `git diff --stat 2b95226..HEAD -- Dockerfile entrypoint.sh seed/seed.sh README.md wp-content/plugins/culturinfo-security wp-content/plugins/culturinfo-audio`
> y `git status --short`.

## Estado

- **Prioridad**: P1
- **Esfuerzo**: L
- **Riesgo**: HIGH
- **Depende de**: 008, 009
- **Categoría**: security
- **Planificado en**: `2b95226`, 2026-08-18

## Por qué importa

WordPress valida extensiones/MIME y requiere `upload_files`, pero el repo fija
64 MiB para cualquier POST (`Dockerfile:28-32`), todo el core y plugins propios
quedan inicialmente bajo `www-data` (`entrypoint.sh:37-79`) y no se define
`DISALLOW_FILE_EDIT`/`DISALLOW_FILE_MODS`. Una sesión admin o vulnerabilidad con
escritura puede modificar PHP. Uploads es persistente y escribible, por lo que
también debe impedirse ejecutar código aunque un archivo eluda validación.

## Estado actual verificado

- `entrypoint.sh:41` hace `chown -R www-data:www-data /var/www/html` en volumen
  nuevo; cada plugin propio también queda en `www-data`.
- Solo uploads se normaliza explícitamente después del seed (`:134-151`).
- El worker Piper corre como `www-data` y escribe MP3 en uploads (`:153-154`).
- No existe filtro `upload_mimes`, `wp_handle_upload_prefilter` ni límite por tipo.
- No existe regla Apache específica que prohíba PHP/CGI en uploads.
- Formulario de contacto y comentarios no admiten adjuntos.

## Alcance

**Dentro**: `Dockerfile`, `entrypoint.sh`, `seed/seed.sh`, configuración Apache,
módulo uploads/roles de `culturinfo-security`, `.env.example` y `README.md`.

**Fuera**: adjuntos públicos en formularios, SVG, instalación web de plugins,
antivirus residente, storage S3 o borrar medios existentes.

## Política objetivo

- Web (`www-data`) escribe únicamente en `wp-content/uploads` y directorios
  temporales expresamente documentados.
- Core, raíz, temas y plugins: `root:www-data`, dirs 755, files 644.
- `wp-config.php`: `root:www-data`, 640.
- Uploads: `www-data:www-data`, dirs 775, files 664.
- Editor/instalador web bloqueado; WP-CLI de despliegue como root conserva control.
- Solo Administrator y Editor pueden subir medios.
- Lista inicial: JPEG, PNG, WebP, AVIF y GIF. MP3 generado por el worker no pasa
  por el formulario. PDF/audio/video manual quedan deshabilitados hasta requisito
  editorial y prueba específica.

## Implementación

### Paso 1: bloquear edición/modificación desde solicitudes web

Gestionar idempotentemente en `wp-config.php`:

```php
define('DISALLOW_FILE_EDIT', true);
define('DISALLOW_FILE_MODS', !(defined('WP_CLI') && WP_CLI));
```

No duplicar constantes. El seed WP-CLI debe poder instalar/actualizar antes del
cierre de permisos; desde web deben desaparecer editor, instalación y actualización
de core/plugins/temas. Si `WP_SITEURL` es HTTPS, conservar `FORCE_SSL_ADMIN=true`
con la detección proxy ya implementada.

### Paso 2: aplicar permisos mínimos después de seed

Reemplazar todos los `chown -R www-data` de core/tema/plugins. Crear funciones
shell que:

1. Rechacen ruta vacía.
2. Exijan ruta absoluta prevista.
3. Resuelvan con `readlink -f` y comprueben frontera exacta
   `/var/www/html` o `/var/www/html/...`.
4. Rechacen symlink en destino y no atraviesen filesystem (`find -xdev`).
5. Fallen ante cualquier error; no `2>/dev/null || true`.

El seed corre como root. Después, fijar permisos objetivo. Crear solo directorios
temporales que un plugin demuestre necesitar; no hacer escribible todo
`wp-content`. Repetir el arranque debe producir exactamente la misma matriz.

El volumen MySQL permanece `mysql:mysql`; queda fuera de estas funciones.

### Paso 3: impedir ejecución en uploads desde Apache

Crear una configuración versionada de Apache, habilitada en build, para
`/var/www/html/wp-content/uploads`:

- `Options -ExecCGI -Indexes`.
- `AllowOverride None` en ese directorio.
- retirar handlers/tipos ejecutables.
- `Require all denied` para extensiones PHP históricas y alternativas
  (`php`, `phtml`, `pht`, `phar`, `cgi`, `pl`, `py`, `sh`) sin distinguir mayúsculas.
- `X-Content-Type-Options: nosniff` si `mod_headers` está habilitado.

Preferir configuración Apache root-owned a un `.htaccess` dentro de uploads,
porque uploads es escribible por web. Probar un archivo benigno `probe.php.txt`
como texto y un `probe.php` sin código sensible: el primero sirve como texto y el
segundo devuelve 403/404, nunca se ejecuta. Eliminar fixtures del clon.

### Paso 4: lista MIME, tamaño y dimensiones

En `culturinfo-security` aplicar `upload_mimes` y
`wp_handle_upload_prefilter` solo a cargas HTTP:

- Permitir `jpg/jpeg`, `png`, `webp`, `avif`, `gif`.
- Rechazar SVG/SVGZ, HTML, scripts, archivos dobles y todo tipo no listado.
- Máximo 15 MiB por imagen aunque PHP permita 64 MiB para otras operaciones.
- Máximo 36 megapíxeles y dimensiones positivas usando un parser seguro.
- Revalidar extensión y MIME real con APIs core (`wp_check_filetype_and_ext`) y
  fallo cerrado si no coinciden.
- Sanear nombre y no confiar en MIME declarado por el navegador.

No reprocesar archivos generados internamente por Piper. Un formato nuevo exige
actualizar lista, tests y documentación; no permitir `unfiltered_upload`.

### Paso 5: limitar quién puede subir

Versionar una migración de capacidades en `culturinfo-security`: Administrator y
Editor conservan `upload_files`; Author, Contributor y Subscriber no. Los autores
editoriales son el CPT independiente existente, así que esta restricción no
cambia la firma visible de las noticias.

Antes de aplicar en producción, listar roles personalizados y usuarios que hoy
suben archivos. Si el cliente necesita que el rol Author suba, detenerse y definir
un rol newsroom limitado con cuotas; no conservar acceso por accidente.

Mostrar mensaje claro al rol sin permiso y comprobar que REST/media, async-upload
y subida directa al editor aplican la misma capacidad.

### Paso 6: cuotas, retención y visibilidad

Registrar métricas agregadas de cargas rechazadas por motivo, nunca nombre/IP
completos. Añadir un límite operativo configurable por usuario (por defecto 100
cargas/día) mediante el contador atómico del plan 009 para frenar abuso de una
cuenta comprometida. Administradores pueden desbloquear con nonce/capacidad y
registro agregado.

No borrar automáticamente medios editoriales. Documentar monitoreo de tamaño del
volumen, respaldo y alerta de capacidad en Coolify. Audio e imágenes comparten el
volumen, por lo que una cuota de formulario no debe contar MP3 del worker.

### Paso 7: pruebas integrales con audio y redeploy

En volumen vacío y clon:

- Editor sube cada imagen permitida y WordPress genera thumbnails.
- Author no puede subir por admin, REST ni endpoint directo.
- MIME falso, doble extensión, SVG, >15 MiB, >36 MP y script se rechazan.
- Un archivo ejecutable colocado manualmente en uploads no se ejecuta.
- `www-data` escribe uploads pero no raíz/core/tema/plugins/wp-config.
- WP-CLI root ejecuta seed y actualización controlada.
- Piper crea/sustituye MP3, Apache lo sirve y reinicio lo conserva.
- Rank Math sigue leyendo imágenes destacadas y `og:image` devuelve 200.
- Carpeta año/mes se crea al cambiar el mes.

## Verificaciones obligatorias

```text
wp eval 'var_export([DISALLOW_FILE_EDIT, DISALLOW_FILE_MODS]);' --allow-root
stat -c '%U:%G %a %n' /var/www/html/wp-config.php /var/www/html/wp-content/uploads
find /var/www/html/wp-content/themes /var/www/html/wp-content/plugins -user www-data -print
curl -i https://DOMINIO/wp-content/uploads/security-probe.php
```

Por CLI: `DISALLOW_FILE_EDIT=true`, `DISALLOW_FILE_MODS=false`; en diagnóstico web
efímero ambos true. `find` no devuelve código owned por www-data y probe no ejecuta.

## Criterios de finalización

- [ ] Edición/instalación web de archivos está bloqueada.
- [ ] Solo uploads/temporales aprobados son escribibles por Apache.
- [ ] Apache niega ejecución y listado en uploads.
- [ ] Cargas HTTP usan lista cerrada, 15 MiB y 36 MP.
- [ ] Solo admin/editor poseen `upload_files`.
- [ ] Cuota por usuario limita cuenta comprometida.
- [ ] Piper, thumbnails, OG y persistencia continúan funcionando.
- [ ] Instalación limpia, clon y segundo arranque pasan.
- [ ] README documenta matriz, formatos, recuperación y monitoreo.

## Condiciones de parada

- Se detecta symlink/reparse point en rutas a modificar.
- Un plugin necesita escribir código propio durante solicitudes web.
- El proveedor de volumen no respeta propietarios/permisos Unix.
- El cliente requiere uploads del rol Author o formatos fuera de lista sin definir
  proceso seguro.
- Apache sigue ejecutando cualquier extensión bloqueada.
- El cambio rompe uploads, audio, seed o actualización del plan 008.

## Notas de mantenimiento

No se añade ClamAV en esta fase por costo de imagen, RAM y actualización de firmas.
La defensa depende de mantener lista cerrada, core/plugins al día, no ejecución y
permisos mínimos; revisar trimestralmente formatos, roles, cuota y espacio.
