# Planes de implementación

Índice reconciliado el 2026-08-18 contra el commit `2b95226`. Los planes 008 a
012 convierten la revisión de seguridad solicitada para Culturinfo en trabajo
ejecutable. Cada ejecutor debe leer el plan completo, ejecutar primero su control
de deriva y actualizar su fila al terminar.

El árbol de trabajo contiene un cambio preexistente en `.env.example`; pertenece
al usuario y ningún plan autoriza descartarlo, restaurarlo ni incluirlo en un
commit por accidente.

## Orden y estado

| Plan | Título | Prioridad | Esfuerzo | Depende de | Estado |
|------|--------|-----------|----------|------------|--------|
| 001 | Retirar credenciales embebidas y rotarlas | P1 | M | — | BLOCKED (código corregido; falta confirmar rotación real de producción) |
| 002 | Corregir HTTPS detrás del proxy | P1 | M | 001 | REJECTED (resuelto independientemente en `configure_proxy.php` y el seed) |
| 003 | Migrar a WordPress 7.0.2 | P1 | L | 001, 002 | REJECTED (supersedido por 008 tras deriva sustancial) |
| 004 | Desactivar el editor de archivos y endurecer permisos | P1 | M | 001, 003 | REJECTED (supersedido por 012) |
| 005 | Restringir la administración de anuncios | P1 | M | 003 | REJECTED (resuelto independientemente: capacidades propias y migración versionada) |
| 006 | Hacer confiable la importación de imágenes destacadas | P1 | M | 008 | TODO |
| 007 | Configurar metadatos SEO con Rank Math | P1 | M | 002, 003, 006 | REJECTED (resuelto independientemente por configuración idempotente) |
| 008 | Migrar WordPress persistente a 7.0.2 | P1 | L | — | BLOCKED (implementación y smoke local listos; falta respaldo/clon y despliegue autorizado) |
| 009 | Proteger login, habilitar 2FA y centralizar controles antiabuso | P1 | L | 008 | BLOCKED (código probado; faltan claves Turnstile, enrolamiento 2FA y reglas Cloudflare) |
| 010 | Proteger comentarios públicos contra spam masivo | P1 | M | 009 | BLOCKED (código probado; falta Turnstile/regla Cloudflare en producción) |
| 011 | Endurecer el formulario de contacto y sus notificaciones | P1 | M | 009 | BLOCKED (código probado; falta Turnstile/regla Cloudflare en producción) |
| 012 | Endurecer cargas, ejecución de archivos y permisos web | P1 | L | 008, 009 | BLOCKED (smoke local listo; falta validar contra los volúmenes de producción) |

Valores de estado: `TODO`, `IN PROGRESS`, `DONE`, `BLOCKED (motivo)` o
`REJECTED (motivo)`.

## Orden recomendado

1. Ejecutar 008 sobre un clon restaurable antes de tocar producción.
2. Ejecutar 009: crea la base común de limitación y Turnstile.
3. Ejecutar 010 y 011; pueden desarrollarse en paralelo después de 009.
4. Ejecutar 012 y validar que audio e imágenes siguen escribiendo en uploads.
5. Ejecutar 006 después de 008; no es parte del top cinco de seguridad, pero su
   fallo silencioso sigue vigente.

La fila 001 no debe darse por cerrada solo porque el código ya no contiene una
contraseña predeterminada. El operador debe confirmar que cualquier credencial
que haya estado expuesta fue rotada en MariaDB, Coolify y `wp-config.php` de
forma coordinada.

## Decisiones y hallazgos no convertidos en un plan separado

- Cloudflare debe complementar, no sustituir, los controles de WordPress. Las
  reglas externas concretas están incluidas en 009, 010 y 011.
- Contact Form 7 aparece activo en producción pero no forma parte del seed ni del
  formulario propio. El plan 008 exige inventariarlo y solo retirarlo después de
  comprobar que ningún contenido lo utiliza; no se autoriza borrarlo a ciegas.
- Akismet no se considera una defensa activa mientras no exista una clave válida
  y una licencia compatible con el uso del sitio. El plan 010 hace explícito su
  comportamiento sin depender de una suscripción.
- No se propone antivirus de archivos dentro del contenedor: ClamAV aumentaría
  de forma material imagen, memoria y mantenimiento. El plan 012 reduce el riesgo
  con lista permitida, validación, límites, permisos y bloqueo de ejecución.
- HSTS y una CSP completa se difieren hasta confirmar si Cloudflare, Coolify o
  Apache es la única capa responsable. Duplicarlas sin inventario puede causar
  bloqueos y no responde directamente a los tres vectores solicitados.
- No se hicieron intentos de login ni envíos de formularios sobre producción
  durante el análisis.
