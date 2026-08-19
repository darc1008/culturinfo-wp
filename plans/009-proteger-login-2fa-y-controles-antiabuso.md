# Plan 009: Proteger login, habilitar 2FA y centralizar controles antiabuso

> **Instrucciones para el ejecutor**: este plan crea la base reutilizada por 010
> y 011. No pruebes credenciales ni ataques en producción. Toda prueba de bloqueo
> se realiza en contenedor/clon y con usuarios efímeros.
>
> **Comprobación de deriva (primero)**:
> `git diff --stat 2b95226..HEAD -- Dockerfile entrypoint.sh seed/seed.sh seed/configure_proxy.php README.md .env.example wp-content/plugins`
> y `git status --short`. Preserva el cambio preexistente de `.env.example`.

## Estado

- **Prioridad**: P1
- **Esfuerzo**: L
- **Riesgo**: HIGH
- **Depende de**: 008
- **Categoría**: security
- **Planificado en**: `2b95226`, 2026-08-18

## Por qué importa

WordPress no incorpora 2FA ni un bloqueo robusto de fuerza bruta por defecto. El
repo no contiene hooks de `wp_login_failed`, challenge en `/wp-login.php` ni una
política de XML-RPC. Cloudflare reduce tráfico, pero el origen y WordPress deben
tener su propio control para que un error de regla externa no deje el login sin
protección.

Este plan usa solo componentes gratuitos: un plugin propio pequeño, Cloudflare
Turnstile y el plugin comunitario `Two-Factor`. No requiere plan de pago.

## Estado actual verificado

- El tema solo retira métodos XML-RPC de pingback
  (`wp-content/themes/culturinfo/functions.php:193-207`); XML-RPC de autenticación
  permanece habilitado.
- `TRUST_PROXY_HEADERS` se usa para HTTPS, pero no existe un resolvedor común de
  IP confiable para controles de abuso.
- No hay limitación de intentos de login ni 2FA administrado por el seed.
- La producción está detrás de Cloudflare/Coolify; el origen directo debe
  comprobarse antes de confiar en `CF-Connecting-IP`.

## Alcance

**Dentro**:

- Nuevo `wp-content/plugins/culturinfo-security/**`.
- Copia/sincronización/activación en `Dockerfile`, `entrypoint.sh`, `seed/seed.sh`.
- `.env.example`, `README.md` y `seed/configure_proxy.php` si necesita exponer
  una constante booleana de confianza.
- Configuración operativa documentada de Cloudflare y usuarios 2FA.

**Fuera**: almacenar IP en claro, desarrollar criptografía TOTP propia, cambiar
contraseñas sin autorización, usar servicios pagos o bloquear editores legítimos
sin recuperación probada.

## Diseño objetivo

Crear `Culturinfo — Seguridad` con tres servicios públicos y estables:

1. `culturinfo_security_client_identity()`: devuelve solo un HMAC de la IP; usa
   `CF-Connecting-IP` únicamente si `TRUST_PROXY_HEADERS=true`, existen señales
   de Cloudflare y el origen no es accesible directamente.
2. `culturinfo_security_rate_limit($scope, $identity, $limit, $window)`: contador
   atómico persistente, usable por login, comentarios y contacto.
3. `culturinfo_security_verify_turnstile($token, $action)`: verificación servidor
   a servidor con timeout, hostname y action esperados, sin registrar token ni
   secreto.

## Implementación

### Paso 1: crear plugin y almacenamiento atómico

Registrar tabla con `dbDelta` y versión de esquema propia. Columnas mínimas:
`scope`, `identity_hash`, `window_start`, `request_count`, `blocked_until` y
`last_seen`; clave única por scope/identidad/ventana. Nunca guardar IP, username,
correo, user-agent o token sin hash. Hacer el incremento con una sola operación
`INSERT ... ON DUPLICATE KEY UPDATE`; un patrón get/set de transients no cumple
porque pierde incrementos concurrentes.

Usar HMAC SHA-256 con salts de WordPress y separación de dominio por scope. Añadir
limpieza diaria de buckets vencidos y hacerla idempotente. La activación debe
crear/actualizar tabla; el seed debe ejecutar la migración aunque el plugin ya
estuviera activo.

Sincronizar el plugin como los otros plugins propios, pero no ocultar errores de
activación con `|| true`.

### Paso 2: resolver correctamente la identidad del cliente

Validar IP con `filter_var`. Por defecto usar `REMOTE_ADDR`. Solo aceptar
`HTTP_CF_CONNECTING_IP` si la confianza de proxy está explícitamente activa y la
petición contiene la señal esperada del proxy. Documentar y comprobar en
Cloudflare/Coolify que el origen no responde desde Internet fuera del proxy; si
responde, cerrar el origen antes de activar confianza en esa cabecera.

Probar cabecera ausente, malformada, IPv4, IPv6 y spoof directo. El plugin nunca
debe devolver la IP cruda a logs, options, REST ni UI.

### Paso 3: limitar login en la aplicación

Antes de autenticar, consultar bloqueos; en `wp_login_failed` incrementar:

- combinación IP + usuario normalizado: 5 fallos en 15 minutos → 15 minutos;
- IP global: 20 fallos en una hora → una hora.

En login exitoso limpiar únicamente el bucket de la combinación exitosa; no el
bloque global producido por otros usuarios. El mensaje al navegador debe ser
genérico y no confirmar si un usuario existe. Añadir `Retry-After` cuando se
rechace por límite y mantener el mismo comportamiento visual del login.

Permitir filtros PHP para umbrales, pero usar estos defaults en producción. Las
pruebas deben cubrir concurrencia: N peticiones paralelas no pueden quedar por
debajo de N en el contador.

### Paso 4: integrar Turnstile sin convertirlo en punto único

Añadir variables sin valores reales:

```text
CULTURINFO_TURNSTILE_SITE_KEY=
CULTURINFO_TURNSTILE_SECRET_KEY=
CULTURINFO_TURNSTILE_ENABLED=true
```

Renderizar challenge en login y validar token contra el endpoint oficial con
timeout máximo de 5 s, action `culturinfo_login` y hostname equivalente al host
canónico del sitio. Claves configuradas + error/timeout debe fallar cerrado con
mensaje recuperable. En local solo se permite desactivar explícitamente; nunca
usar claves de prueba en producción.

No imprimir clave, respuesta completa ni token. Añadir un aviso administrativo
si `ENABLED=true` y faltan claves. Con Turnstile desactivado, el limitador de
login debe seguir funcionando.

### Paso 5: instalar 2FA probado y recuperar acceso

Instalar/activar idempotentemente el plugin WordPress.org `two-factor` en una
versión comprobada con WP 7.0.2. No implementar TOTP propio. En el clon:

1. Crear un segundo administrador de recuperación temporal.
2. Enrolar TOTP para administradores y editores reales.
3. Generar y guardar códigos de recuperación fuera de Git/Coolify logs.
4. Verificar login, código inválido y recuperación.
5. Inspeccionar la versión instalada del plugin para usar su mecanismo soportado
   de obligatoriedad; no adivinar nombres de opciones/meta.
6. Solo entonces exigir 2FA a `administrator` y `editor`, con período de gracia
   explícito y una vía de recuperación documentada por WP-CLI.

El seed no puede generar ni guardar secretos TOTP de usuarios. La fila permanece
BLOCKED si el operador no completa el enrolamiento.

### Paso 6: cerrar XML-RPC y reforzar el borde

Inventariar Jetpack, app móvil, clientes remotos y webhooks. Si ninguno depende
de XML-RPC, deshabilitar autenticación XML-RPC desde el plugin de seguridad y
retirar `X-Pingback`; REST y cron deben continuar. Si existe dependencia,
mantener solo los métodos documentados y aplicar el mismo rate limit.

En Cloudflare Free crear reglas explícitas y documentadas:

- Managed Challenge para `/wp-login.php` y `/xmlrpc.php`.
- Rate limit de solicitudes POST al login acorde con los umbrales de aplicación.
- No cachear login, admin, Turnstile ni respuestas autenticadas.

Exportar/capturar identificadores y expresiones de reglas, nunca secretos. Probar
con un usuario de clon y confirmar que la IP real llega correctamente.

## Pruebas y verificaciones

- Lint PHP de cada archivo del plugin y build de la imagen.
- Activación limpia y migración repetida sin alterar datos.
- 4 fallos permiten reintentar; el quinto bloquea; el tiempo libera el acceso.
- Ataques a múltiples usuarios disparan el bucket global.
- Un login válido no resetea bloqueos ajenos.
- Contador paralelo no pierde incrementos.
- Turnstile válido/ausente/caducado/action incorrecta/hostname incorrecto/timeout.
- 2FA válido, inválido y código de recuperación.
- XML-RPC bloqueado sin afectar REST ni WP-Cron.
- Cero IP, correo, usuario o secreto crudo en tabla y logs.

## Criterios de finalización

- [ ] Plugin común activo con migración y contador atómico.
- [ ] Login limitado por combinación e IP sin enumeración de usuarios.
- [ ] Turnstile funciona en producción y las claves solo viven en secretos.
- [ ] Todos los administradores/editores están enrolados y recuperación probada.
- [ ] XML-RPC está deshabilitado o reducido con dependencia documentada.
- [ ] Cloudflare y aplicación aplican defensas coherentes.
- [ ] Origen directo y cabeceras confiables fueron verificados.
- [ ] README explica operación, desbloqueo y rotación.

## Condiciones de parada

- El origen es accesible directamente y no puede cerrarse.
- Falta una segunda vía administrativa antes de exigir 2FA.
- Se detecta uso necesario de XML-RPC no contemplado.
- El plugin Two-Factor no demuestra compatibilidad con WP 7.0.2.
- El contador no es atómico o guarda PII cruda.
- Turnstile obliga a registrar tokens/secretos para diagnosticar.

## Notas de mantenimiento

Revisar bloqueos agregados, no identidades individuales. Mantener una instrucción
WP-CLI de recuperación probada y auditar trimestralmente administradores, 2FA,
reglas Cloudflare y crecimiento/limpieza de la tabla.
