# Plan 002: Corregir HTTPS detrás del proxy y eliminar URLs mixtas

> **Reconciliación 2026-08-18**: REJECTED porque fue resuelto por cambios
> independientes: `configure_proxy.php` actualiza la detección HTTPS y el seed
> reconcilia `home`/`siteurl`. Producción ya responde detrás del proxy.

> **Instrucciones para el ejecutor**: Sigue todos los pasos y verifica cada uno.
> Si ocurre una condición de parada, detente y reporta. Al terminar, actualiza
> la fila 002 de `plans/README.md`, salvo indicación del revisor.
>
> **Comprobación de deriva (ejecutar primero)**:
> `git diff --stat aa2da49..HEAD -- entrypoint.sh seed/seed.sh README.md` y
> `git status --short -- entrypoint.sh seed/seed.sh README.md`. El árbol ya
> estaba sucio; no descartes cambios. Compara los extractos antes de editar.

## Estado

- **Prioridad**: P1
- **Esfuerzo**: M
- **Riesgo**: HIGH
- **Depende de**: `plans/001-retirar-y-rotar-secretos.md`
- **Categoría**: bug
- **Planificado en**: commit `aa2da49`, 2026-08-03 (árbol de trabajo sucio)

## Por qué importa

En producción, `/wp-sitemap.xml` redirige hacia sí mismo y el HTML servido por
HTTPS contiene recursos `http://`. WordPress no está reconociendo el esquema
original que envía el proxy de Coolify, lo que rompe el sitemap, genera contenido
mixto y degrada seguridad, caché, canonicales y rastreo. La corrección debe confiar
solo en cabeceras de proxies explícitamente autorizados.

## Estado actual

- `entrypoint.sh:60-73` crea `wp-config.php` sin lógica para
  `HTTP_X_FORWARDED_PROTO` ni `HTTPS`.
- `seed/seed.sh:41-48` instala con `WP_SITEURL`, y luego no reconcilia `home` y
  `siteurl` en cada despliegue.
- El dominio esperado es `https://culturinfo.statusloop.app`.
- Evidencia del diagnóstico: el sitemap de producción devolvía 301 hacia la
  misma URL y el HTML HTTPS referenciaba assets HTTP.

Extracto identificador:

```text
entrypoint.sh:64-72 wp config create ... sin bloque reverse-proxy
seed/seed.sh:42    --url="${WP_SITEURL:-https://culturinfo.statusloop.app}"
```

## Comandos necesarios

| Propósito | Comando | Resultado esperado |
|---|---|---|
| Sintaxis | `wsl.exe -e sh -lc "bash -n /mnt/e/dev/culturinfo-wp/entrypoint.sh && bash -n /mnt/e/dev/culturinfo-wp/seed/seed.sh"` | exit 0 |
| Build | `docker build -t culturinfo-plan-002 .` | exit 0 |
| Cabeceras | `curl.exe -sS -I https://culturinfo.statusloop.app/wp-sitemap.xml` | respuesta final 200, sin bucle |
| HTML mixto | `curl.exe -sS https://culturinfo.statusloop.app/` | ninguna URL `http://culturinfo.statusloop.app` |

## Alcance

**Dentro del alcance**:

- `entrypoint.sh`
- `seed/seed.sh`
- `README.md`

**Fuera del alcance**:

- No cambiar reglas DNS, certificados ni configuración interna de Coolify.
- No añadir redirecciones globales en el tema o en JavaScript.
- No instalar un plugin de SSL.
- No cambiar el dominio público.

## Flujo Git

- Rama: `codex/002-https-proxy`
- Commit sugerido: `fix: honor trusted proxy https headers`
- Preserva los cambios locales existentes y no hagas push sin autorización.

## Pasos

### Paso 1: Definir un contrato de proxy confiable

Añade una variable de entorno documentada `TRUST_PROXY_HEADERS`, con default
seguro `false`. En producción Coolify debe configurarla en `true`; localmente
permanece `false`. No aceptes `X-Forwarded-Proto` de cualquier cliente cuando la
variable esté desactivada.

En `entrypoint.sh`, después de crear `wp-config.php`, usa `wp config set` para
insertar código PHP antes del comentario final: solo cuando
`TRUST_PROXY_HEADERS=true` y `HTTP_X_FORWARDED_PROTO` contiene `https` como token
separado por coma, establece `$_SERVER['HTTPS']='on'` y
`$_SERVER['SERVER_PORT']='443'`. La comparación debe ser insensible a mayúsculas
y tolerar espacios de proxies encadenados.

**Verificar**:
`git grep -n 'TRUST_PROXY_HEADERS' -- entrypoint.sh README.md`
→ existe en código y documentación.

### Paso 2: Reconciliar URLs canónicas de WordPress

En `seed/seed.sh`, valida que `WP_SITEURL` sea una URL absoluta `http` o `https`
sin barra final. Después de confirmar que WordPress está instalado, actualiza
idempotentemente las opciones `home` y `siteurl` con ese valor. En producción,
exige `https`; permite `http://localhost:<puerto>` para desarrollo.

No hagas reemplazos indiscriminados de base de datos. Documenta un comando
operativo separado de `wp search-replace` con `--dry-run` y luego ejecución solo
para corregir URLs históricas del dominio exacto, preservando datos serializados.

**Verificar**:
`wsl.exe -e sh -lc "bash -n /mnt/e/dev/culturinfo-wp/entrypoint.sh && bash -n /mnt/e/dev/culturinfo-wp/seed/seed.sh"`
→ exit 0.

### Paso 3: Probar ambos modelos de confianza en local

Construye y arranca la imagen con secretos efímeros, puerto 8092,
`WP_SITEURL=https://example.test` y `TRUST_PROXY_HEADERS=true`. Envía una petición
HTTP al contenedor con `Host: example.test` y `X-Forwarded-Proto: https`. El HTML
debe generar `https://example.test/...` y no redirigir a HTTP.

Repite con `TRUST_PROXY_HEADERS=false`: una cabecera externa no debe forzar HTTPS.
Esta segunda prueba evita convertir una cabecera falsificable en señal confiable.

**Verificar**:
`curl.exe -sS -H "Host: example.test" -H "X-Forwarded-Proto: https" http://localhost:8092/wp-sitemap.xml`
→ XML del sitemap, sin redirección circular.

### Paso 4: Desplegar y verificar producción

Configura `TRUST_PROXY_HEADERS=true` en Coolify, conserva
`WP_SITEURL=https://culturinfo.statusloop.app`, despliega y ejecuta:

**Verificar**:
`curl.exe -sS -L --max-redirs 3 -o NUL -w "%{http_code} %{url_effective}`n" https://culturinfo.statusloop.app/wp-sitemap.xml`
→ `200 https://culturinfo.statusloop.app/wp-sitemap.xml`.

**Verificar**:
`$html = curl.exe -sS https://culturinfo.statusloop.app/; if ($html -match 'http://culturinfo\.statusloop\.app') { exit 1 }`
→ exit 0.

## Plan de pruebas

- Proxy confiable + `X-Forwarded-Proto: https`: WordPress percibe SSL.
- Proxy no confiable + la misma cabecera: no se altera `HTTPS`.
- `X-Forwarded-Proto: http, https` y espacios: se detecta el token HTTPS.
- Sitemap: máximo tres redirecciones y respuesta final 200.
- Portada y una noticia: cero URLs absolutas HTTP del dominio de producción.
- Login: `/wp-admin/` conserva HTTPS y cookies seguras detrás del proxy.

## Criterios de finalización

- [ ] La confianza de proxy es explícita y desactivada por defecto.
- [ ] `home` y `siteurl` se reconcilian idempotentemente con `WP_SITEURL`.
- [ ] El sitemap de producción responde 200 sin bucle.
- [ ] Portada y noticia no contienen recursos HTTP del dominio.
- [ ] `/wp-admin/` no rebaja de HTTPS a HTTP.
- [ ] No se modificaron archivos fuera del alcance, salvo el índice de planes.
- [ ] La fila 002 está actualizada.

## Condiciones de parada

- Coolify no envía `X-Forwarded-Proto` o lo sobrescribe de forma inesperada.
- El proxy está expuesto directamente a Internet sin una frontera de confianza.
- `home` y `siteurl` intencionalmente usan dominios distintos.
- Corregir URLs exige reemplazar un dominio no identificado o datos serializados
  sin `wp search-replace`.
- Una verificación falla dos veces.

## Notas de mantenimiento

- Mantener la detección de HTTPS en `wp-config.php`, antes de cargar WordPress;
  no moverla al tema.
- Si cambia el proxy, volver a validar la semántica de cabeceras y la frontera de
  confianza.
- HSTS queda fuera de este plan hasta decidir qué capa lo controla.
