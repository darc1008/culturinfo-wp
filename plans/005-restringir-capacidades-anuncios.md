# Plan 005: Restringir la administración de anuncios a editores autorizados

> **Reconciliación 2026-08-18**: REJECTED porque el hallazgo fue corregido por
> cambios independientes. El plugin actual usa capacidades propias, `map_meta_cap`,
> migración versionada y `edit_culturinfo_ad` al guardar.

> **Instrucciones para el ejecutor**: Ejecuta el plan completo y cada gate. No
> improvises ante deriva o fallos. Actualiza la fila 005 en `plans/README.md` al
> terminar, salvo que el revisor mantenga el índice.
>
> **Comprobación de deriva (ejecutar primero)**:
> `git diff --stat aa2da49..HEAD -- wp-content/plugins/culturinfo-ads/culturinfo-ads.php README.md`
> y `git status --short -- wp-content/plugins/culturinfo-ads README.md`.
> Hay cambios locales preexistentes; no los descartes.

## Estado

- **Prioridad**: P1
- **Esfuerzo**: M
- **Riesgo**: MED
- **Depende de**: `plans/003-migrar-wordpress-7-0-2.md`
- **Categoría**: security
- **Planificado en**: commit `aa2da49`, 2026-08-03 (árbol de trabajo sucio)

## Por qué importa

El tipo de contenido de anuncios reutiliza las capacidades de posts. Por ello,
un autor que puede publicar noticias también puede crear o publicar publicidad,
una separación de funciones incorrecta para un periódico. El gestor debe usar
capacidades propias: administradores y editores gestionan anuncios; autores,
colaboradores y suscriptores no pueden ver el menú ni mutarlos por REST o admin.

## Estado actual

- `wp-content/plugins/culturinfo-ads/culturinfo-ads.php:31-60` registra
  `culturinfo_ad` con `'capability_type' => 'post'` y sin `map_meta_cap`.
- `culturinfo-ads.php:184-207` guarda metadatos después de comprobar
  `current_user_can('edit_post', $post_id)`.
- El tipo es no público pero `show_ui` y `show_in_rest` son true.
- Versión del plugin: `1.0.0`; no existe migración versionada de capacidades.

Extracto:

```text
culturinfo-ads.php:56 'capability_type' => 'post',
culturinfo-ads.php:191 current_user_can('edit_post', $post_id)
```

## Comandos necesarios

| Propósito | Comando | Resultado esperado |
|---|---|---|
| Sintaxis PHP | `docker run --rm -v "${PWD}:/work" -w /work php:8.3-cli php -l wp-content/plugins/culturinfo-ads/culturinfo-ads.php` | `No syntax errors` |
| Build | `docker build -t culturinfo-plan-005 .` | exit 0 |
| Caps editor | `docker exec culturinfo-plan-005 wp cap list editor --allow-root` | contiene `edit_culturinfo_ads` y `publish_culturinfo_ads` |
| Caps autor | `docker exec culturinfo-plan-005 wp cap list author --allow-root` | no contiene capacidades `culturinfo_ad` |

## Alcance

**Dentro del alcance**:

- `wp-content/plugins/culturinfo-ads/culturinfo-ads.php`
- `README.md`

**Fuera del alcance**:

- No cambiar ubicaciones, render, prioridad ni vencimiento de anuncios.
- No dar acceso a autores, colaboradores o suscriptores.
- No cambiar capacidades de posts/páginas normales.
- No borrar anuncios existentes al activar/desactivar.

## Flujo Git

- Rama: `codex/005-ad-capabilities`
- Commit sugerido: `security: isolate advertisement capabilities`
- No hagas push ni despliegue sin autorización.

## Pasos

### Paso 1: Registrar capacidades propias

En `culturinfo_ads_register_post_type`, cambia `capability_type` a la pareja
singular/plural `array('culturinfo_ad', 'culturinfo_ads')`, activa
`map_meta_cap => true` y declara explícitamente este mapa:

- `edit_post` → `edit_culturinfo_ad`
- `read_post` → `read_culturinfo_ad`
- `delete_post` → `delete_culturinfo_ad`
- `edit_posts` → `edit_culturinfo_ads`
- `edit_others_posts` → `edit_others_culturinfo_ads`
- `publish_posts` → `publish_culturinfo_ads`
- `read_private_posts` → `read_private_culturinfo_ads`
- `delete_posts`, `delete_private_posts`, `delete_published_posts`,
  `delete_others_posts`, `edit_private_posts` y `edit_published_posts` a sus
  equivalentes `*_culturinfo_ads`.

Mantén `public=false` y `show_in_rest=true`; REST debe respetar las mismas caps.

**Verificar**:
`git grep -n "capability_type\|map_meta_cap\|edit_culturinfo_ads" -- wp-content/plugins/culturinfo-ads/culturinfo-ads.php`
→ aparecen tipo propio, mapeo y caps.

### Paso 2: Añadir concesión y migración idempotentes

Crea funciones en el mismo plugin para devolver la lista única de capacidades y
concederla a `administrator` y `editor`. Registra un activation hook y una
migración por versión mediante opción `culturinfo_ads_cap_version`; esto cubre
instalaciones donde el plugin ya está activo y el hook de activación no correrá
al desplegar el archivo actualizado.

Ejecuta la migración solo si la versión almacenada es inferior a la versión de
capacidades del código. No elimines capacidades al desactivar: hacerlo puede
bloquear la gestión de anuncios al reactivar. Documenta que una desinstalación
futura debe limpiar las caps explícitamente.

**Verificar**:
`docker exec culturinfo-plan-005 wp option get culturinfo_ads_cap_version --allow-root`
→ versión esperada definida por el código.

### Paso 3: Usar la meta-capacidad correcta al guardar

En `culturinfo_ads_save_meta`, cambia la comprobación a
`current_user_can('edit_culturinfo_ad', $post_id)`. Conserva nonce, autosave,
saneamiento y allow-list de espacios existentes.

Revisa cualquier otro endpoint, acción masiva o REST relacionado dentro del
archivo; debe apoyarse en las capacidades del post type, no en `edit_posts`.

**Verificar**:
`git grep -n "current_user_can('edit_post'" -- wp-content/plugins/culturinfo-ads`
→ sin coincidencias.

### Paso 4: Probar la matriz de roles

Crea usuarios efímeros de prueba para administrator, editor, author, contributor
y subscriber. Como admin y editor, comprueba que pueden listar, crear, editar,
publicar y borrar un anuncio. Como los otros tres, comprueba:

- El menú Anuncios no aparece.
- `/wp-json/wp/v2/culturinfo_ad` no permite crear ni listar privados.
- Una petición de actualización directa al post devuelve 401/403.

Elimina solo esos usuarios y anuncios de prueba.

**Verificar**:
`docker exec culturinfo-plan-005 wp cap list author --allow-root | Select-String 'culturinfo_ad'`
→ sin salida.

**Verificar**:
`docker exec culturinfo-plan-005 wp cap list editor --allow-root | Select-String 'publish_culturinfo_ads'`
→ una coincidencia.

### Paso 5: Documentar responsabilidades

Actualiza `README.md`: administradores y editores administran publicidad;
autores solo contenido editorial. Indica que modificar esta matriz requiere
subir la versión de migración de capacidades.

**Verificar**:
`git grep -n 'administradores.*editores\|versión de.*capacidades' -- README.md`
→ documentación presente.

## Plan de pruebas

- Activación limpia concede caps a admin/editor.
- Actualización de un plugin ya activo ejecuta migración una vez.
- Repetir migración no cambia ni duplica estado.
- Admin/editor: CRUD y publicación por admin y REST.
- Author/contributor/subscriber: sin menú y REST denegado.
- El render frontend de anuncios publicados sigue igual para visitantes.
- Desactivar/reactivar conserva datos y recupera acceso.

## Criterios de finalización

- [ ] El CPT no reutiliza capacidades de posts.
- [ ] Admin y editor poseen todas las caps propias.
- [ ] Author, contributor y subscriber no poseen ninguna.
- [ ] Guardado y REST aplican la matriz correcta.
- [ ] Existe migración de capacidades versionada e idempotente.
- [ ] Anuncios existentes permanecen intactos.
- [ ] Solo se tocaron plugin, README e índice.
- [ ] La fila 005 está actualizada.

## Condiciones de parada

- El cliente exige que autores gestionen anuncios; requiere decisión explícita.
- Un rol personalizado existente necesita acceso y no está documentado.
- El CPT tiene endpoints fuera del archivo que omiten las capacidades del core.
- La migración elimina capacidades no creadas por este plugin.
- Una verificación falla dos veces.

## Notas de mantenimiento

- Cada cambio futuro en la lista de caps debe incrementar
  `culturinfo_ads_cap_version`.
- Revisar especialmente REST y acciones masivas en el PR.
- No confundir “editor” de WordPress con cualquier autor de noticias.
