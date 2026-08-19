# Plan 010: Proteger comentarios públicos contra spam masivo

> **Instrucciones para el ejecutor**: depende de las APIs del plugin creado en
> 009. Si sus nombres o contratos cambiaron, detente y actualiza este plan antes
> de duplicar un segundo limitador. No enviar comentarios de prueba a producción.
>
> **Comprobación de deriva (primero)**:
> `git diff --stat 2b95226..HEAD -- seed/seed.sh README.md wp-content/themes/culturinfo wp-content/plugins/culturinfo-security`
> y `git status --short`.

## Estado

- **Prioridad**: P1
- **Esfuerzo**: M
- **Riesgo**: MED
- **Depende de**: 009
- **Categoría**: security/abuse
- **Planificado en**: `2b95226`, 2026-08-18

## Por qué importa

Los comentarios están abiertos, se moderan y exigen nombre/correo, pero no tienen
challenge ni un límite robusto. WordPress aplica controles básicos de duplicado y
flood corto; un bot distribuido todavía puede llenar la cola de moderación y
disparar correo. La solución debe conservar comentarios públicos sin suscripción
y sin convertir Akismet sin clave en una defensa ficticia.

## Estado actual verificado

- `seed/seed.sh:76-88` abre comentarios, modera todo, exige nombre/correo, limita
  a un enlace y cierra conversaciones después de 60 días.
- El tema retira el campo URL y desactiva pingbacks
  (`wp-content/themes/culturinfo/functions.php:193-213`).
- No existe Turnstile ni rate limit en `wp-comments-post.php`.
- Akismet se instala/activa siempre (`seed/seed.sh:145`) pero el repo no configura
  clave; no debe asumirse que filtra.
- El formulario de comentarios no admite adjuntos.

## Alcance

**Dentro**: módulo de comentarios de `culturinfo-security`, `seed/seed.sh`,
`README.md`, estilos mínimos del tema y reglas Cloudflare documentadas.

**Fuera**: comentarios con cuentas obligatorias, carga de archivos, suscripción
Akismet, moderación automática por IA o publicación automática de comentarios.

## Implementación

### Paso 1: mantener una única capa de seguridad

Implementar hooks de seguridad en `culturinfo-security`, no en el tema. El tema
solo mantiene presentación. Reutilizar el resolvedor de identidad, contador
atómico y verificador Turnstile del plan 009.

Renderizar Turnstile en `comment_form_after_fields` y
`comment_form_logged_in_after` con action `culturinfo_comment`. Validar en servidor
antes de crear el comentario. Verificar success, action y hostname; token ausente,
reutilizado, expirado o de otro action debe devolver un error claro sin eco de
datos enviados.

Eximir únicamente a usuarios autenticados con `moderate_comments`; no eximir por
cookies de comentarista ni por user-agent.

### Paso 2: límites atómicos por capas

Antes de `wp_new_comment`, aplicar:

- 5 intentos por identidad en 15 minutos;
- 20 intentos por identidad en 24 horas;
- 5 intentos por HMAC de correo en una hora.

Contar intentos después de validar estructura mínima y antes de insertar; los
rechazos de Turnstile no crean comentarios. Normalizar correo y usar HMAC, nunca
guardar el correo adicionalmente en la tabla de límites. Responder 429 con
`Retry-After` al exceder, sin indicar qué bucket se activó.

Conservar el filtro nativo de duplicados, el flood de core, moderación obligatoria
y máximo de un enlace. Un comentario permitido debe quedar `hold`, nunca
publicado automáticamente por este módulo.

### Paso 3: honeypot accesible y reducción de notificaciones

Añadir un campo trampa fuera del flujo visual pero no oculto mediante un patrón
que perjudique lectores de pantalla: etiqueta y `aria-hidden`, `tabindex=-1` y
autocomplete desactivado. Si se llena, responder éxito genérico sin insertar ni
enviar notificaciones; registrar solo un contador agregado.

Revisar `comments_notify` y `moderation_notify` con el cliente. Por defecto,
mantener una única notificación de moderación y aplicar un presupuesto global de
correo (por ejemplo 20/hora) compartido con contacto; al agotarse, los comentarios
siguen en la cola y se muestra aviso a editores. No perder comentarios ni mandar
un correo por cada bot.

### Paso 4: hacer honesto el estado de Akismet

Cambiar el seed:

- Si existe una clave Akismet válida y su uso/licencia fue confirmado, instalar
  y activar sin exponerla.
- Si no existe clave, no instalarlo en instalaciones nuevas y no describirlo como
  protección activa.
- En instalaciones existentes sin clave, registrar el estado y desactivarlo solo
  después de confirmar que no hay configuración manual útil; nunca borrar datos.

Turnstile + rate limit + moderación deben bastar sin Akismet. Documentar que una
clave personal gratuita no debe usarse si el sitio no cumple sus condiciones.

### Paso 5: reforzar Cloudflare sin romper el formulario

Crear regla gratuita de rate limiting para POST a `/wp-comments-post.php`, con un
umbral más alto que el de aplicación para absorber ráfagas. Excluir GET y no
cachear respuestas. Confirmar que Cloudflare no elimina cookies/nonce ni cambia
el POST. La regla es defensa adicional, no el único control.

### Paso 6: experiencia de usuario y observabilidad

Mostrar mensajes en español para challenge vencido y límite alcanzado, conservando
nombre/comentario solo en el navegador cuando sea seguro. Añadir en admin métricas
agregadas de permitidos, bloqueados por límite y rechazados por challenge; sin IP,
correo, token ni contenido. Documentar cómo distinguir spam, falsa alarma y caída
de Turnstile.

## Pruebas

- Comentario válido anónimo → queda pendiente.
- Moderador autenticado → funciona sin challenge y con nonce/capacidad normal.
- Turnstile ausente, inválido, reutilizado, action/hostname incorrectos y timeout.
- Sexto intento/15 min y límite diario → 429 + `Retry-After`.
- Mismo correo desde identidades distintas activa bucket de correo.
- Concurrencia no permite sobrepasar silenciosamente los límites.
- Honeypot lleno no crea comentario ni correo.
- HTML/JS, muchos enlaces y duplicado siguen bloqueados/saneados por core.
- Comentario legítimo no se publica automáticamente.
- No hay adjunto ni endpoint de upload en el formulario.

## Criterios de finalización

- [ ] Todo comentario anónimo exige Turnstile válido en producción.
- [ ] Límites atómicos por identidad/correo están activos.
- [ ] Moderación, enlace máximo y cierre a 60 días se conservan.
- [ ] Honeypot no perjudica accesibilidad ni genera datos basura.
- [ ] Notificaciones tienen presupuesto y la cola conserva mensajes.
- [ ] Akismet no figura activo sin clave/licencia confirmadas.
- [ ] Regla Cloudflare probada y documentada.
- [ ] No se almacena PII adicional en telemetría de abuso.

## Condiciones de parada

- El plan 009 no expone servicios comunes estables.
- Turnstile bloquea usuarios legítimos detrás de la red objetivo.
- La capa de caché entrega tokens/widgets inválidos.
- Reducir notificaciones requiere una decisión editorial no obtenida.
- La licencia/configuración Akismet existente no puede determinarse.

## Notas de mantenimiento

Revisar falsos positivos y cola semanalmente durante el primer mes. Ajustar
umbrales con métricas agregadas, no desactivar Turnstile ni moderación por una
sola incidencia.
