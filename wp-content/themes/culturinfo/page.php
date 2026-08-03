<?php
/**
 * Página estándar.
 *
 * @package Culturinfo
 */
get_header();
?>
<main id="main-content" class="page-wrap">
    <?php while (have_posts()) : the_post(); ?>
        <article <?php post_class(); ?>>
            <h1 class="page-title"><?php the_title(); ?></h1>
            <div class="entry-content"><?php the_content(); ?></div>
        </article>
    <?php endwhile; ?>
</main>
<?php get_footer(); ?>
