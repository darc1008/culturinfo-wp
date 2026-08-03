<?php
/**
 * Funciones principales del tema Culturinfo Editorial.
 *
 * @package Culturinfo
 */

if (!defined('ABSPATH')) {
    exit;
}

function culturinfo_setup() {
    load_theme_textdomain('culturinfo', get_template_directory() . '/languages');
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    add_theme_support('automatic-feed-links');
    add_theme_support('responsive-embeds');
    add_theme_support('align-wide');
    add_theme_support('html5', array('search-form', 'comment-form', 'comment-list', 'gallery', 'caption', 'style', 'script'));
    add_theme_support('custom-logo', array('height' => 220, 'width' => 720, 'flex-height' => true, 'flex-width' => true));

    register_nav_menus(array(
        'primary' => __('Navegación principal', 'culturinfo'),
        'footer'  => __('Navegación del pie', 'culturinfo'),
    ));

    add_image_size('culturinfo-lead', 1400, 850, true);
    add_image_size('culturinfo-card', 720, 480, true);
    add_image_size('culturinfo-thumb', 320, 220, true);
}
add_action('after_setup_theme', 'culturinfo_setup');

function culturinfo_assets() {
    $version = wp_get_theme()->get('Version');
    wp_enqueue_style('culturinfo-style', get_stylesheet_uri(), array(), $version);
    wp_enqueue_script('culturinfo-theme', get_template_directory_uri() . '/assets/js/theme.js', array(), $version, true);
}
add_action('wp_enqueue_scripts', 'culturinfo_assets');

function culturinfo_sections() {
    return array(
        'con-palabras' => array(
            'name'        => 'Con Palabras',
            'number'      => '01',
            'description' => 'Crónicas, entrevistas y relatos donde la palabra abre nuevas maneras de mirar.',
            'accent'      => '#f36b10',
        ),
        'arte-plural' => array(
            'name'        => 'Arte Plural',
            'number'      => '02',
            'description' => 'Creación, lenguajes artísticos y las voces que transforman nuestra sensibilidad.',
            'accent'      => '#b54165',
        ),
        'reflexiones-filo-linguisticas' => array(
            'name'        => 'Reflexiones Filo-lingüísticas',
            'number'      => '03',
            'description' => 'Ideas sobre lenguaje, pensamiento y los significados que construyen el mundo.',
            'accent'      => '#41796f',
        ),
        'anfora-cultura' => array(
            'name'        => 'Ánfora Cultura',
            'number'      => '04',
            'description' => 'Patrimonio, memoria e identidad: el legado cultural puesto en conversación.',
            'accent'      => '#9b642d',
        ),
        'ventana-social' => array(
            'name'        => 'Ventana Social',
            'number'      => '05',
            'description' => 'La sociedad en movimiento, sus desafíos y las iniciativas que generan encuentro.',
            'accent'      => '#386fa4',
        ),
        'aula-abierta' => array(
            'name'        => 'Aula Abierta',
            'number'      => '06',
            'description' => 'Educación sin fronteras: herramientas, experiencias y saberes para compartir.',
            'accent'      => '#d55232',
        ),
    );
}

function culturinfo_section_data($slug = '') {
    $sections = culturinfo_sections();
    return isset($sections[$slug]) ? $sections[$slug] : array(
        'name'        => single_cat_title('', false),
        'number'      => '—',
        'description' => category_description() ? wp_strip_all_tags(category_description()) : get_bloginfo('description'),
        'accent'      => '#f36b10',
    );
}

function culturinfo_logo_url() {
    $custom_logo_id = get_theme_mod('custom_logo');
    if ($custom_logo_id) {
        $logo = wp_get_attachment_image_src($custom_logo_id, 'full');
        if ($logo) {
            return $logo[0];
        }
    }
    return get_template_directory_uri() . '/assets/images/culturinfo-logo.jpg';
}

function culturinfo_primary_menu_fallback() {
    echo '<ul id="primary-menu" class="primary-menu">';
    foreach (culturinfo_sections() as $slug => $section) {
        $term = get_category_by_slug($slug);
        $url = $term ? get_category_link($term->term_id) : home_url('/category/' . $slug . '/');
        printf('<li><a href="%s">%s</a></li>', esc_url($url), esc_html($section['name']));
    }
    echo '</ul>';
}

function culturinfo_excerpt_length($length) {
    return 28;
}
add_filter('excerpt_length', 'culturinfo_excerpt_length', 99);

function culturinfo_excerpt_more() {
    return '…';
}
add_filter('excerpt_more', 'culturinfo_excerpt_more');

function culturinfo_posted_on() {
    printf(
        '<span>%s</span><span>%s</span>',
        esc_html(get_the_author()),
        esc_html(get_the_date('j \d\e F, Y'))
    );
}

function culturinfo_reading_time($post_id = null) {
    $content = get_post_field('post_content', $post_id ?: get_the_ID());
    $words = str_word_count(wp_strip_all_tags($content));
    return max(1, (int) ceil($words / 220));
}

function culturinfo_first_category() {
    $categories = get_the_category();
    return $categories ? $categories[0] : null;
}

function culturinfo_body_classes($classes) {
    if (is_category()) {
        $category = get_queried_object();
        $classes[] = 'section-' . sanitize_html_class($category->slug);
    }
    return $classes;
}
add_filter('body_class', 'culturinfo_body_classes');

function culturinfo_pingback_header() {
    if (is_singular() && pings_open()) {
        printf('<link rel="pingback" href="%s">', esc_url(get_bloginfo('pingback_url')));
    }
}
add_action('wp_head', 'culturinfo_pingback_header');
