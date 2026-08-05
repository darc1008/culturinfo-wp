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
    $author_id = (int) get_the_author_meta('ID');
    $author_name = $author_id > 0 ? get_the_author_meta('display_name', $author_id) : get_bloginfo('name');
    $author_url = $author_id > 0 ? get_author_posts_url($author_id) : home_url('/');
?>
    <article <?php post_class(); ?>>
        <header class="article-header">
            <div class="site-shell article-header-inner">
                <div class="breadcrumbs"><a href="<?php echo esc_url(home_url('/')); ?>"><?php esc_html_e('Portada', 'culturinfo'); ?></a><?php if ($category) : ?><span>/</span><a href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a><?php endif; ?></div>
                <?php if ($category) : ?><a class="article-section" href="<?php echo esc_url(get_category_link($category)); ?>"><?php echo esc_html($category->name); ?></a><?php endif; ?>
                <h1 class="article-title"><?php the_title(); ?></h1>
                <?php if (has_excerpt()) : ?><p class="article-deck"><?php echo esc_html(get_the_excerpt()); ?></p><?php endif; ?>
                <div class="article-byline">
                    <span class="article-author">
                        <?php esc_html_e('Escrito por', 'culturinfo'); ?>
                        <a href="<?php echo esc_url($author_url); ?>" rel="author"><?php echo esc_html($author_name); ?></a>
                    </span>
                    <span>·</span>
                    <time datetime="<?php echo esc_attr(get_the_date('c')); ?>"><?php echo esc_html(get_the_date('j \d\e F, Y')); ?></time>
                    <span>·</span>
                    <span><?php echo esc_html(culturinfo_reading_time()); ?> <?php esc_html_e('min de lectura', 'culturinfo'); ?></span>
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
