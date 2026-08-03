<?php
/**
 * Resultados de búsqueda.
 *
 * @package Culturinfo
 */
get_header();
?>
<main id="main-content">
    <header class="archive-hero" data-number="⌕"><div class="site-shell"><div class="archive-kicker"><?php esc_html_e('Resultados de búsqueda', 'culturinfo'); ?></div><h1 class="archive-title"><?php printf(esc_html__('“%s”', 'culturinfo'), esc_html(get_search_query())); ?></h1></div></header>
    <div class="archive-content"><div class="site-shell">
        <?php if (have_posts()) : ?><div class="archive-grid">
            <?php while (have_posts()) : the_post(); ?>
                <article <?php post_class('archive-card'); ?>>
                    <?php if (has_post_thumbnail()) : ?><a class="archive-card-media" href="<?php the_permalink(); ?>"><?php the_post_thumbnail('culturinfo-card'); ?></a><?php endif; ?>
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <div class="card-meta"><?php echo esc_html(get_the_date()); ?></div>
                </article>
            <?php endwhile; ?>
            <div class="pagination"><?php the_posts_pagination(); ?></div>
        </div><?php else : ?><div class="empty-section"><div><strong><?php esc_html_e('No encontramos coincidencias.', 'culturinfo'); ?></strong><p><?php esc_html_e('Prueba con otras palabras o explora las secciones.', 'culturinfo'); ?></p></div></div><?php endif; ?>
    </div></div>
</main>
<?php get_footer(); ?>
