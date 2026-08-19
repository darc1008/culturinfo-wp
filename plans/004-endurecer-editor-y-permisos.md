# Plan 004: Desactivar la edición web de archivos y endurecer permisos

> **Reconciliación 2026-08-18**: REJECTED; supersedido por el plan 012, que añade
> validación de MIME/tamaño, bloqueo de ejecución en uploads y una matriz de roles.

> **Instrucciones para el ejecutor**: Sigue los pasos en orden, ejecuta cada
> verificación y detente ante una condición de parada. Actualiza la fila 004 en
> `plans/README.md` al terminar, salvo que el revisor mantenga el índice.
>
> **Comprobación de deriva (ejecutar primero)**:
> `git diff --stat aa2da49..HEAD -- Dockerfile entrypoint.sh seed/seed.sh README.md`
> y `git status --short -- Dockerfile entrypoint.sh seed/seed.sh README.md`.
> Hay cambios locales preexistentes: no los descartes. Compara los extractos.

## Estado

- **Prioridad**: P1
- **Esfuerzo**: M
- **Riesgo**: MED
- **Depende de**: `plans/001-retirar-y-rotar-secretos.md`, `plans/003-migrar-wordpress-7-0-2.md`
- **Categoría**: security
- **Planificado en**: commit `aa2da49`, 2026-08-03 (árbol de trabajo sucio)

## Por qué importa

El contenedor entrega todo WordPress y el código propio a `www-data`, y
`DISALLOW_FILE_EDIT` está desactivado. Una sesión administrativa comprometida
puede modificar PHP desde el navegador; además, una vulnerabilidad de escritura
en la aplicación tiene demasiado alcance. El objetivo es bloquear editores y
modificaciones de código desde solicitudes web, manteniendo WP-CLI como ruta de
actualización controlada durante despliegues.

## Estado actual

- `entrypoint.sh:13-18` copia el core y ejecuta `chown -R www-data:www-data` sobre
  todo `/var/www/html`.
- `entrypoint.sh:20-30` sincroniza tema y plugin propios y también los entrega a
  `www-data`.
- La inspección de la instancia mostró `DISALLOW_FILE_EDIT=false` y
  `FORCE_SSL_ADMIN=false`.
- `seed/seed.sh:80-87` instala plugins mediante WP-CLI; esa ruta debe seguir
  funcionando durante el arranque/despliegue.

## Comandos necesarios

| Propósito | Comando | Resultado esperado |
|---|---|---|
| Sintaxis | `wsl.exe -e sh -lc "bash -n /mnt/e/dev/culturinfo-wp/entrypoint.sh && bash -n /mnt/e/dev/culturinfo-wp/seed/seed.sh"` | exit 0 |
| Build | `docker build -t culturinfo-plan-004 .` | exit 0 |
| Constante CLI | `docker exec culturinfo-plan-004 wp eval "var_export([DISALLOW_FILE_EDIT, DISALLOW_FILE_MODS]);" --allow-root` | `array (0 => true, 1 => false)` porque WP-CLI conserva la ruta de despliegue |
| Permisos | `docker exec culturinfo-plan-004 stat -c '%U:%G %a %n' /var/www/html/wp-config.php /var/www/html/wp-content/uploads` | config no escribible por público; uploads escribible por `www-data` |

## Alcance

**Dentro del alcance**:

- `entrypoint.sh`
- `seed/seed.sh`
- `Dockerfile` si hace falta copiar un helper de permisos
- `README.md`

**Fuera del alcance**:

- No cambiar permisos de `/var/lib/mysql` salvo conservar `mysql:mysql`.
- No ejecutar chmod/chown sobre rutas fuera de `/var/www/html` y `/var/lib/mysql`.
- No bloquear la carga de imágenes en `wp-content/uploads`.
- No cambiar roles editoriales ni capacidades de anuncios; eso corresponde al
  plan 005.
- No añadir CSP/HSTS en este plan.

## Flujo Git

- Rama: `codex/004-hardening-wordpress-files`
- Commit sugerido: `security: lock WordPress file modifications`
- No hagas push ni despliegue sin autorización.

## Pasos

### Paso 1: Establecer una política de escritura explícita

En `entrypoint.sh`, reemplaza el `chown -R` global por funciones que operen sobre
rutas explícitas y validen que el destino resuelto esté bajo `/var/www/html`.
Después de ejecutar el seed:

- Core, tema y plugins: propietario `root:www-data`, directorios 755, archivos 644.
- `wp-config.php`: `root:www-data`, modo 640.
- `wp-content/uploads`: `www-data:www-data`, directorios 775, archivos 664.
- Directorios temporales necesarios (`upgrade`, caché solo si existe):
  `www-data:www-data`, sin seguir enlaces simbólicos.

Antes de cualquier operación recursiva, comprueba que la ruta existe, no es un
enlace/reparse point y su ruta real empieza exactamente por `/var/www/html/`.
No ocultes fallos con `|| true`.

**Verificar**:
`wsl.exe -e sh -lc "bash -n /mnt/e/dev/culturinfo-wp/entrypoint.sh"`
→ exit 0.

### Paso 2: Bloquear edición y modificaciones desde la web

Gestiona las constantes desde `entrypoint.sh` mediante `wp config set` para que
sean idempotentes. `DISALLOW_FILE_EDIT` debe ser siempre `true`.

`DISALLOW_FILE_MODS` debe ser `true` para solicitudes web, pero permitir el flujo
WP-CLI controlado del seed. Implementa una expresión PHP equivalente a:

```php
define('DISALLOW_FILE_MODS', !(defined('WP_CLI') && WP_CLI));
```

No definas la misma constante dos veces. Como el plan 002 corrige detección SSL,
define también `FORCE_SSL_ADMIN=true` solo cuando `WP_SITEURL` sea HTTPS; para
localhost HTTP debe permanecer false.

**Verificar**:
`docker exec culturinfo-plan-004 wp eval "echo (defined('WP_CLI') && WP_CLI) ? 'cli' : 'web';" --allow-root`
→ `cli`; el seed sigue pudiendo instalar/actualizar plugins.

**Verificar web**: desde una página de diagnóstico temporal ejecutada únicamente
en el contenedor de prueba, las constantes deben resultar
`DISALLOW_FILE_EDIT=true`, `DISALLOW_FILE_MODS=true`; elimina el diagnóstico al
terminar.

### Paso 3: Verificar escritura mínima

Arranca un volumen nuevo. Como `www-data`, crea y elimina un archivo de prueba
solo en `wp-content/uploads`; debe funcionar. Como `www-data`, intenta crear un
archivo en el tema propio, plugin propio, raíz WordPress y `wp-content/plugins`;
todas deben fallar. Ejecuta el seed una segunda vez como root; debe completar sin
errores, demostrando que el despliegue controlado conserva capacidad de actualizar.

**Verificar**:
`docker exec -u www-data culturinfo-plan-004 sh -c 'touch /var/www/html/wp-content/uploads/.perm-test && rm /var/www/html/wp-content/uploads/.perm-test'`
→ exit 0.

**Verificar**:
`docker exec -u www-data culturinfo-plan-004 sh -c 'touch /var/www/html/wp-content/themes/culturinfo/.must-fail'`
→ exit distinto de 0 y ningún archivo creado.

### Paso 4: Documentar la operación

En `README.md`, indica que instalación/actualización de core, temas y plugins se
hace mediante nueva imagen/seed con WP-CLI, no desde el panel. Documenta uploads
como única zona persistente escribible por Apache y cómo comprobar permisos.

**Verificar**:
`git grep -n 'DISALLOW_FILE_EDIT\|DISALLOW_FILE_MODS\|wp-content/uploads' -- README.md entrypoint.sh`
→ aparecen política y aplicación.

## Plan de pruebas

- Instalación limpia y segundo arranque idempotente.
- Subida de imagen desde admin funciona.
- Editor de temas/plugins no aparece o devuelve permiso denegado.
- Instalación de plugin desde el navegador está bloqueada.
- Seed por WP-CLI continúa activo.
- Usuario `www-data` no escribe core/tema/plugins y sí escribe uploads.
- No se siguen symlinks durante el endurecimiento.

## Criterios de finalización

- [ ] Edición y modificaciones web de archivos están bloqueadas.
- [ ] WP-CLI controlado puede completar el seed.
- [ ] `wp-config.php` tiene modo 640 o más restrictivo.
- [ ] Solo uploads/temporales documentados son escribibles por Apache.
- [ ] No existen `chown -R www-data:www-data /var/www/html` globales.
- [ ] Subidas de medios siguen funcionando.
- [ ] Solo se tocaron archivos dentro del alcance y el índice.
- [ ] La fila 004 está actualizada.

## Condiciones de parada

- Un plugin requerido necesita escribir su propio código durante solicitudes web.
- El volumen o proveedor no permite propietario root y grupo `www-data`.
- La política impide uploads o el segundo arranque.
- Se detecta un enlace simbólico dentro de una ruta que se iba a recorrer.
- El cambio exige hacer escribible todo `/var/www/html`.
- Una verificación falla dos veces.

## Notas de mantenimiento

- Las actualizaciones deben entrar por reconstrucción de imagen; documentar esta
  expectativa al equipo editorial.
- Revisar permisos después de instalar nuevos plugins que creen directorios de
  caché o exportación y conceder escritura solo a esos directorios.
- La defensa no sustituye copias de seguridad ni actualización oportuna.
