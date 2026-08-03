<?php
/**
 * Archivo general.
 *
 * @package Culturinfo
 */
get_header();
?>
<main id="main-content">
    <header class="archive-hero" data-number="CI">
        <div class="site-shell">
            <div class="archive-kicker"><?php esc_html_e('Archivo', 'culturinfo'); ?></div>
            <h1 class="archive-title"><?php the_archive_title(); ?></h1>
            <?php the_archive_description('<div class="archive-description">', '</div>'); ?>
        </div>
    </header>
    <div class="archive-content"><div class="site-shell">
        <?php if (have_posts()) : ?><div class="archive-grid">
            <?php while (have_posts()) : the_post(); ?>
                <article <?php post_class('archive-card'); ?>>
                    <?php if (has_post_thumbnail()) : ?><a class="archive-card-media" href="<?php the_permalink(); ?>"><?php the_post_thumbnail('culturinfo-card'); ?></a><?php endif; ?>
                    <?php $category = culturinfo_first_category(); if ($category) : ?><a class="card-category" href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a><?php endif; ?>
                    <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                    <div class="card-meta"><?php echo esc_html(get_the_date()); ?></div>
                </article>
            <?php endwhile; ?>
            <div class="pagination"><?php the_posts_pagination(); ?></div>
        </div><?php else : ?><div class="empty-section"><p><?php esc_html_e('No encontramos publicaciones.', 'culturinfo'); ?></p></div><?php endif; ?>
    </div></div>
</main>
<?php get_footer(); ?>
