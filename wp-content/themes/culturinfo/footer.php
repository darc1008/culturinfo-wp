<?php
/**
 * Pie del sitio.
 *
 * @package Culturinfo
 */
?>
<footer class="site-footer">
    <div class="site-shell footer-main">
        <div class="footer-brand">
            <a href="<?php echo esc_url(home_url('/')); ?>">
                <img src="<?php echo esc_url(culturinfo_logo_url()); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
            </a>
            <p><?php esc_html_e('Periódico digital de Horizonte Cultural. Un espacio plural para las ideas, el arte, la educación y la vida en sociedad.', 'culturinfo'); ?></p>
        </div>
        <div>
            <h2 class="footer-heading"><?php esc_html_e('Secciones', 'culturinfo'); ?></h2>
            <ul class="footer-links">
                <?php foreach (culturinfo_sections() as $slug => $section) :
                    $term = get_category_by_slug($slug);
                    $url = $term ? get_category_link($term->term_id) : home_url('/category/' . $slug . '/');
                ?>
                    <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($section['name']); ?></a></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <div>
            <h2 class="footer-heading"><?php esc_html_e('Horizonte Cultural', 'culturinfo'); ?></h2>
            <div class="footer-contact">
                <p><?php esc_html_e('Cultura que conecta. Pensamiento que transforma.', 'culturinfo'); ?></p>
                <p><a href="<?php echo esc_url(home_url('/contacto/')); ?>"><?php esc_html_e('Contacto editorial', 'culturinfo'); ?> →</a></p>
            </div>
        </div>
    </div>
    <div class="site-shell footer-bottom">
        <span>© <?php echo esc_html(wp_date('Y')); ?> Culturinfo · <?php esc_html_e('Todos los derechos reservados', 'culturinfo'); ?></span>
        <span><?php esc_html_e('Periódico digital de Horizonte Cultural', 'culturinfo'); ?></span>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
