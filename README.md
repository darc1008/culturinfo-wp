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

## Lectura de noticias en voz alta

Cada noticia incluye un reproductor con controles para escuchar, pausar,
continuar, detener y cambiar la velocidad. Utiliza la Web Speech API del
navegador, prioriza una voz `es-DO` o cualquier voz en español disponible y
divide automáticamente los artículos extensos para mejorar la estabilidad en
móviles. No genera archivos de audio, no consume espacio del servidor y no
requiere API, cuenta externa ni suscripción.

La voz concreta depende del navegador y del sistema operativo del visitante. El
script se carga únicamente en las noticias y excluye menús, anuncios,
comentarios, pies de foto, contenido relacionado y biografías del autor.

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

El formulario permite tres envíos por hora y visitante, incluye campo trampa,
validación, consentimiento y redirección restringida al sitio. Los mensajes
privados pasan a la papelera después de 90 días. Solo administradores y editores
pueden acceder al menú **Mensajes**.

## Stack

- WordPress 6.7 (PHP 8.3 + Apache)
- MariaDB
- Tema: Culturinfo Editorial
- WP-CLI para instalación y carga idempotente
- Plugins: Akismet, Classic Editor, Rank Math SEO e Independent Analytics

## Despliegue en Coolify

1. Crear una aplicación desde el repositorio.
2. Elegir `Dockerfile` como método de construcción.
3. Configurar el dominio, por ejemplo `culturinfo.statusloop.app`.
4. Crear volúmenes persistentes:
   - `/var/lib/mysql` para la base de datos.
   - `/var/www/html` para WordPress.
5. Copiar las variables de `.env.example` y definir contraseñas seguras.
6. Desplegar. El contenedor instala WordPress, activa el tema, crea las secciones y configura el menú automáticamente.

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

En cada arranque, el contenedor normaliza `wp-content/uploads` a propietario
`www-data` con directorios `775` y archivos `664`. Esto permite que el panel
cree las carpetas por año/mes y suba imágenes sin volver escribible el código.

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
