<?php
/**
 * Artículo individual.
 *
 * @package Culturinfo
 */
get_header();
?>
<main id="main-content">
<?php while (have_posts()) : the_post();
    $category = culturinfo_first_category();
    if (function_exists('culturinfo_authors_get_article_author')) {
        $article_author = culturinfo_authors_get_article_author(get_the_ID());
    } else {
        $fallback_author_id = (int) get_the_author_meta('ID');
        $fallback_author_name = $fallback_author_id > 0 ? get_the_author_meta('display_name', $fallback_author_id) : get_bloginfo('name');
        $article_author = array(
            'name'       => $fallback_author_name,
            'url'        => $fallback_author_id > 0 ? get_author_posts_url($fallback_author_id) : home_url('/'),
            'anchor_id'  => 'autor-wordpress-' . $fallback_author_id,
            'photo_id'   => 0,
            'avatar_url' => $fallback_author_id > 0 ? get_avatar_url($fallback_author_id, array('size' => 192)) : '',
            'initials'   => strtoupper(substr($fallback_author_name, 0, 1)),
            'bio'        => $fallback_author_id > 0 ? get_the_author_meta('description', $fallback_author_id) : '',
            'socials'    => array(),
        );
    }
?>
    <article <?php post_class(); ?>>
        <header class="article-header">
            <div class="site-shell article-header-inner">
                <div class="breadcrumbs"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Portada', 'culturinfo'); ?></a><?php if ($category) : ?><span>/</span><a href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a><?php endif; ?></div>
                <?php if ($category) : ?><a class="article-section" href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a><?php endif; ?>
                <h1 class="article-title"><?php the_title(); ?></h1>
                <?php if (has_excerpt()) : ?><p class="article-deck"><?php echo esc_html(get_the_excerpt()); ?></p><?php endif; ?>
                <div class="article-byline">
                    <div class="article-author">
                        <a class="article-author-avatar" href="<?php echo esc_url($article_author['url']); ?>" aria-label="<?php echo esc_attr($article_author['name']); ?>">
                            <?php if (!empty($article_author['photo_id'])) : ?>
                                <?php echo wp_get_attachment_image($article_author['photo_id'], array(96, 96), false, array('alt' => $article_author['name'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                            <?php elseif (!empty($article_author['avatar_url'])) : ?>
                                <img src="<?php echo esc_url($article_author['avatar_url']); ?>" alt="<?php echo esc_attr($article_author['name']); ?>" width="52" height="52">
                            <?php else : ?>
                                <span aria-hidden="true"><?php echo esc_html($article_author['initials']); ?></span>
                            <?php endif; ?>
                        </a>
                        <span class="article-author-copy">
                            <span class="article-author-label"><?php esc_html_e('Escrito por', 'culturinfo'); ?></span>
                            <a class="article-author-name" href="<?php echo esc_url($article_author['url']); ?>" rel="author"><?php echo esc_html($article_author['name']); ?></a>
                        </span>
                    </div>
                    <div class="article-byline-details">
                        <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('j \d\e F, Y')); ?></time>
                        <span aria-hidden="true">·</span>
                        <span><?php echo esc_html(culturinfo_reading_time()); ?> <?php esc_html_e('min de lectura', 'culturinfo'); ?></span>
                    </div>
                </div>
            </div>
        </header>
        <?php if (function_exists('culturinfo_ads_render')) { culturinfo_ads_render('article_after_header', get_the_ID()); } ?>

        <?php if (has_post_thumbnail()) : the_post_thumbnail('culturinfo-lead', array('class' => 'article-hero-image')); endif; ?>

        <div class="article-layout">
            <aside class="share-rail" aria-label="<?php esc_attr_e('Compartir artículo', 'culturinfo'); ?>">
                <div class="share-label"><?php esc_html_e('Compartir', 'culturinfo'); ?></div>
                <div class="share-links">
                    <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo rawurlencode(get_permalink()); ?>" target="_blank" rel="noopener" aria-label="Facebook">f</a>
                    <a href="https://wa.me/?text=<?php echo rawurlencode(get_the_title() . ' ' . get_permalink()); ?>" target="_blank" rel="noopener" aria-label="WhatsApp">wa</a>
                    <a href="mailto:?subject=<?php echo rawurlencode(get_the_title()); ?>&body=<?php echo rawurlencode(get_permalink()); ?>" aria-label="<?php esc_attr_e('Enviar por correo', 'culturinfo'); ?>">@</a>
                </div>
            </aside>
            <div class="article-main-column">
                <div class="entry-content">
                    <?php the_content(); ?>
                    <?php wp_link_pages(); ?>
                </div>
                <?php if (function_exists('culturinfo_ads_render')) { culturinfo_ads_render('article_after_content', get_the_ID()); } ?>
                <section class="article-author-card" id="<?php echo esc_attr($article_author['anchor_id']); ?>" aria-labelledby="article-author-name">
                    <div class="article-author-card-photo">
                        <?php if (!empty($article_author['photo_id'])) : ?>
                            <?php echo wp_get_attachment_image($article_author['photo_id'], array(192, 192), false, array('alt' => $article_author['name'])); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                        <?php elseif (!empty($article_author['avatar_url'])) : ?>
                            <img src="<?php echo esc_url($article_author['avatar_url']); ?>" alt="<?php echo esc_attr($article_author['name']); ?>" width="112" height="112">
                        <?php else : ?>
                            <span aria-hidden="true"><?php echo esc_html($article_author['initials']); ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="article-author-card-content">
                        <div class="article-author-card-heading">
                            <h2 id="article-author-name"><?php echo esc_html($article_author['name']); ?></h2>
                            <?php if (!empty($article_author['socials'])) : ?>
                                <div class="article-author-socials" aria-label="<?php esc_attr_e('Redes del autor', 'culturinfo'); ?>">
                                    <?php foreach ($article_author['socials'] as $social) : ?>
                                        <a href="<?php echo esc_url($social['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($social['label']); ?>"><?php echo esc_html($social['short']); ?></a>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        <?php if (!empty(trim($article_author['bio']))) : ?>
                            <div class="article-author-bio"><?php echo wp_kses_post(wpautop($article_author['bio'])); ?></div>
                        <?php endif; ?>
                    </div>
                </section>
            </div>
            <aside class="article-aside">
                <?php if (function_exists('culturinfo_ads_render')) { culturinfo_ads_render('article_sidebar', get_the_ID()); } ?>
                <h2><?php esc_html_e('Sigue explorando', 'culturinfo'); ?></h2>
                <?php
                $related = new WP_Query(array(
                    'posts_per_page'      => 3,
                    'post__not_in'        => array(get_the_ID()),
                    'ignore_sticky_posts' => true,
                    'cat'                 => $category ? $category->term_id : 0,
                ));
                if ($related->have_posts()) : while ($related->have_posts()) : $related->the_post();
                ?>
                    <a href="<?php the_permalink(); ?>"><?php the_title(); ?></a>
                <?php endwhile; else : ?>
                    <a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Más historias en la portada', 'culturinfo'); ?></a>
                <?php endif; wp_reset_postdata(); ?>
            </aside>
        </div>
    </article>
<?php endwhile; ?>
</main>
<?php get_footer(); ?>
