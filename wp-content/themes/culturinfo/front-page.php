<?php
/**
 * Portada editorial de Culturinfo.
 *
 * @package Culturinfo
 */
get_header();

$lead_query = new WP_Query(array(
    'posts_per_page'      => 3,
    'post_status'         => 'publish',
    'ignore_sticky_posts' => false,
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
            <section class="lead-grid" aria-label="<?php esc_attr_e('Historias destacadas', 'culturinfo'); ?>">
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
                            <span><?php echo esc_html(get_the_author_meta('display_name', $lead->post_author)); ?></span>
                            <span><?php echo esc_html(get_the_date('j \d\e F, Y', $lead)); ?></span>
                            <span><?php echo esc_html(culturinfo_reading_time($lead->ID)); ?> min de lectura</span>
                        </div>
                    </div>
                </article>

                <div class="lead-aside">
                    <?php foreach (array_slice($lead_posts, 1, 2) as $side_post) :
                        $side_categories = get_the_category($side_post->ID);
                    ?>
                        <article class="side-story">
                            <?php if (has_post_thumbnail($side_post)) : ?>
                                <?php echo get_the_post_thumbnail($side_post, 'culturinfo-card'); ?>
                            <?php else : ?>
                                <span class="placeholder-media"></span>
                            <?php endif; ?>
                            <div class="side-content">
                                <?php if ($side_categories) : ?><span class="story-kicker"><?php echo esc_html($side_categories[0]->name); ?></span><?php endif; ?>
                                <h2 class="side-title"><a href="<?php echo esc_url(get_permalink($side_post)); ?>"><?php echo esc_html(get_the_title($side_post)); ?></a></h2>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php else : ?>
            <section class="lead-grid" aria-label="<?php esc_attr_e('Presentación', 'culturinfo'); ?>">
                <article class="lead-story">
                    <span class="placeholder-media"></span>
                    <div class="lead-content">
                        <span class="story-kicker"><?php esc_html_e('Bienvenidos', 'culturinfo'); ?></span>
                        <h1 class="lead-title"><?php esc_html_e('Cultura, ideas y sociedad en un mismo horizonte', 'culturinfo'); ?></h1>
                        <p class="lead-excerpt"><?php esc_html_e('Culturinfo nace para compartir miradas plurales y conversaciones que dejan huella.', 'culturinfo'); ?></p>
                    </div>
                </article>
                <div class="lead-aside">
                    <article class="side-story"><span class="placeholder-media"></span><div class="side-content"><span class="story-kicker">Cultur + Info</span><h2 class="side-title"><?php esc_html_e('Próximamente, nuevas historias', 'culturinfo'); ?></h2></div></article>
                    <article class="side-story"><span class="placeholder-media"></span><div class="side-content"><span class="story-kicker"><?php esc_html_e('Horizonte Cultural', 'culturinfo'); ?></span><h2 class="side-title"><?php esc_html_e('Un periódico abierto a la comunidad', 'culturinfo'); ?></h2></div></article>
                </div>
            </section>
        <?php endif; wp_reset_postdata(); ?>
        <?php if (function_exists('culturinfo_ads_render')) { culturinfo_ads_render('home_after_lead'); } ?>

        <aside class="editorial-statement" aria-label="<?php esc_attr_e('Declaración editorial', 'culturinfo'); ?>">
            <span class="statement-mark" aria-hidden="true">“</span>
            <p><?php esc_html_e('La cultura no es un adorno: es la forma en que una comunidad se piensa, se cuenta y se transforma.', 'culturinfo'); ?></p>
            <span class="statement-label"><?php esc_html_e('Nuestra mirada', 'culturinfo'); ?></span>
        </aside>

        <div class="sections-index">
            <?php $section_position = 0; foreach (culturinfo_sections() as $slug => $section) :
                $section_position++;
                $term = get_category_by_slug($slug);
                $term_link = $term ? get_category_link($term->term_id) : home_url('/category/' . $slug . '/');
                $section_query = new WP_Query(array(
                    'posts_per_page'      => 4,
                    'post_status'         => 'publish',
                    'ignore_sticky_posts' => true,
                    'category_name'       => $slug,
                ));
                $section_posts = $section_query->posts;
            ?>
                <section class="section-block" style="--section-accent: <?php echo esc_attr($section['accent']); ?>" aria-labelledby="section-<?php echo esc_attr($slug); ?>">
                    <header class="section-heading">
                        <span class="section-number"><?php echo esc_html($section['number']); ?></span>
                        <div class="section-heading-main">
                            <h2 id="section-<?php echo esc_attr($slug); ?>" class="section-title"><?php echo esc_html($section['name']); ?></h2>
                            <span class="section-deck"><?php echo esc_html($section['description']); ?></span>
                        </div>
                        <a class="section-link" href="<?php echo esc_url($term_link); ?>"><?php esc_html_e('Ver sección', 'culturinfo'); ?></a>
                    </header>

                    <div class="section-content<?php echo count($section_posts) <= 1 ? ' is-solo' : ''; ?>">
                        <?php if (!empty($section_posts)) :
                            $feature = $section_posts[0];
                        ?>
                            <article class="section-feature">
                                <a class="section-feature-media" href="<?php echo esc_url(get_permalink($feature)); ?>" tabindex="-1">
                                    <?php if (has_post_thumbnail($feature)) : ?>
                                        <?php echo get_the_post_thumbnail($feature, 'culturinfo-card'); ?>
                                    <?php else : ?>
                                        <span class="placeholder-media"></span>
                                    <?php endif; ?>
                                </a>
                                <div class="section-feature-copy">
                                    <span class="card-category"><?php echo esc_html($section['name']); ?></span>
                                    <h3 class="section-feature-title"><a href="<?php echo esc_url(get_permalink($feature)); ?>"><?php echo esc_html(get_the_title($feature)); ?></a></h3>
                                    <p class="section-feature-excerpt"><?php echo esc_html(wp_trim_words(get_the_excerpt($feature), 24)); ?></p>
                                    <div class="card-meta"><?php echo esc_html(get_the_date('j \d\e F, Y', $feature)); ?> · <?php echo esc_html(culturinfo_reading_time($feature->ID)); ?> min</div>
                                </div>
                            </article>

                            <div class="section-list">
                                <?php foreach (array_slice($section_posts, 1, 3) as $list_post) : ?>
                                    <article class="list-story">
                                        <div>
                                            <span class="card-category"><?php echo esc_html($section['name']); ?></span>
                                            <h3 class="list-story-title"><a href="<?php echo esc_url(get_permalink($list_post)); ?>"><?php echo esc_html(get_the_title($list_post)); ?></a></h3>
                                            <div class="card-meta"><?php echo esc_html(get_the_date('j M, Y', $list_post)); ?></div>
                                        </div>
                                        <?php if (has_post_thumbnail($list_post)) : ?>
                                            <a class="list-story-thumb" href="<?php echo esc_url(get_permalink($list_post)); ?>" tabindex="-1"><?php echo get_the_post_thumbnail($list_post, 'culturinfo-thumb'); ?></a>
                                        <?php endif; ?>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        <?php else : ?>
                            <div class="empty-section">
                                <div><strong><?php echo esc_html($section['name']); ?></strong><p><?php echo esc_html($section['description']); ?></p></div>
                                <a class="section-link" href="<?php echo esc_url($term_link); ?>"><?php esc_html_e('Explorar', 'culturinfo'); ?></a>
                            </div>
                        <?php endif; wp_reset_postdata(); ?>
                    </div>
                </section>
                <?php
                if (function_exists('culturinfo_ads_render') && in_array($section_position, array(2, 4), true)) {
                    culturinfo_ads_render('home_between_sections_' . $section_position);
                }
                ?>
            <?php endforeach; ?>
        </div>

        <?php if (function_exists('culturinfo_ads_render')) { culturinfo_ads_render('home_before_footer'); } ?>
        <section id="boletin" class="newsletter">
            <div class="newsletter-inner">
                <div>
                    <span class="newsletter-kicker"><?php esc_html_e('El horizonte en tu correo', 'culturinfo'); ?></span>
                    <h2><?php esc_html_e('Lecturas que merecen un poco más de tiempo.', 'culturinfo'); ?></h2>
                    <p><?php esc_html_e('Una selección periódica de historias, ideas y cultura. Sin ruido.', 'culturinfo'); ?></p>
                </div>
                <div class="newsletter-form">
                    <a class="newsletter-button" href="<?php echo esc_url(home_url('/contacto/')); ?>"><?php esc_html_e('Quiero recibir novedades', 'culturinfo'); ?></a>
                </div>
            </div>
        </section>
    </div>
</main>
<?php get_footer(); ?>
