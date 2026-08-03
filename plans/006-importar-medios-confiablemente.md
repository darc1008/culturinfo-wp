# Plan 006: Importar imágenes destacadas de forma confiable e idempotente

> **Instrucciones para el ejecutor**: Sigue cada paso y verifica el resultado.
> Detente ante una condición de parada. Actualiza la fila 006 en
> `plans/README.md` al terminar, salvo que el revisor mantenga el índice.
>
> **Comprobación de deriva (ejecutar primero)**:
> `git diff --stat aa2da49..HEAD -- Dockerfile seed/seed.sh seed/articles README.md`
> y `git status --short -- Dockerfile seed/seed.sh seed/articles README.md`.
> Los artículos ya tienen cambios locales; no los sobrescribas.

## Estado

- **Prioridad**: P1
- **Esfuerzo**: M
- **Riesgo**: MED
- **Depende de**: `plans/003-migrar-wordpress-7-0-2.md`
- **Categoría**: bug
- **Planificado en**: commit `aa2da49`, 2026-08-03 (árbol de trabajo sucio)

## Por qué importa

Los seis artículos seed declaran imágenes remotas, pero la instancia inspeccionada
no tenía ningún adjunto ni miniatura. El script descarta toda salida y convierte
cualquier error de descarga en éxito, por lo que la portada pierde jerarquía
visual y los metadatos sociales no tienen imagen. La importación debe reintentar,
validar el archivo, registrar el error y ser idempotente.

## Estado actual

- `seed/articles/*.md` contiene frontmatter `featured_image` con URL remota.
- `seed/seed.sh:177-180` ejecuta `wp media import` directamente sobre la URL,
  redirige stdout/stderr a null y termina con `|| true`.
- En la instancia local observada: 0 posts de tipo `attachment` y 0 miniaturas
  destacadas para los artículos seed.
- `Dockerfile:4-12` ya instala `curl` y WP-CLI.

Extracto:

```text
seed/seed.sh:177 CURRENT_THUMB=...
seed/seed.sh:178 IMG_URL=...
seed/seed.sh:180 wp media import "$IMG_URL" ... >/dev/null 2>&1 || true
```

## Comandos necesarios

| Propósito | Comando | Resultado esperado |
|---|---|---|
| Sintaxis | `wsl.exe -e sh -lc "bash -n /mnt/e/dev/culturinfo-wp/seed/seed.sh"` | exit 0 |
| Build | `docker build -t culturinfo-plan-006 .` | exit 0 |
| Adjuntos | `docker exec culturinfo-plan-006 wp post list --post_type=attachment --format=count --allow-root` | `6` |
| Miniaturas | `docker exec culturinfo-plan-006 wp eval '$q=new WP_Query(["post_type"=>"post","posts_per_page"=>-1,"meta_key"=>"_thumbnail_id"]); echo $q->found_posts;' --allow-root` | `6` para el seed actual |

## Alcance

**Dentro del alcance**:

- `seed/seed.sh`
- `seed/articles/*.md` solo si una URL concreta está rota o no es una imagen
- `Dockerfile` solo si una utilidad de validación imprescindible falta
- `README.md`

**Fuera del alcance**:

- No generar ni sustituir creativamente imágenes sin aprobación del cliente.
- No descargar imágenes sin licencia o atribución compatible.
- No rediseñar tamaños/crops del tema.
- No borrar medios existentes ni reemplazar miniaturas elegidas por un editor.

## Flujo Git

- Rama: `codex/006-featured-media-import`
- Commit sugerido: `fix: make seeded media imports reliable`
- No hagas push ni despliegue sin autorización.

## Pasos

### Paso 1: Crear un importador explícito

En `seed/seed.sh`, añade una función `import_featured_image` que reciba ID del
post, URL, slug y título. Debe:

1. Rechazar URL que no sea HTTPS.
2. Crear un directorio temporal con `mktemp -d` y limpiarlo mediante `trap`.
3. Descargar con `curl --fail --location --retry 3 --retry-all-errors
   --connect-timeout 10 --max-time 90`.
4. Capturar `Content-Type`; aceptar solo `image/jpeg`, `image/png` o `image/webp`.
5. Rechazar archivos vacíos o mayores a 15 MiB.
6. Nombrar el temporal `<slug>.<extensión-validada>` para que WP-CLI reconozca el
   tipo aun cuando la URL tenga query string.
7. Importar con `wp media import <archivo> --post_id=<id> --featured_image
   --porcelain --allow-root` y capturar el ID de adjunto.
8. Guardar alt text con el título saneado y meta
   `_culturinfo_featured_source_url` con la URL de origen.
9. Escribir logs útiles con slug/URL/causa, nunca ocultar stderr.

**Verificar**:
`git grep -n 'wp media import' -- seed/seed.sh`
→ ninguna invocación termina en `|| true` ni descarta ambos streams.

### Paso 2: Preservar trabajo editorial e idempotencia

Mantén la regla: si `_thumbnail_id` apunta a un adjunto existente, no descargues
ni reemplaces nada. Si el meta existe pero el adjunto fue borrado, limpia el ID
inválido y reimporta. Antes de crear un adjunto, busca uno con el mismo meta de
origen; si existe, reutilízalo y asígnalo al post.

Acumula fallos por artículo y termina el seed con código distinto de cero después
de procesarlos todos, mostrando un resumen. Esto entrega diagnóstico completo sin
dejar que el despliegue se declare correcto sin imágenes.

**Verificar**: ejecutar `/usr/local/bin/seed.sh` dos veces y comparar conteos
→ siguen existiendo exactamente 6 adjuntos y 6 posts con miniatura.

### Paso 3: Verificar cada fuente y licencia

Para cada `featured_image` actual, ejecuta una petición HEAD/GET y confirma 200,
tipo de imagen y tamaño permitido. Documenta en `README.md` que las imágenes seed
son remotas y su licencia/procedencia; si no puede confirmarse, detente y pide al
cliente archivos aprobados. No cambies URLs por búsquedas arbitrarias.

**Verificar**:
`docker exec culturinfo-plan-006 wp post meta list $(docker exec culturinfo-plan-006 wp post list --post_type=post --field=ID --allow-root | Select-Object -First 1) --allow-root`
→ incluye `_thumbnail_id` y el meta de origen.

### Paso 4: Probar fallos de red y tipo

En un contenedor efímero, sustituye solo mediante variable/fixture temporal una
URL por 404: deben verse tres reintentos, resumen con slug y exit no cero. Prueba
una respuesta `text/html`: debe rechazarse antes de `wp media import`. Restaura el
fixture y ejecuta el seed normal.

**Verificar**: logs del caso 404 contienen el slug y “HTTP”; logs del tipo
incorrecto contienen el MIME recibido; en ambos casos el proceso sale no cero.

## Plan de pruebas

- Primera instalación: 6 adjuntos y 6 miniaturas.
- Segundo seed: mismos conteos, sin duplicados.
- Miniatura editorial existente: no se reemplaza.
- `_thumbnail_id` huérfano: se repara.
- URL 404, timeout, MIME HTML, archivo vacío y archivo >15 MiB: fallo explícito.
- Alt text de cada adjunto no vacío y relacionado con el título.
- Portada y noticia sirven `img` con respuesta 200.

## Criterios de finalización

- [ ] Cada artículo seed tiene miniatura válida.
- [ ] El seed es idempotente y no duplica adjuntos.
- [ ] No existen errores de medios ocultos con `|| true`.
- [ ] Red, MIME y tamaño se validan con límites.
- [ ] Las imágenes editoriales existentes se preservan.
- [ ] Procedencia/licencia está documentada o aprobada.
- [ ] Solo se tocaron archivos dentro del alcance y el índice.
- [ ] La fila 006 está actualizada.

## Condiciones de parada

- No puede confirmarse la licencia/procedencia de una imagen.
- Una fuente requiere autenticación, cookies o evasión de restricciones.
- El servidor remoto bloquea descargas desde producción de forma persistente.
- El cliente prefiere archivos propios pero todavía no los entregó.
- Reparar exige borrar adjuntos existentes.
- Una verificación falla dos veces.

## Notas de mantenimiento

- Las fuentes remotas siguen siendo una dependencia de primera instalación;
  valorar luego empaquetar assets aprobados en el repositorio o storage propio.
- Revisar límites si se aceptan formatos adicionales.
- Rank Math usará estas miniaturas para Open Graph y `NewsArticle` en el plan 007.
