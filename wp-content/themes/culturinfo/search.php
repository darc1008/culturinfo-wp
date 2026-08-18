<?php
/**
 * Resultados de búsqueda.
 *
 * @package Culturinfo
 */
get_header();
$writer_results = function_exists('culturinfo_authors_search') ? culturinfo_authors_search(get_search_query(), 6) : array();
?>
<main id="main-content">
    <header class="archive-hero" data-number="⌕"><div class="site-shell"><div class="archive-kicker"><?php esc_html_e('Resultados de búsqueda', 'culturinfo'); ?></div><h1 class="archive-title"><?php printf(esc_html__('“%s”', 'culturinfo'), esc_html(get_search_query())); ?></h1></div></header>
    <div class="archive-content"><div class="site-shell">
        <?php if ($writer_results) : ?>
            <section class="search-writers" aria-labelledby="search-writers-title">
                <header class="section-heading"><span class="section-number">✦</span><div class="section-heading-main"><h2 id="search-writers-title" class="section-title"><?php esc_html_e('Escritores', 'culturinfo'); ?></h2></div></header>
                <div class="search-writer-grid">
                    <?php foreach ($writer_results as $writer) : ?>
                        <a class="search-writer-card" href="<?php echo esc_url($writer['url']); ?>">
                            <span class="search-writer-photo">
                                <?php if ($writer['photo_id']) : ?>
                                    <?php echo wp_get_attachment_image($writer['photo_id'], array(128, 128), false, array('alt' => $writer['name'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                                <?php else : ?>
                                    <span aria-hidden="true"><?php echo esc_html($writer['initials']); ?></span>
                                <?php endif; ?>
                            </span>
                            <span><strong><?php echo esc_html($writer['name']); ?></strong><small><?php esc_html_e('Ver perfil y publicaciones', 'culturinfo'); ?></small></span>
                        </a>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <?php if (have_posts()) : ?>
            <h2 class="search-articles-title"><?php esc_html_e('Publicaciones', 'culturinfo'); ?></h2>
            <div class="archive-grid search-article-grid">
            <?php while (have_posts()) : the_post(); ?>
                <article <?php post_class('archive-card'); ?>>
                    <?php if (has_post_thumbnail()) : ?><a class="archive-card-media" href="<?php the_permalink(); ?>"><?php the_post_thumbnail('culturinfo-card'); ?></a><?php endif; ?>
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <div class="card-meta"><?php echo esc_html(get_the_date()); ?></div>
                </article>
            <?php endwhile; ?>
            <div class="pagination"><?php the_posts_pagination(); ?></div>
            </div>
        <?php elseif (!$writer_results) : ?>
            <div class="empty-section"><div><strong><?php esc_html_e('No encontramos coincidencias.', 'culturinfo'); ?></strong><p><?php esc_html_e('Prueba con otras palabras o explora las secciones.', 'culturinfo'); ?></p></div></div>
        <?php endif; ?>
    </div></div>
</main>
<?php get_footer(); ?>
