# Planes de implementación

Generados con la skill `improve` el 2026-08-03. Ejecutar en el orden indicado,
salvo que las dependencias digan lo contrario. Cada ejecutor debe leer el plan
completo, respetar sus condiciones de parada y actualizar su fila al terminar.

La línea base es el commit `aa2da49`, pero el árbol de trabajo ya estaba sucio
cuando se redactaron estos planes. Los cambios existentes pertenecen al usuario:
ningún ejecutor debe descartarlos, restaurarlos ni sobrescribirlos.

## Orden y estado

| Plan | Título | Prioridad | Esfuerzo | Depende de | Estado |
|------|--------|-----------|----------|------------|--------|
| 001 | Retirar credenciales embebidas y rotarlas | P1 | M | — | TODO |
| 002 | Corregir HTTPS detrás del proxy | P1 | M | 001 | TODO |
| 003 | Migrar a WordPress 7.0.2 | P1 | L | 001, 002 | TODO |
| 004 | Desactivar el editor de archivos y endurecer permisos | P1 | M | 001, 003 | TODO |
| 005 | Restringir la administración de anuncios | P1 | M | 003 | TODO |
| 006 | Hacer confiable la importación de imágenes destacadas | P1 | M | 003 | TODO |
| 007 | Configurar metadatos SEO con Rank Math | P1 | M | 002, 003, 006 | TODO |

Valores de estado: `TODO`, `IN PROGRESS`, `DONE`, `BLOCKED (motivo)` o
`REJECTED (motivo)`.

## Notas de dependencia

- 002 se ejecuta después de 001 porque ambos modifican `entrypoint.sh`; así se
  evita resolver dos veces el mismo bloque de generación de `wp-config.php`.
- 003 exige 001 y 002 para no desplegar WordPress 7 con secretos conocidos ni
  con URLs HTTPS defectuosas.
- 004 se realiza después de 003 porque la actualización necesita escribir el
  core y los plugins durante la construcción/puesta en marcha.
- 005 se valida sobre WordPress 7.0.2, que será la plataforma soportada.
- 006 se valida sobre la imagen final de WordPress 7.0.2.
- 007 necesita HTTPS correcto y medios destacados funcionales para validar
  canonicales, Open Graph, Twitter Cards y `NewsArticle` con URLs definitivas.

## Hallazgos considerados y no convertidos en planes

- `sitemap_index.xml` devuelve 404: no es un defecto por sí mismo; WordPress
  core publica el índice nativo en `/wp-sitemap.xml` y Rank Math puede cambiar
  la ruta cuando termine su configuración.
- `xmlrpc.php` responde: no se propone desactivarlo sin confirmar antes si se
  usarán Jetpack, la app móvil o publicación remota.
- Falta de cabeceras HSTS/CSP: se difiere hasta saber si las controla Coolify,
  el proxy frontal o Apache; duplicarlas en dos capas puede causar incidentes.
