<?php
/**
 * Plugin Name: Culturinfo — Gestor de anuncios
 * Description: Permite crear anuncios y asignarlos a espacios de la portada, las secciones y las noticias.
 * Version: 1.0.0
 * Author: Horizonte Cultural
 * Text Domain: culturinfo-ads
 */

if (!defined('ABSPATH')) {
    exit;
}

function culturinfo_ads_slots() {
    return array(
        'home_after_header'       => 'Portada — antes de la noticia principal',
        'home_after_lead'         => 'Portada — después de las noticias destacadas',
        'home_between_sections_2' => 'Portada — después de la segunda sección',
        'home_between_sections_4' => 'Portada — después de la cuarta sección',
        'home_before_footer'      => 'Portada — antes del bloque final',
        'section_after_header'    => 'Sección — después del encabezado',
        'section_after_feature'   => 'Sección — después de la noticia destacada',
        'section_before_footer'   => 'Sección — al final del listado',
        'article_after_header'    => 'Noticia — después del titular',
        'article_middle'          => 'Noticia — en medio del contenido',
        'article_after_content'   => 'Noticia — después del contenido',
        'article_sidebar'         => 'Noticia — columna lateral',
    );
}

function culturinfo_ads_register_post_type() {
    register_post_type('culturinfo_ad', array(
        'labels' => array(
            'name'                  => 'Anuncios',
            'singular_name'         => 'Anuncio',
            'add_new'               => 'Añadir anuncio',
            'add_new_item'          => 'Añadir nuevo anuncio',
            'edit_item'             => 'Editar anuncio',
            'new_item'              => 'Nuevo anuncio',
            'view_item'             => 'Ver anuncio',
            'search_items'          => 'Buscar anuncios',
            'not_found'             => 'No hay anuncios',
            'not_found_in_trash'    => 'No hay anuncios en la papelera',
            'featured_image'        => 'Imagen o banner del anuncio',
            'set_featured_image'    => 'Seleccionar imagen del anuncio',
            'remove_featured_image' => 'Quitar imagen del anuncio',
            'use_featured_image'    => 'Usar como imagen del anuncio',
        ),
        'public'             => false,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'show_in_rest'       => true,
        'menu_icon'          => 'dashicons-megaphone',
        'menu_position'      => 22,
        'supports'           => array('title', 'editor', 'thumbnail'),
        'capability_type'    => 'post',
        'has_archive'        => false,
        'rewrite'            => false,
        'exclude_from_search'=> true,
    ));
}
add_action('init', 'culturinfo_ads_register_post_type');

function culturinfo_ads_meta_boxes() {
    add_meta_box(
        'culturinfo-ad-settings',
        'Ubicación y destino',
        'culturinfo_ads_meta_box_html',
        'culturinfo_ad',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'culturinfo_ads_meta_boxes');

function culturinfo_ads_meta_box_html($post) {
    wp_nonce_field('culturinfo_ad_save', 'culturinfo_ad_nonce');

    $placement = get_post_meta($post->ID, '_culturinfo_ad_placement', true);
    $url = get_post_meta($post->ID, '_culturinfo_ad_url', true);
    $new_tab = get_post_meta($post->ID, '_culturinfo_ad_new_tab', true);
    $section_id = absint(get_post_meta($post->ID, '_culturinfo_ad_section', true));
    $article_id = absint(get_post_meta($post->ID, '_culturinfo_ad_article', true));
    $priority = absint(get_post_meta($post->ID, '_culturinfo_ad_priority', true));
    $expiry = get_post_meta($post->ID, '_culturinfo_ad_expiry', true);
    ?>
    <style>
        .culturinfo-ad-fields { display:grid; grid-template-columns:repeat(2,minmax(0,1fr)); gap:18px 24px; }
        .culturinfo-ad-field label { display:block; margin-bottom:6px; font-weight:600; }
        .culturinfo-ad-field select, .culturinfo-ad-field input[type="url"], .culturinfo-ad-field input[type="number"], .culturinfo-ad-field input[type="date"] { width:100%; }
        .culturinfo-ad-field--wide { grid-column:1/-1; }
        .culturinfo-ad-help { grid-column:1/-1; margin:0; padding:12px 14px; border-left:4px solid #f36b10; background:#f6f7f7; }
        @media (max-width:782px) { .culturinfo-ad-fields { grid-template-columns:1fr; }.culturinfo-ad-field--wide,.culturinfo-ad-help { grid-column:auto; } }
    </style>
    <div class="culturinfo-ad-fields">
        <p class="culturinfo-ad-help">Usa la <strong>imagen destacada</strong> como banner. Si no agregas imagen, se mostrará el contenido escrito en el editor. Cuando varios anuncios comparten espacio, aparece primero el de menor prioridad.</p>

        <div class="culturinfo-ad-field culturinfo-ad-field--wide">
            <label for="culturinfo-ad-placement">Espacio publicitario</label>
            <select id="culturinfo-ad-placement" name="culturinfo_ad_placement" required>
                <option value="">Selecciona dónde aparecerá</option>
                <?php foreach (culturinfo_ads_slots() as $slot => $label) : ?>
                    <option value="<?php echo esc_attr($slot); ?>" <?php selected($placement, $slot); ?>><?php echo esc_html($label); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="culturinfo-ad-field">
            <label for="culturinfo-ad-url">Enlace de destino</label>
            <input id="culturinfo-ad-url" type="url" name="culturinfo_ad_url" value="<?php echo esc_attr($url); ?>" placeholder="https://anunciante.com/">
        </div>

        <div class="culturinfo-ad-field">
            <label for="culturinfo-ad-priority">Prioridad</label>
            <input id="culturinfo-ad-priority" type="number" min="0" step="1" name="culturinfo_ad_priority" value="<?php echo esc_attr($priority); ?>">
            <small>0 aparece antes que 10.</small>
        </div>

        <div class="culturinfo-ad-field culturinfo-ad-target culturinfo-ad-target--section">
            <label for="culturinfo-ad-section">Mostrar en la sección</label>
            <?php
            wp_dropdown_categories(array(
                'show_option_all' => 'Todas las secciones',
                'taxonomy'        => 'category',
                'name'            => 'culturinfo_ad_section',
                'id'              => 'culturinfo-ad-section',
                'selected'        => $section_id,
                'hide_empty'      => false,
                'orderby'         => 'name',
            ));
            ?>
        </div>

        <div class="culturinfo-ad-field culturinfo-ad-target culturinfo-ad-target--article">
            <label for="culturinfo-ad-article">Mostrar en la noticia</label>
            <select id="culturinfo-ad-article" name="culturinfo_ad_article">
                <option value="0">Todas las noticias</option>
                <?php
                $articles = get_posts(array('post_type' => 'post', 'post_status' => array('publish', 'draft'), 'numberposts' => 100, 'orderby' => 'date', 'order' => 'DESC'));
                foreach ($articles as $article) :
                ?>
                    <option value="<?php echo esc_attr($article->ID); ?>" <?php selected($article_id, $article->ID); ?>><?php echo esc_html($article->post_title); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="culturinfo-ad-field">
            <label for="culturinfo-ad-expiry">Ocultar después de</label>
            <input id="culturinfo-ad-expiry" type="date" name="culturinfo_ad_expiry" value="<?php echo esc_attr($expiry); ?>">
            <small>Opcional. Déjalo vacío para mantenerlo activo.</small>
        </div>

        <div class="culturinfo-ad-field">
            <label><input type="checkbox" name="culturinfo_ad_new_tab" value="1" <?php checked($new_tab, '1'); ?>> Abrir el enlace en una pestaña nueva</label>
        </div>
    </div>
    <?php
}

function culturinfo_ads_admin_script($hook) {
    global $post_type;
    if ('culturinfo_ad' !== $post_type || !in_array($hook, array('post.php', 'post-new.php'), true)) {
        return;
    }
    ?>
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        var placement = document.getElementById('culturinfo-ad-placement');
        var sectionField = document.querySelector('.culturinfo-ad-target--section');
        var articleField = document.querySelector('.culturinfo-ad-target--article');
        function updateTargets() {
            var value = placement ? placement.value : '';
            if (sectionField) sectionField.style.display = value.indexOf('section_') === 0 ? '' : 'none';
            if (articleField) articleField.style.display = value.indexOf('article_') === 0 ? '' : 'none';
        }
        if (placement) placement.addEventListener('change', updateTargets);
        updateTargets();
    });
    </script>
    <?php
}
add_action('admin_footer', 'culturinfo_ads_admin_script');

function culturinfo_ads_save_meta($post_id) {
    if (!isset($_POST['culturinfo_ad_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['culturinfo_ad_nonce'])), 'culturinfo_ad_save')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $slots = culturinfo_ads_slots();
    $placement = isset($_POST['culturinfo_ad_placement']) ? sanitize_key(wp_unslash($_POST['culturinfo_ad_placement'])) : '';
    update_post_meta($post_id, '_culturinfo_ad_placement', isset($slots[$placement]) ? $placement : '');
    update_post_meta($post_id, '_culturinfo_ad_url', isset($_POST['culturinfo_ad_url']) ? esc_url_raw(wp_unslash($_POST['culturinfo_ad_url'])) : '');
    update_post_meta($post_id, '_culturinfo_ad_new_tab', isset($_POST['culturinfo_ad_new_tab']) ? '1' : '0');
    update_post_meta($post_id, '_culturinfo_ad_section', isset($_POST['culturinfo_ad_section']) ? absint($_POST['culturinfo_ad_section']) : 0);
    update_post_meta($post_id, '_culturinfo_ad_article', isset($_POST['culturinfo_ad_article']) ? absint($_POST['culturinfo_ad_article']) : 0);
    update_post_meta($post_id, '_culturinfo_ad_priority', isset($_POST['culturinfo_ad_priority']) ? absint($_POST['culturinfo_ad_priority']) : 0);

    $expiry = isset($_POST['culturinfo_ad_expiry']) ? sanitize_text_field(wp_unslash($_POST['culturinfo_ad_expiry'])) : '';
    update_post_meta($post_id, '_culturinfo_ad_expiry', preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry) ? $expiry : '');
}
add_action('save_post_culturinfo_ad', 'culturinfo_ads_save_meta');

function culturinfo_ads_columns($columns) {
    return array(
        'cb'           => $columns['cb'],
        'title'        => 'Anuncio',
        'ad_placement' => 'Ubicación',
        'ad_target'    => 'Destino',
        'ad_expiry'    => 'Vigencia',
        'date'         => $columns['date'],
    );
}
add_filter('manage_culturinfo_ad_posts_columns', 'culturinfo_ads_columns');

function culturinfo_ads_column_content($column, $post_id) {
    if ('ad_placement' === $column) {
        $slots = culturinfo_ads_slots();
        $placement = get_post_meta($post_id, '_culturinfo_ad_placement', true);
        echo esc_html(isset($slots[$placement]) ? $slots[$placement] : 'Sin asignar');
    }
    if ('ad_target' === $column) {
        $placement = get_post_meta($post_id, '_culturinfo_ad_placement', true);
        if (0 === strpos($placement, 'section_')) {
            $term_id = absint(get_post_meta($post_id, '_culturinfo_ad_section', true));
            echo $term_id ? esc_html(get_term_field('name', $term_id, 'category')) : 'Todas las secciones';
        } elseif (0 === strpos($placement, 'article_')) {
            $article_id = absint(get_post_meta($post_id, '_culturinfo_ad_article', true));
            echo $article_id ? esc_html(get_the_title($article_id)) : 'Todas las noticias';
        } else {
            echo 'Portada';
        }
    }
    if ('ad_expiry' === $column) {
        $expiry = get_post_meta($post_id, '_culturinfo_ad_expiry', true);
        echo $expiry ? esc_html(wp_date(get_option('date_format'), strtotime($expiry))) : 'Sin vencimiento';
    }
}
add_action('manage_culturinfo_ad_posts_custom_column', 'culturinfo_ads_column_content', 10, 2);

function culturinfo_ads_enqueue_style() {
    wp_enqueue_style('culturinfo-ads', plugin_dir_url(__FILE__) . 'assets/ads.css', array(), '1.0.0');
}
add_action('wp_enqueue_scripts', 'culturinfo_ads_enqueue_style');

function culturinfo_ads_find($slot, $context_id = 0) {
    if (!isset(culturinfo_ads_slots()[$slot])) {
        return null;
    }

    $query = new WP_Query(array(
        'post_type'              => 'culturinfo_ad',
        'post_status'            => 'publish',
        'posts_per_page'         => 20,
        'no_found_rows'          => true,
        'ignore_sticky_posts'    => true,
        'orderby'                => array('meta_value_num' => 'ASC', 'date' => 'DESC'),
        'meta_key'               => '_culturinfo_ad_priority',
        'meta_query'             => array(
            array(
                'key'     => '_culturinfo_ad_placement',
                'value'   => $slot,
                'compare' => '=',
            ),
        ),
    ));

    $today = wp_date('Y-m-d');
    foreach ($query->posts as $ad) {
        $expiry = get_post_meta($ad->ID, '_culturinfo_ad_expiry', true);
        if ($expiry && $expiry < $today) {
            continue;
        }
        if (0 === strpos($slot, 'section_')) {
            $target = absint(get_post_meta($ad->ID, '_culturinfo_ad_section', true));
            if ($target && $target !== absint($context_id)) {
                continue;
            }
        }
        if (0 === strpos($slot, 'article_')) {
            $target = absint(get_post_meta($ad->ID, '_culturinfo_ad_article', true));
            if ($target && $target !== absint($context_id)) {
                continue;
            }
        }
        return $ad;
    }
    return null;
}

function culturinfo_ads_get($slot, $context_id = 0) {
    $ad = culturinfo_ads_find($slot, $context_id);
    if (!$ad) {
        return '';
    }

    $url = get_post_meta($ad->ID, '_culturinfo_ad_url', true);
    $new_tab = '1' === get_post_meta($ad->ID, '_culturinfo_ad_new_tab', true);
    $target = $new_tab ? ' target="_blank"' : '';
    $rel = $new_tab ? ' rel="sponsored noopener"' : ' rel="sponsored"';
    $slot_class = str_replace('_', '-', $slot);

    ob_start();
    ?>
    <aside class="culturinfo-ad culturinfo-ad--<?php echo esc_attr($slot_class); ?>" aria-label="Publicidad">
        <span class="culturinfo-ad__label">Publicidad</span>
        <?php if ($url) : ?><a class="culturinfo-ad__link" href="<?php echo esc_url($url); ?>"<?php echo $target . $rel; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>><?php endif; ?>
            <?php if (has_post_thumbnail($ad)) : ?>
                <?php echo get_the_post_thumbnail($ad, 'full', array('class' => 'culturinfo-ad__image', 'loading' => 'lazy', 'alt' => get_the_title($ad))); ?>
            <?php else : ?>
                <div class="culturinfo-ad__content">
                    <strong class="culturinfo-ad__title"><?php echo esc_html(get_the_title($ad)); ?></strong>
                    <?php echo wp_kses_post(wpautop(do_shortcode($ad->post_content))); ?>
                </div>
            <?php endif; ?>
        <?php if ($url) : ?></a><?php endif; ?>
    </aside>
    <?php
    return (string) ob_get_clean();
}

function culturinfo_ads_render($slot, $context_id = 0) {
    echo culturinfo_ads_get($slot, $context_id); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}

function culturinfo_ads_insert_middle($content) {
    if (!is_singular('post') || !in_the_loop() || !is_main_query()) {
        return $content;
    }
    $ad = culturinfo_ads_get('article_middle', get_the_ID());
    if (!$ad) {
        return $content;
    }

    $paragraphs = explode('</p>', $content);
    $paragraph_count = max(0, count($paragraphs) - 1);
    if ($paragraph_count < 3) {
        return $content . $ad;
    }
    $position = max(2, (int) floor($paragraph_count / 2));
    $output = '';
    foreach ($paragraphs as $index => $paragraph) {
        $output .= $paragraph;
        if ($index < $paragraph_count) {
            $output .= '</p>';
        }
        if (($index + 1) === $position) {
            $output .= $ad;
        }
    }
    return $output;
}
add_filter('the_content', 'culturinfo_ads_insert_middle', 20);
