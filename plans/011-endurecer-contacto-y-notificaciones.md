# Plan 011: Endurecer el formulario de contacto y sus notificaciones

> **Instrucciones para el ejecutor**: conservar todos los controles válidos del
> plugin actual y sustituir únicamente el limitador vulnerable a concurrencia.
> Las pruebas de envío se realizan con buzón/transport falso en local o clon.
>
> **Comprobación de deriva (primero)**:
> `git diff --stat 2b95226..HEAD -- wp-content/plugins/culturinfo-contact wp-content/plugins/culturinfo-security seed/configure_contact.php README.md`
> y `git status --short`.

## Estado

- **Prioridad**: P1
- **Esfuerzo**: M
- **Riesgo**: MED
- **Depende de**: 009
- **Categoría**: security/abuse
- **Planificado en**: `2b95226`, 2026-08-18

## Por qué importa

El formulario propio ya tiene nonce, honeypot, validación, máximo de tres envíos
por hora y no acepta adjuntos. Sin embargo, `get_transient` + `set_transient`
(`culturinfo-contact.php:149-156`) no incrementa atómicamente y el fingerprint
incluye user-agent, que un bot cambia con facilidad. Bots distribuidos también
pueden generar muchos posts privados y correos al administrador.

## Estado actual verificado

- Usa `REMOTE_ADDR` o cabecera Cloudflare y HMAC (`:140-146`).
- Límite declarado: 3/hora, implementado con transients (`:149-156`).
- Form POST con nonce y honeypot (`:177-188`).
- Valida longitudes, correo, tipo y consentimiento (`:206-226`).
- Guarda mensaje privado y después llama `wp_mail` inmediatamente (`:229-247`).
- Limpieza a 90 días y capacidades propias para admin/editor.
- No hay `<input type=file>`, media handler ni endpoint de adjuntos.

## Alcance

**Dentro**: `culturinfo-contact`, APIs de `culturinfo-security`, estilos mínimos,
README, pruebas y regla Cloudflare.

**Fuera**: adjuntos, CRM, newsletter, CAPTCHA pago, borrar mensajes existentes o
convertir el formulario en Contact Form 7.

## Implementación

### Paso 1: sustituir fingerprint y transient

Eliminar `culturinfo_contact_fingerprint()` y el get/set de transients. Reutilizar
la identidad HMAC derivada solo de la IP validada por el plan 009; no incluir
user-agent. Añadir un bucket separado por HMAC del correo normalizado después de
validarlo.

Aplicar atómicamente:

- 5 intentos por identidad en 15 minutos;
- 3 mensajes aceptados por identidad en una hora;
- 5 mensajes por correo en 24 horas;
- 100 mensajes aceptados globales por 24 horas como circuit breaker inicial.

Los límites deben ser filtros configurables. El límite global devuelve mensaje
de indisponibilidad temporal sin revelar umbrales; no inserta ni envía correo.
No contar como “aceptado” lo que falla validación o challenge.

### Paso 2: integrar Turnstile y conservar defensas existentes

Renderizar Turnstile con action `culturinfo_contact` junto al botón y validarlo
en servidor con el helper común. Orden del handler:

1. Método POST y tamaño total razonable.
2. Nonce.
3. Honeypot (éxito silencioso).
4. Precheck del límite de intentos.
5. Turnstile: success, action y hostname.
6. Saneamiento/validación actual.
7. Límites de aceptados por identidad, correo y global.
8. Inserción privada.
9. Notificación presupuestada.

Mantener longitudes actuales y `wp_safe_redirect`. No loguear cuerpo, correo,
nombre, nonce, token ni respuesta completa de Turnstile.

### Paso 3: desacoplar aceptación de la entrega de correo

El post privado debe ser la fuente de verdad. Si `wp_mail` falla o el presupuesto
se agota, el usuario sigue viendo “enviado” porque el mensaje quedó guardado.
Guardar meta de estado: `pending`, `sent`, `failed` o `suppressed`; nunca el error
completo si contiene PII.

Usar un presupuesto global compartido de máximo 20 notificaciones/hora. Al
agotarse, marcar `suppressed`, mostrar aviso agregado a admin/editor y programar
un resumen único; no reintentar en bucle ni enviar 100 correos al recuperarse.
Proteger cualquier acción “reenviar” con capacidad, nonce y el mismo presupuesto.

Si no existe transporte SMTP confiable, documentar que revisar **Mensajes** es el
canal canónico. No instalar un relay externo ni añadir credenciales a Git.

### Paso 4: impedir sobrecarga de almacenamiento

Conservar limpieza a 90 días y verificar que cron existe/está programado. Añadir
un índice/consulta eficiente para conteo global y no ejecutar un `COUNT(*)` de
posts completos en cada POST. Incluir en admin conteos agregados por estado de
notificación y edad; solo admin/editor pueden ver nombre/correo/contenido.

Al llegar al circuit breaker, rechazar nuevos mensajes temporalmente; no borrar
ni sobrescribir mensajes previos. Alertar una sola vez por ventana.

### Paso 5: reforzar el borde

Crear regla gratuita de Cloudflare para POST a
`/wp-admin/admin-post.php` cuando action sea la del contacto, si el producto
permite inspeccionar el campo; si no, limitar el path con umbral suficientemente
alto para no afectar acciones administrativas. Preferir una ruta REST dedicada
solo si puede conservar nonce, CORS cerrado al mismo origen y todas las pruebas.

No cachear el formulario, redirecciones de estado ni respuestas POST. Documentar
la expresión exacta y verificar IP real detrás de Cloudflare.

### Paso 6: mantener explícitamente “sin adjuntos”

Conservar `enctype` normal y rechazar cualquier `$_FILES` no vacío antes de
procesar. Añadir test que envíe multipart con archivo y espere rechazo sin crear
attachment, mensaje ni archivo temporal persistente. README y texto del formulario
deben decir que no admite adjuntos y ofrecer un proceso editorial aparte si el
cliente lo requiere en el futuro.

## Pruebas

- Mensaje válido → post privado + estado de notificación esperado.
- Nonce ausente, honeypot, Turnstile inválido/action/hostname/timeout.
- Longitudes límite, correo inválido, tipo desconocido, falta de consentimiento.
- Sexto intento/15 min, cuarto aceptado/hora, correo diario y límite global.
- Carga paralela no supera silenciosamente umbrales.
- Rotar user-agent no cambia identidad de límite.
- `wp_mail=false` conserva mensaje y marca `failed`.
- Presupuesto agotado conserva mensajes y no envía más correos.
- Multipart/`$_FILES` se rechaza y no deja adjunto/archivo.
- Limpieza de 90 días, capacidades y redirección same-origin permanecen.

## Criterios de finalización

- [ ] No queda limitador get/set de transients en contacto.
- [ ] Límites por identidad/correo/global son atómicos.
- [ ] Turnstile servidor está activo en producción.
- [ ] El correo no determina si el mensaje se conserva.
- [ ] Existe presupuesto de notificaciones y observabilidad agregada.
- [ ] Retención a 90 días y acceso admin/editor siguen vigentes.
- [ ] Cualquier adjunto se rechaza explícitamente.
- [ ] Regla Cloudflare está probada y documentada.
- [ ] No hay PII/secreto adicional en logs o tabla antiabuso.

## Condiciones de parada

- El servicio común del plan 009 no es atómico o expone PII.
- El hosting no permite verificar Turnstile desde servidor.
- El cliente exige adjuntos: requiere un plan independiente de cuarentena/AV.
- El límite global bloquea tráfico legítimo medido.
- El transporte de correo solo puede configurarse exponiendo credenciales.

## Notas de mantenimiento

Revisar semanalmente suppressed/failed y volumen durante el primer mes. Ajustar
umbrales con datos, conservar la bandeja privada como fuente de verdad y probar el
cron de retención después de cada cambio de programación.
