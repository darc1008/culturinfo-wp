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

## SEO y vistas previas al compartir

Rank Math SEO se configura automáticamente en cada arranque, sin conectar una
cuenta externa. La portada, secciones y noticias generan título, descripción,
canonical, Open Graph, Twitter Card y datos estructurados. Para que WhatsApp,
Facebook y otras redes muestren la miniatura de una noticia, basta con asignarle
una **imagen destacada** en WordPress; Rank Math la publica como `og:image`.

## Stack

- WordPress 6.7 (PHP 8.3 + Apache)
- MariaDB
- Tema: Culturinfo Editorial
- WP-CLI para instalación y carga idempotente
- Plugins: Akismet, Contact Form 7, Classic Editor y Rank Math SEO

## Despliegue en Coolify

1. Crear una aplicación desde el repositorio.
2. Elegir `Dockerfile` como método de construcción.
3. Configurar el dominio, por ejemplo `culturinfo.statusloop.app`.
4. Crear volúmenes persistentes:
   - `/var/lib/mysql` para la base de datos.
   - `/var/www/html` para WordPress.
5. Copiar las variables de `.env.example` y definir contraseñas seguras.
6. Desplegar. El contenedor instala WordPress, activa el tema, crea las secciones y configura el menú automáticamente.

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
