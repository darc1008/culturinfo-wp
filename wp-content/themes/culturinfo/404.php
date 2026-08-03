<?php
/**
 * Página no encontrada.
 *
 * @package Culturinfo
 */
get_header();
?>
<main id="main-content" class="page-wrap not-found">
    <p class="article-section"><?php esc_html_e('Página no encontrada', 'culturinfo'); ?></p>
    <h1 class="page-title">404</h1>
    <p><?php esc_html_e('Parece que esta historia cambió de página. Prueba con una búsqueda o vuelve a la portada.', 'culturinfo'); ?></p>
    <div style="margin-top: 30px"><?php get_search_form(); ?></div>
</main>
<?php get_footer(); ?>
