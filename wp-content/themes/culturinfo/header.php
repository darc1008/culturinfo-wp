<?php
/**
 * Encabezado del sitio.
 *
 * @package Culturinfo
 */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<a class="skip-link screen-reader-text" href="#main-content"><?php esc_html_e('Saltar al contenido', 'culturinfo'); ?></a>

<header class="site-header">
    <div class="utility-bar">
        <div class="site-shell utility-inner">
            <div class="utility-edition">
                <span class="live-dot" aria-hidden="true"></span>
                <span><?php echo esc_html(wp_date('l, j \d\e F \d\e Y')); ?></span>
            </div>
            <div class="utility-links">
                <span><?php esc_html_e('Una publicación de Horizonte Cultural', 'culturinfo'); ?></span>
                <a href="<?php echo esc_url(home_url('/contacto/')); ?>"><?php esc_html_e('Contacto', 'culturinfo'); ?></a>
            </div>
        </div>
    </div>

    <div class="masthead">
        <div class="site-shell masthead-inner">
            <p class="masthead-note"><?php esc_html_e('Ideas que amplían el horizonte', 'culturinfo'); ?></p>
            <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" rel="home" aria-label="<?php esc_attr_e('Culturinfo — Inicio', 'culturinfo'); ?>">
                <img src="<?php echo esc_url(culturinfo_logo_url()); ?>" alt="<?php echo esc_attr(get_bloginfo('name')); ?>">
            </a>
            <div class="masthead-actions">
                <button class="icon-button search-toggle" type="button" aria-expanded="false" aria-controls="site-search" aria-label="<?php esc_attr_e('Abrir búsqueda', 'culturinfo'); ?>">⌕</button>
                <a class="subscribe-link" href="#boletin"><?php esc_html_e('Recibir novedades', 'culturinfo'); ?></a>
            </div>
        </div>
    </div>

    <nav class="main-navigation" aria-label="<?php esc_attr_e('Navegación principal', 'culturinfo'); ?>">
        <div class="site-shell nav-inner">
            <a class="home-link" href="<?php echo esc_url(home_url('/')); ?>" aria-label="<?php esc_attr_e('Ir al inicio', 'culturinfo'); ?>">C</a>
            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="primary-menu" aria-label="<?php esc_attr_e('Abrir menú', 'culturinfo'); ?>">
                <span class="menu-toggle-lines" aria-hidden="true"></span>
            </button>
            <?php
            wp_nav_menu(array(
                'theme_location' => 'primary',
                'menu_id'        => 'primary-menu',
                'menu_class'     => 'primary-menu',
                'container'      => false,
                'fallback_cb'    => 'culturinfo_primary_menu_fallback',
                'depth'          => 1,
            ));
            ?>
        </div>
    </nav>

    <div id="site-search" class="search-panel">
        <div class="site-shell"><?php get_search_form(); ?></div>
    </div>
</header>
