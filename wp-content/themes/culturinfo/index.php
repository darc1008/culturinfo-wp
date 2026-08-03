<?php
/**
 * Plantilla de respaldo.
 *
 * @package Culturinfo
 */
get_header();
?>
<main id="main-content" class="archive-content">
    <div class="site-shell">
        <header class="section-heading">
            <span class="section-number">CI</span>
            <div class="section-heading-main">
                <h1 class="section-title"><?php echo is_home() ? esc_html__('Últimas publicaciones', 'culturinfo') : esc_html(get_the_archive_title()); ?></h1>
            </div>
        </header>
        <?php if (have_posts()) : ?>
            <div class="archive-grid">
                <?php while (have_posts()) : the_post(); ?>
                    <article <?php post_class('archive-card'); ?>>
                        <?php if (has_post_thumbnail()) : ?>
                            <a class="archive-card-media" href="<?php the_permalink(); ?>"><?php the_post_thumbnail('culturinfo-card'); ?></a>
                        <?php endif; ?>
                        <?php $category = culturinfo_first_category(); ?>
                        <?php if ($category) : ?><a class="card-category" href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a><?php endif; ?>
                        <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <div class="card-meta"><?php echo esc_html(get_the_date()); ?></div>
                    </article>
                <?php endwhile; ?>
                <div class="pagination"><?php the_posts_pagination(array('mid_size' => 2, 'prev_text' => '←', 'next_text' => '→')); ?></div>
            </div>
        <?php else : ?>
            <div class="empty-section"><div><strong><?php esc_html_e('Todavía no hay publicaciones.', 'culturinfo'); ?></strong><p><?php esc_html_e('Muy pronto encontrarás nuevas historias en este espacio.', 'culturinfo'); ?></p></div></div>
        <?php endif; ?>
    </div>
</main>
<?php get_footer(); ?>
