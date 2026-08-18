<?php
/**
 * Perfil público de un escritor editorial.
 *
 * @package Culturinfo
 */
get_header();
?>
<main id="main-content">
<?php while (have_posts()) : the_post();
    $writer_id = get_the_ID();
    $profile = function_exists('culturinfo_authors_get_writer_profile') ? culturinfo_authors_get_writer_profile($writer_id) : null;
    if (!$profile) {
        continue;
    }
    $paged = max(1, absint(get_query_var('paged')));
    $articles = new WP_Query(array(
        'post_type'           => 'post',
        'post_status'         => 'publish',
        'posts_per_page'      => 9,
        'paged'               => $paged,
        'ignore_sticky_posts' => true,
        'meta_key'            => '_culturinfo_writer_id',
        'meta_value'          => $writer_id,
    ));
?>
    <header class="writer-hero">
        <div class="site-shell writer-hero-inner">
            <div class="writer-portrait">
                <?php if ($profile['photo_id']) : ?>
                    <?php echo wp_get_attachment_image($profile['photo_id'], array(320, 320), false, array('alt' => $profile['name'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                <?php else : ?>
                    <span aria-hidden="true"><?php echo esc_html($profile['initials']); ?></span>
                <?php endif; ?>
            </div>
            <div class="writer-intro">
                <span class="archive-kicker"><?php esc_html_e('Escritor de Culturinfo', 'culturinfo'); ?></span>
                <h1><?php echo esc_html($profile['name']); ?></h1>
                <?php if (trim($profile['bio'])) : ?><div class="writer-bio"><?php echo wp_kses_post(wpautop($profile['bio'])); ?></div><?php endif; ?>
                <?php if ($profile['socials']) : ?>
                    <div class="writer-socials" aria-label="<?php esc_attr_e('Redes del escritor', 'culturinfo'); ?>">
                        <?php foreach ($profile['socials'] as $social) : ?>
                            <a href="<?php echo esc_url($social['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($social['label']); ?></a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </header>

    <section class="writer-articles">
        <div class="site-shell">
            <header class="section-heading">
                <span class="section-number">✦</span>
                <div class="section-heading-main"><h2 class="section-title"><?php esc_html_e('Sus publicaciones', 'culturinfo'); ?></h2></div>
            </header>
            <?php if ($articles->have_posts()) : ?>
                <div class="archive-grid writer-article-grid">
                    <?php while ($articles->have_posts()) : $articles->the_post(); $category = culturinfo_first_category(); ?>
                        <article <?php post_class('archive-card'); ?>>
                            <?php if (has_post_thumbnail()) : ?><a class="archive-card-media" href="<?php the_permalink(); ?>"><?php the_post_thumbnail('culturinfo-card'); ?></a><?php endif; ?>
                            <?php if ($category) : ?><a class="card-category" href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a><?php endif; ?>
                            <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                            <div class="card-meta"><?php echo esc_html(get_the_date()); ?> · <?php echo esc_html(culturinfo_reading_time()); ?> min</div>
                        </article>
                    <?php endwhile; ?>
                    <div class="pagination">
                        <?php echo wp_kses_post(paginate_links(array('total' => $articles->max_num_pages, 'current' => $paged, 'type' => 'list'))); ?>
                    </div>
                </div>
            <?php else : ?>
                <div class="empty-section"><div><strong><?php esc_html_e('Todavía no hay publicaciones disponibles.', 'culturinfo'); ?></strong></div></div>
            <?php endif; wp_reset_postdata(); ?>
        </div>
    </section>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
