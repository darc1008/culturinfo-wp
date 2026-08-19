# Culturinfo — Periódico Digital

WordPress autoalojado para **Culturinfo**, periódico digital de Horizonte Cultural.

## Identidad editorial

El repositorio incluye el tema propio **Culturinfo Editorial**, diseñado a partir del logo azul y naranja del proyecto. La portada organiza el contenido en seis áreas y cada una dispone de una página de archivo dedicada:

- Con Palabras
- Arte Plural
- Reflexiones Filo-lingüísticas
- Ánfora Cultura
- Ventana Social
- Aula Abierta

El tema se encuentra en `wp-content/themes/culturinfo` y se sincroniza al volumen persistente de WordPress durante cada arranque.

## Gestión de anuncios

El plugin propio `Culturinfo — Gestor de anuncios` agrega la opción **Anuncios** al panel de WordPress. Cada anuncio puede incluir una imagen, contenido, enlace, prioridad y fecha de vencimiento.

El editor elige su ubicación desde una lista de espacios disponibles:

- Portada: antes o después de las noticias destacadas, entre secciones y antes del bloque final.
- Secciones: después del encabezado, después de la noticia destacada o al final del listado.
- Noticias: después del titular, dentro del contenido, al terminar el artículo o en la columna lateral.

Los anuncios de secciones y noticias pueden mostrarse en todas las páginas del tipo elegido o limitarse a una sección o artículo concreto. Los espacios sin anuncios publicados no generan huecos visibles.

## Autores editoriales

El plugin `Culturinfo — Autores editoriales` añade **Autores** al panel. Cada
perfil admite nombre, fotografía destacada, biografía y enlaces a Facebook,
Instagram, X/Twitter, LinkedIn, YouTube y sitio web. Al editar una noticia, el
bloque **Autor de la noticia** permite elegir al escritor independientemente del
usuario que inició sesión o publicó el contenido.

En la noticia se muestra una firma compacta con foto junto al titular y una
ficha biográfica completa con sus redes al terminar el contenido. Las entradas
anteriores que aún no tengan escritor seleccionado usan el usuario de WordPress
asignado como respaldo.

La portada y las páginas de sección muestran el mismo escritor editorial, de
modo que la firma es consistente dentro y fuera de la noticia.

Cada autor publicado dispone además de una página en `/autor/nombre/` con foto,
biografía, redes y todas sus publicaciones. La búsqueda general muestra perfiles
de escritores coincidentes además de noticias.

## Comentarios y moderación

Las noticias incluyen una conversación pública basada en comentarios nativos de
WordPress. Cada comentario nuevo queda pendiente hasta que un editor lo aprueba;
se exige nombre y correo, se limita el número de enlaces, se ofrece consentimiento
para la cookie del comentarista y las conversaciones se cierran después de 60
días. Los pingbacks y trackbacks están desactivados para reducir spam.

Los editores pueden abrir o cerrar comentarios por noticia y moderarlos desde
**Comentarios** en el panel. El correo del lector nunca se muestra públicamente.
El módulo de seguridad limita por visitante y correo los intentos concurrentes;
cuando Turnstile está configurado, todo comentario anónimo debe superar además
el challenge gratuito de Cloudflare.

## Seguridad y protección antiabuso

El plugin propio `Culturinfo — Seguridad` protege el login, comentarios, contacto
y cargas sin servicios de pago. Los contadores se incrementan atómicamente en la
base de datos y almacenan únicamente identificadores HMAC, nunca direcciones IP,
usuarios o correos en claro. El login bloquea temporalmente intentos repetidos por
usuario/visitante y de forma global por visitante; los mensajes de error no
confirman si una cuenta existe.

Cloudflare Turnstile es opcional hasta configurar en Coolify
`CULTURINFO_TURNSTILE_SITE_KEY`, `CULTURINFO_TURNSTILE_SECRET_KEY` y
`CULTURINFO_TURNSTILE_ENABLED=true`. Sin esas claves siguen activos los límites
locales, pero no aparece el challenge. Las claves pertenecen al gestor de secretos
y nunca deben guardarse en Git.

El seed instala el plugin gratuito **Two-Factor**. Cada administrador y editor
debe entrar en su perfil, enrolar una aplicación TOTP y guardar sus códigos de
recuperación fuera de WordPress. Este paso no puede automatizarse porque generaría
y expondría el secreto personal. Antes de exigir 2FA debe existir un segundo
administrador de recuperación probado.

XML-RPC permanece deshabilitado por defecto. Solo debe habilitarse con
`CULTURINFO_XMLRPC_ENABLED=true` si existe una integración documentada, por
ejemplo Jetpack o una app móvil. En Cloudflare conviene añadir Managed Challenge
y rate limiting para `/wp-login.php`, `/xmlrpc.php`, `/wp-comments-post.php` y
los POST del formulario de contacto; son una segunda capa, no sustituyen los
controles del plugin.

## Lectura de noticias en voz alta

El plugin propio `Culturinfo — Audio de noticias` genera un MP3 con Piper cuando
una noticia se publica o cambia. La voz `es_MX-claude-high` se ejecuta dentro del
contenedor y el archivo se guarda en
`wp-content/uploads/culturinfo-audio/año/mes`, por lo que no depende de las
voces instaladas en el navegador ni de una API o suscripción externa.

El procesamiento ocurre en segundo plano mediante una cola que atiende una
noticia por ciclo. Mientras termina, la publicación muestra **Audio en
preparación** y después lo reemplaza automáticamente por un reproductor HTML5
con pausa, búsqueda y velocidades entre 0.75× y 2×. Al modificar el título,
resumen o contenido se crea una versión nueva y se elimina la anterior después
de completar la sustitución.

El panel de edición muestra el estado del audio, el enlace al MP3, posibles
errores y una opción para regenerarlo manualmente. La lista de noticias añade
también una columna **Audio**. El modelo utiliza un conjunto de datos con
licencia Apache-2.0 y su ficha se conserva en `licenses/` y dentro de la imagen.

## Estadísticas

El plugin propio `Culturinfo — Estadísticas editoriales` añade un panel de
**Estadísticas** en WordPress con visitas, visitantes únicos diarios, noticias
más leídas, escritores, secciones, fuentes de tráfico, dispositivos y países
proporcionados por Cloudflare. También mide la profundidad y el tiempo activo
de lectura, compara cada período con el anterior y registra impresiones, clics
y CTR de cada anuncio cuando este llega a ser visible en pantalla. El reporte
publicitario puede descargarse como CSV desde el mismo panel.

Para identificar países, el registro DNS debe estar en modo proxy (nube
naranja) y Cloudflare debe enviar `CF-IPCountry` mediante **IP Geolocation** o
el Managed Transform de ubicación. Si la cabecera no está disponible, la visita
se registra como país desconocido sin afectar las demás métricas.

Los datos se guardan en la misma base de datos de WordPress. No se almacena la
dirección IP: se utiliza un identificador irreversible que cambia diariamente
y se elimina después de 91 días. Las visitas de usuarios conectados y robots
conocidos no se contabilizan. Independent Analytics se instala como complemento
gratuito para análisis generales más detallados; ninguna parte requiere una
suscripción.

Los eventos públicos de lectura y publicidad se deduplican y limitan por ese
identificador temporal para reducir recargas accidentales y manipulación básica
de cifras sin convertir la dirección IP en un dato almacenado.

Cuando ya existen datos suficientes, la portada muestra **Más leídas** con las
cuatro noticias de mayor lectura de los últimos siete días. El cálculo se guarda
en caché durante diez minutos para no recargar la base de datos.

## SEO y vistas previas al compartir

Rank Math SEO se configura automáticamente en cada arranque, sin conectar una
cuenta externa. La portada, secciones y noticias generan título, descripción,
canonical, Open Graph, Twitter Card y datos estructurados. Para que WhatsApp,
Facebook y otras redes muestren la miniatura de una noticia, basta con asignarle
una **imagen destacada** en WordPress; Rank Math la publica como `og:image`.

## Programación editorial

El plugin propio `Culturinfo — Programación editorial` añade el menú
**Programación** al panel. Un editor puede elegir el día y la hora semanal por
defecto para publicar en bloque todas las noticias guardadas como borrador, y
configurar excepciones para secciones concretas. El proceso revisa los horarios
cada 15 minutos con la zona horaria de WordPress, conserva un historial y ofrece
una ejecución manual protegida por confirmación.

Antes de entrar en la programación, un administrador o editor debe marcar la
noticia como **Lista para publicación automática** desde el bloque **Revisión
editorial**. Los borradores sin aprobación permanecen intactos, incluso durante
una ejecución manual. Si un autor modifica después un borrador aprobado, la
aprobación se retira y debe revisarse nuevamente.

La automatización se instala desactivada para no publicar borradores existentes
por sorpresa. Al guardar o cambiar un calendario comienza un ciclo nuevo; si el
sitio no estaba disponible a la hora prevista, la ejecución pendiente se realiza
en cuanto WordPress vuelve a procesar tareas programadas.

La programación y la aprobación usan una capacidad propia concedida solamente a
administradores y editores. El gestor de anuncios también utiliza capacidades
separadas: autores, colaboradores y suscriptores no pueden crear ni modificar
publicidad aunque puedan escribir noticias.

## Contacto editorial

El plugin propio `Culturinfo — Contacto editorial` convierte la página
**Contacto** en un canal para mensajes generales, propuestas, correcciones,
publicidad y colaboraciones. No admite adjuntos ni solicita cuentas al lector.
Cada envío se guarda como mensaje privado en WordPress y se intenta notificar al
correo administrador; por ello el equipo puede revisarlo aun cuando el servidor
de correo no entregue la notificación.

El formulario permite tres mensajes aceptados por hora y visitante, limita
intentos, correo y volumen global, e incluye campo trampa, validación,
consentimiento y redirección restringida al sitio. Un presupuesto compartido
evita avalanchas de correo: si se agota o `wp_mail` falla, el mensaje permanece
guardado y muestra su estado en **Mensajes**. Los mensajes privados pasan a la
papelera después de 90 días. Solo administradores y editores pueden acceder.

## Stack

- WordPress 7.0.2 (PHP 8.3 + Apache)
- MariaDB
- Piper 1.7 + voz neuronal española y LAME
- Tema: Culturinfo Editorial
- WP-CLI para instalación y carga idempotente
- Plugins: Two-Factor, Classic Editor, Rank Math SEO e Independent Analytics

## Despliegue en Coolify

1. Crear una aplicación desde el repositorio.
2. Elegir `Dockerfile` como método de construcción.
3. Configurar el dominio, por ejemplo `culturinfo.statusloop.app`.
4. Crear volúmenes persistentes:
   - `/var/lib/mysql` para la base de datos.
   - `/var/www/html` para WordPress.
   - `/backups` para los respaldos automáticos.
5. Copiar las variables de `.env.example` y definir contraseñas seguras.
6. Crear un widget gratuito de Cloudflare Turnstile para el dominio, guardar sus
   dos claves en Coolify y cambiar `CULTURINFO_TURNSTILE_ENABLED=true`.
7. Desplegar. El contenedor instala o migra WordPress, activa el tema, crea las
   secciones y configura el menú automáticamente.

## Respaldos automáticos

El contenedor puede crear diariamente un paquete restaurable sin interrumpir el
sitio. Cada paquete contiene una exportación transaccional comprimida de
MariaDB, un archivo de los datos persistentes de WordPress, un manifiesto y
checksums SHA-256. Los archivos temporales no se publican como respaldos válidos
y una ejecución concurrente queda bloqueada.

En Coolify se debe crear un tercer **Volume Mount** con destino `/backups` y
después configurar:

```env
CULTURINFO_BACKUPS_ENABLED=true
CULTURINFO_BACKUP_HOUR=3
CULTURINFO_BACKUP_RETENTION_DAYS=30
TZ=America/Santo_Domingo
```

La hora utiliza `TZ`; el trabajador comprueba cada cinco minutos y reintenta si
una ejecución falla. La retención admite entre 1 y 365 días. Si `/backups` no es
un volumen real, el proceso rechaza la ejecución para evitar que una copia
efímera dé una falsa sensación de seguridad.

Cuando el volumen persistente conserva una versión anterior de WordPress, el
primer despliegue crea obligatoriamente un respaldo completo antes de modificar
el core o actualizar la base de datos. La migración se cancela si los respaldos
no están habilitados o `/backups` no está realmente montado, de modo que una
configuración incompleta no actualice producción sin punto de recuperación.
Si la base todavía no contiene una instalación, el contenedor conserva
`wp-content` y `wp-config.php`, sustituye únicamente el core antiguo por el core
7.0.2 de la imagen y deja que el seed complete la instalación inicial.

Se puede crear una copia manual desde la terminal del contenedor con:

```bash
/usr/local/bin/culturinfo-backup.sh
```

El volumen de respaldo protege ante despliegues o daños en los dos volúmenes de
la aplicación, pero no ante la pérdida completa del servidor. Para recuperación
ante desastres se debe descargar o sincronizar periódicamente `/backups` hacia
otro equipo o almacenamiento S3 compatible.

El modelo de voz forma parte de la imagen y no necesita un volumen adicional.
Los MP3 sí permanecen dentro del volumen de WordPress en
`wp-content/uploads/culturinfo-audio`. El trabajador comprueba la cola cada 15
segundos; el intervalo puede cambiarse con `CULTURINFO_AUDIO_WORKER_INTERVAL`,
sin usar valores menores de 5 segundos.

`MARIADB_PASSWORD` es obligatoria en todos los arranques y
`WP_ADMIN_PASSWORD` lo es durante la primera instalación. La imagen no contiene
contraseñas predeterminadas: deben generarse como valores aleatorios y guardarse
en el gestor de variables secretas de Coolify, nunca en Git.

En una instalación existente, antes de redesplegar se debe cargar en Coolify la
contraseña que actualmente usa el usuario de la base de datos. Retirar el valor
del código no rota por sí solo una credencial ya creada; la rotación de MariaDB y
la actualización de `wp-config.php` deben realizarse juntas durante una ventana
de mantenimiento y después invalidar las sesiones administrativas si hubo
posible exposición.

La aplicación está preparada para ejecutarse detrás del proxy HTTPS de
Cloudflare/Coolify. En producción debe mantenerse `TRUST_PROXY_HEADERS=true`;
en una instalación expuesta directamente sin proxy se debe usar `false`.
El idioma del sitio se instala y activa desde `WP_LOCALE`; el valor recomendado
para Culturinfo es `es_ES`.

El core se incluye de forma inmutable en la imagen. Si el volumen conserva una
versión anterior, el entrypoint ejecuta la actualización desde el ZIP local
antes del seed; no depende de descargar WordPress durante el arranque.

Después del seed, core, temas, plugins y `wp-config.php` quedan bajo propietario
root y no son escribibles por Apache. `DISALLOW_FILE_EDIT` y las modificaciones
web están activadas; las actualizaciones entran por una nueva imagen/WP-CLI. Solo
`wp-content/uploads` queda en `www-data`, con directorios `775` y archivos `664`.
Apache bloquea ejecución de PHP/scripts y listado dentro de uploads.

Las cargas manuales están limitadas a administradores y editores, 100 archivos
por usuario/día, 15 MB y 36 megapíxeles. Se aceptan JPEG, PNG, GIF, WebP y AVIF;
SVG, PDF, ejecutables, audio y video manual se rechazan. Los MP3 creados por Piper
son internos y continúan escribiéndose directamente en uploads. Contacto y
comentarios no aceptan adjuntos.

## Archivos principales

```text
wp-content/themes/culturinfo/  Tema editorial a medida
seed/seed.sh                   Inicialización idempotente
seed/articles/                 Contenido inicial de demostración
entrypoint.sh                  Arranque de MariaDB, WordPress y Apache
Dockerfile                     Imagen de producción
```

## Administración

El panel se encuentra en `/wp-admin/` dentro del dominio configurado.
