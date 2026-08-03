<?php
/**
 * Página dedicada de cada sección.
 *
 * @package Culturinfo
 */
get_header();

$category = get_queried_object();
$section = culturinfo_section_data($category->slug);
?>
<main id="main-content">
    <header class="archive-hero" style="--section-accent: <?php echo esc_attr($section['accent']); ?>" data-number="<?php echo esc_attr($section['number']); ?>">
        <div class="site-shell">
            <div class="archive-kicker"><?php esc_html_e('Sección', 'culturinfo'); ?> <?php echo esc_html($section['number']); ?></div>
            <h1 class="archive-title"><?php echo esc_html($section['name']); ?></h1>
            <div class="archive-description"><?php echo esc_html($section['description']); ?></div>
        </div>
    </header>
    <?php if (function_exists('culturinfo_ads_render')) { culturinfo_ads_render('section_after_header', $category->term_id); } ?>

    <div class="archive-content" style="--section-accent: <?php echo esc_attr($section['accent']); ?>">
        <div class="site-shell">
            <?php if (have_posts()) : the_post(); ?>
                <article class="archive-feature">
                    <a class="archive-feature-media" href="<?php the_permalink(); ?>" tabindex="-1">
                        <?php if (has_post_thumbnail()) : the_post_thumbnail('culturinfo-lead'); else : ?><span class="placeholder-media"></span><?php endif; ?>
                    </a>
                    <div>
                        <span class="card-category"><?php echo esc_html($section['name']); ?> · <?php esc_html_e('Destacado', 'culturinfo'); ?></span>
                        <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                        <p><?php echo esc_html(wp_trim_words(get_the_excerpt(), 34)); ?></p>
                        <div class="card-meta"><?php culturinfo_posted_on(); ?> · <?php echo esc_html(culturinfo_reading_time()); ?> min</div>
                    </div>
                </article>
                <?php if (function_exists('culturinfo_ads_render')) { culturinfo_ads_render('section_after_feature', $category->term_id); } ?>

                <div class="archive-grid">
                    <?php while (have_posts()) : the_post(); ?>
                        <article <?php post_class('archive-card'); ?>>
                            <?php if (has_post_thumbnail()) : ?><a class="archive-card-media" href="<?php the_permalink(); ?>"><?php the_post_thumbnail('culturinfo-card'); ?></a><?php endif; ?>
                            <span class="card-category"><?php echo esc_html($section['name']); ?></span>
                            <h2><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
                            <div class="card-meta"><?php echo esc_html(get_the_date('j \d\e F, Y')); ?> · <?php echo esc_html(culturinfo_reading_time()); ?> min</div>
                        </article>
                    <?php endwhile; ?>
                    <div class="pagination"><?php the_posts_pagination(array('mid_size' => 2, 'prev_text' => '← Anterior', 'next_text' => 'Siguiente →')); ?></div>
                </div>
            <?php else : ?>
                <div class="empty-section">
                    <div><strong><?php esc_html_e('Estamos preparando esta sección.', 'culturinfo'); ?></strong><p><?php esc_html_e('Muy pronto encontrarás aquí nuevas historias y conversaciones.', 'culturinfo'); ?></p></div>
                    <a class="section-link" href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Volver a portada', 'culturinfo'); ?></a>
                </div>
            <?php endif; ?>
            <?php if (function_exists('culturinfo_ads_render')) { culturinfo_ads_render('section_before_footer', $category->term_id); } ?>
        </div>
    </div>
</main>
<?php get_footer(); ?>
