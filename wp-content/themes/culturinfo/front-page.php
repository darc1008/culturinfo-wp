<?php
/**
 * Portada editorial de Culturinfo.
 *
 * @package Culturinfo
 */
get_header();

$lead_query = new WP_Query(array(
    'posts_per_page'      => 1,
    'post_status'         => 'publish',
    'ignore_sticky_posts' => true,
    'orderby'             => array('date' => 'DESC', 'ID' => 'DESC'),
));
$lead_posts = $lead_query->posts;
?>
<main id="main-content" class="front-main">
    <div class="site-shell">
        <div class="news-intro"><?php esc_html_e('Historias para comprender nuestro tiempo', 'culturinfo'); ?></div>
        <?php if (function_exists('culturinfo_ads_render')) { culturinfo_ads_render('home_after_header'); } ?>

        <?php if (!empty($lead_posts)) :
            $lead = $lead_posts[0];
            $lead_category = get_the_category($lead->ID);
        ?>
            <section class="lead-grid is-single" aria-label="<?php esc_attr_e('Noticia destacada', 'culturinfo'); ?>">
                <article class="lead-story">
                    <a class="lead-media" href="<?php echo esc_url(get_permalink($lead)); ?>" tabindex="-1" aria-hidden="true">
                        <?php if (has_post_thumbnail($lead)) : ?>
                            <?php echo get_the_post_thumbnail($lead, 'culturinfo-lead'); ?>
                        <?php else : ?>
                            <span class="placeholder-media"></span>
                        <?php endif; ?>
                    </a>
                    <div class="lead-content">
                        <?php if ($lead_category) : ?><span class="story-kicker"><?php echo esc_html($lead_category[0]->name); ?></span><?php endif; ?>
                        <h1 class="lead-title"><a href="<?php echo esc_url(get_permalink($lead)); ?>"><?php echo esc_html(get_the_title($lead)); ?></a></h1>
                        <p class="lead-excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt($lead), 28)); ?></p>
                        <div class="story-meta">
                            <span><?php echo esc_html(culturinfo_editorial_author_name($lead->ID)); ?></span>
                            <span><?php echo esc_html(get_the_date('j \d\e F, Y', $lead)); ?></span>
                            <span><?php echo esc_html(culturinfo_reading_time($lead->ID)); ?> min de lectura</span>
                        </div>
                    </div>
                </article>

            </section>
        <?php else : ?>
            <section class="lead-grid is-single" aria-label="<?php esc_attr_e('Presentación', 'culturinfo'); ?>">
                <article class="lead-story">
                    <span class="placeholder-media"></span>
                    <div class="lead-content">
                        <span class="story-kicker"><?php esc_html_e('Bienvenidos', 'culturinfo'); ?></span>
                        <h1 class="lead-title"><?php esc_html_e('Cultura, ideas y sociedad en un mismo horizonte', 'culturinfo'); ?></h1>
                        <p class="lead-excerpt"><?php esc_html_e('Culturinfo nace para compartir miradas plurales y conversaciones que dejan huella.', 'culturinfo'); ?></p>
                    </div>
                </article>
            </section>
        <?php endif; wp_reset_postdata(); ?>
        <?php if (function_exists('culturinfo_ads_render')) { culturinfo_ads_render('home_after_lead'); } ?>

        <?php
        $news_page_value = isset($_GET['noticias']) && is_scalar($_GET['noticias']) ? wp_unslash($_GET['noticias']) : 1;
        $news_page = max(1, absint($news_page_value));
        $recent_args = array(
            'post_type'           => 'post',
            'post_status'         => 'publish',
            'posts_per_page'      => 9,
            'paged'               => $news_page,
            'post__not_in'       => wp_list_pluck($lead_posts, 'ID'),
            'ignore_sticky_posts' => true,
            'orderby'            => array('date' => 'DESC', 'ID' => 'DESC'),
        );
        $recent_query = new WP_Query($recent_args);
        ?>
        <?php if ($recent_query->found_posts) : ?>
            <section id="ultimas-noticias" class="recent-stories" aria-labelledby="recent-stories-title">
                <header class="section-heading">
                    <span class="section-number">CI</span>
                    <div class="section-heading-main">
                        <h2 id="recent-stories-title" class="section-title"><?php esc_html_e('Últimas noticias', 'culturinfo'); ?></h2>
                        <span class="section-deck"><?php esc_html_e('Todas las publicaciones, de la más reciente a la más antigua.', 'culturinfo'); ?></span>
                    </div>
                </header>
                <?php if ($recent_query->have_posts()) : ?>
                    <div class="recent-grid">
                        <?php foreach ($recent_query->posts as $recent_post) :
                            $recent_categories = get_the_category($recent_post->ID);
                        ?>
                            <article class="recent-card">
                                <?php if (has_post_thumbnail($recent_post)) : ?>
                                    <a class="recent-card-media" href="<?php echo esc_url(get_permalink($recent_post)); ?>" tabindex="-1" aria-hidden="true">
                                        <?php echo get_the_post_thumbnail($recent_post, 'culturinfo-card'); ?>
                                    </a>
                                <?php endif; ?>
                                <?php if ($recent_categories) : ?><span class="card-category"><?php echo esc_html($recent_categories[0]->name); ?></span><?php endif; ?>
                                <h3><a href="<?php echo esc_url(get_permalink($recent_post)); ?>"><?php echo esc_html(get_the_title($recent_post)); ?></a></h3>
                                <div class="card-meta"><?php echo esc_html(get_the_date('j \d\e F, Y', $recent_post)); ?> · <?php echo esc_html(culturinfo_editorial_author_name($recent_post->ID)); ?></div>
                            </article>
                        <?php endforeach; ?>
                    </div>
                    <?php if ($recent_query->max_num_pages > 1) : ?>
                        <nav class="pagination" aria-label="<?php esc_attr_e('Páginas de noticias recientes', 'culturinfo'); ?>">
                            <div class="nav-links">
                                <?php echo paginate_links(array(
                                    'base'      => trailingslashit(home_url('/')) . '?noticias=%#%#ultimas-noticias',
                                    'format'    => '',
                                    'current'   => $news_page,
                                    'total'     => $recent_query->max_num_pages,
                                    'prev_text' => __('← Noticias más recientes', 'culturinfo'),
                                    'next_text' => __('Noticias anteriores →', 'culturinfo'),
                                )); ?>
                            </div>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </section>
        <?php endif; wp_reset_postdata(); ?>
        <?php if (function_exists('culturinfo_ads_render')) { culturinfo_ads_render('home_between_sections_2'); } ?>

        <aside class="editorial-statement" aria-label="<?php esc_attr_e('Declaración editorial', 'culturinfo'); ?>">
            <span class="statement-mark" aria-hidden="true">“</span>
            <p><?php esc_html_e('La cultura no es un adorno: es la forma en que una comunidad se piensa, se cuenta y se transforma.', 'culturinfo'); ?></p>
            <span class="statement-label"><?php esc_html_e('Nuestra mirada', 'culturinfo'); ?></span>
        </aside>

        <?php
        $popular_ids = function_exists('culturinfo_stats_popular_post_ids') ? culturinfo_stats_popular_post_ids(7, 4) : array();
        $popular_posts = $popular_ids ? get_posts(array(
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 4,
            'post__in'       => $popular_ids,
            'orderby'        => 'post__in',
        )) : array();
        if ($popular_posts) :
        ?>
            <section class="popular-stories" aria-labelledby="popular-stories-title">
                <header class="section-heading">
                    <span class="section-number">↗</span>
                    <div class="section-heading-main">
                        <h2 id="popular-stories-title" class="section-title"><?php esc_html_e('Más leídas', 'culturinfo'); ?></h2>
                        <span class="section-deck"><?php esc_html_e('Las historias que marcaron la conversación esta semana.', 'culturinfo'); ?></span>
                    </div>
                </header>
                <div class="popular-grid">
                    <?php foreach ($popular_posts as $position => $popular_post) : $popular_category = get_the_category($popular_post->ID); ?>
                        <article class="popular-card">
                            <span class="popular-rank"><?php echo esc_html(str_pad((string) ($position + 1), 2, '0', STR_PAD_LEFT)); ?></span>
                            <div>
                                <?php if ($popular_category) : ?><span class="card-category"><?php echo esc_html($popular_category[0]->name); ?></span><?php endif; ?>
                                <h3><a href="<?php echo esc_url(get_permalink($popular_post)); ?>"><?php echo esc_html(get_the_title($popular_post)); ?></a></h3>
                                <div class="card-meta"><?php echo esc_html(culturinfo_editorial_author_name($popular_post->ID)); ?> · <?php echo esc_html(culturinfo_reading_time($popular_post->ID)); ?> min</div>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>
        <?php if (function_exists('culturinfo_ads_render')) { culturinfo_ads_render('home_between_sections_4'); } ?>

        <?php if (function_exists('culturinfo_ads_render')) { culturinfo_ads_render('home_before_footer'); } ?>
        <section id="participa" class="newsletter">
            <div class="newsletter-inner">
                <div>
                    <span class="newsletter-kicker"><?php esc_html_e('Tu mirada también cuenta', 'culturinfo'); ?></span>
                    <h2><?php esc_html_e('Conversemos sobre las historias que importan.', 'culturinfo'); ?></h2>
                    <p><?php esc_html_e('Propón una noticia, solicita una corrección o conversa con el equipo editorial.', 'culturinfo'); ?></p>
                </div>
                <div class="newsletter-form">
                    <a class="newsletter-button" href="<?php echo esc_url(home_url('/contacto/')); ?>"><?php esc_html_e('Contactar al equipo', 'culturinfo'); ?></a>
                </div>
            </div>
        </section>
    </div>
</main>
<?php get_footer(); ?>
