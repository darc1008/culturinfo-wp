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
