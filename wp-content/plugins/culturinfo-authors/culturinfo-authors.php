<?php
/**
 * Plugin Name: Culturinfo — Autores editoriales
 * Description: Gestiona escritores independientes de los usuarios de WordPress y los asigna a las noticias.
 * Version: 1.0.0
 * Author: Horizonte Cultural
 * Text Domain: culturinfo-authors
 */

if (!defined('ABSPATH')) {
    exit;
}

function culturinfo_authors_social_fields() {
    return array(
        'facebook'  => array('label' => 'Facebook', 'short' => 'f'),
        'instagram' => array('label' => 'Instagram', 'short' => 'ig'),
        'x'         => array('label' => 'X / Twitter', 'short' => 'x'),
        'linkedin'  => array('label' => 'LinkedIn', 'short' => 'in'),
        'youtube'   => array('label' => 'YouTube', 'short' => 'yt'),
        'website'   => array('label' => 'Sitio web', 'short' => 'web'),
    );
}

function culturinfo_authors_register_post_type() {
    register_post_type('culturinfo_writer', array(
        'labels' => array(
            'name'               => 'Autores',
            'singular_name'      => 'Autor',
            'add_new'            => 'Añadir autor',
            'add_new_item'       => 'Añadir nuevo autor',
            'edit_item'          => 'Editar autor',
            'new_item'           => 'Nuevo autor',
            'view_item'          => 'Ver autor',
            'search_items'       => 'Buscar autores',
            'not_found'          => 'No se encontraron autores',
            'not_found_in_trash' => 'No hay autores en la papelera',
            'featured_image'     => 'Fotografía del autor',
            'set_featured_image' => 'Seleccionar fotografía',
            'remove_featured_image' => 'Quitar fotografía',
            'use_featured_image' => 'Usar como fotografía',
        ),
        'public'              => false,
        'publicly_queryable'  => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => true,
        'menu_icon'           => 'dashicons-id-alt',
        'menu_position'       => 21,
        'supports'            => array('title', 'editor', 'thumbnail'),
        'capability_type'     => 'page',
        'map_meta_cap'        => true,
        'has_archive'         => false,
        'rewrite'             => false,
        'exclude_from_search' => true,
    ));

    register_post_meta('post', '_culturinfo_writer_id', array(
        'type'              => 'integer',
        'single'            => true,
        'show_in_rest'      => true,
        'sanitize_callback' => 'absint',
        'auth_callback'     => function () {
            return current_user_can('edit_posts');
        },
    ));
}
add_action('init', 'culturinfo_authors_register_post_type');

function culturinfo_authors_add_meta_boxes() {
    add_meta_box(
        'culturinfo-writer-socials',
        'Redes y enlaces',
        'culturinfo_authors_socials_meta_box',
        'culturinfo_writer',
        'normal',
        'default'
    );

    add_meta_box(
        'culturinfo-article-writer',
        'Autor de la noticia',
        'culturinfo_authors_article_meta_box',
        'post',
        'side',
        'high'
    );
}
add_action('add_meta_boxes', 'culturinfo_authors_add_meta_boxes');

function culturinfo_authors_socials_meta_box($post) {
    wp_nonce_field('culturinfo_writer_profile_save', 'culturinfo_writer_profile_nonce');
    ?>
    <p>La descripción principal de esta pantalla se mostrará como biografía al final de sus noticias.</p>
    <table class="form-table" role="presentation">
        <tbody>
        <?php foreach (culturinfo_authors_social_fields() as $key => $field) : ?>
            <tr>
                <th scope="row"><label for="culturinfo-writer-<?php echo esc_attr($key); ?>"><?php echo esc_html($field['label']); ?></label></th>
                <td>
                    <input
                        class="regular-text"
                        id="culturinfo-writer-<?php echo esc_attr($key); ?>"
                        name="culturinfo_writer_socials[<?php echo esc_attr($key); ?>]"
                        type="url"
                        value="<?php echo esc_attr(get_post_meta($post->ID, '_culturinfo_writer_' . $key, true)); ?>"
                        placeholder="https://"
                    >
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php
}

function culturinfo_authors_article_meta_box($post) {
    wp_nonce_field('culturinfo_article_writer_save', 'culturinfo_article_writer_nonce');
    $selected_writer = absint(get_post_meta($post->ID, '_culturinfo_writer_id', true));
    $writers = get_posts(array(
        'post_type'      => 'culturinfo_writer',
        'post_status'    => array('publish', 'draft', 'private'),
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ));
    ?>
    <p>
        <label class="screen-reader-text" for="culturinfo-writer-id">Autor de la noticia</label>
        <select id="culturinfo-writer-id" name="culturinfo_writer_id" style="width:100%">
            <option value="0">Usuario de WordPress asignado</option>
            <?php foreach ($writers as $writer) : ?>
                <option value="<?php echo esc_attr($writer->ID); ?>" <?php selected($selected_writer, $writer->ID); ?>>
                    <?php echo esc_html($writer->post_title); ?><?php echo $writer->post_status !== 'publish' ? ' — ' . esc_html($writer->post_status) : ''; ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <p class="description">El escritor elegido es independiente del usuario que publica la noticia.</p>
    <?php if (current_user_can('edit_pages')) : ?>
        <p><a href="<?php echo esc_url(admin_url('post-new.php?post_type=culturinfo_writer')); ?>">Crear un autor nuevo</a></p>
    <?php endif; ?>
    <?php
}

function culturinfo_authors_save_writer_profile($post_id) {
    if (!isset($_POST['culturinfo_writer_profile_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['culturinfo_writer_profile_nonce'])), 'culturinfo_writer_profile_save')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $submitted = isset($_POST['culturinfo_writer_socials']) && is_array($_POST['culturinfo_writer_socials'])
        ? wp_unslash($_POST['culturinfo_writer_socials'])
        : array();

    foreach (culturinfo_authors_social_fields() as $key => $field) {
        $value = isset($submitted[$key]) ? esc_url_raw($submitted[$key]) : '';
        if ($value !== '') {
            update_post_meta($post_id, '_culturinfo_writer_' . $key, $value);
        } else {
            delete_post_meta($post_id, '_culturinfo_writer_' . $key);
        }
    }
}
add_action('save_post_culturinfo_writer', 'culturinfo_authors_save_writer_profile');

function culturinfo_authors_save_article_writer($post_id) {
    if (!isset($_POST['culturinfo_article_writer_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['culturinfo_article_writer_nonce'])), 'culturinfo_article_writer_save')) {
        return;
    }
    if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $writer_id = isset($_POST['culturinfo_writer_id']) ? absint($_POST['culturinfo_writer_id']) : 0;
    if ($writer_id > 0 && get_post_type($writer_id) === 'culturinfo_writer') {
        update_post_meta($post_id, '_culturinfo_writer_id', $writer_id);
    } else {
        delete_post_meta($post_id, '_culturinfo_writer_id');
    }
}
add_action('save_post_post', 'culturinfo_authors_save_article_writer');

function culturinfo_authors_initials($name) {
    $words = preg_split('/\s+/u', trim((string) $name));
    $initials = '';
    foreach (array_slice(array_filter($words), 0, 2) as $word) {
        $initials .= function_exists('mb_substr') ? mb_substr($word, 0, 1) : substr($word, 0, 1);
    }
    return function_exists('mb_strtoupper') ? mb_strtoupper($initials) : strtoupper($initials);
}

/**
 * Devuelve el perfil editorial elegido para una noticia, con fallback al usuario.
 */
function culturinfo_authors_get_article_author($article_id = 0) {
    $article_id = $article_id ? absint($article_id) : get_the_ID();
    $writer_id = absint(get_post_meta($article_id, '_culturinfo_writer_id', true));

    if ($writer_id > 0 && get_post_type($writer_id) === 'culturinfo_writer') {
        $writer = get_post($writer_id);
        $socials = array();
        foreach (culturinfo_authors_social_fields() as $key => $field) {
            $url = get_post_meta($writer_id, '_culturinfo_writer_' . $key, true);
            if ($url) {
                $socials[] = array(
                    'label' => $field['label'],
                    'short' => $field['short'],
                    'url'   => $url,
                );
            }
        }

        return array(
            'id'         => $writer_id,
            'source'     => 'editorial',
            'name'       => get_the_title($writer_id),
            'url'        => '#autor-' . $writer_id,
            'anchor_id'  => 'autor-' . $writer_id,
            'photo_id'   => get_post_thumbnail_id($writer_id),
            'avatar_url' => '',
            'initials'   => culturinfo_authors_initials(get_the_title($writer_id)),
            'bio'        => $writer ? $writer->post_content : '',
            'socials'    => $socials,
        );
    }

    $user_id = absint(get_post_field('post_author', $article_id));
    $name = $user_id > 0 ? get_the_author_meta('display_name', $user_id) : get_bloginfo('name');
    $website = $user_id > 0 ? get_the_author_meta('user_url', $user_id) : '';

    return array(
        'id'         => $user_id,
        'source'     => 'wordpress',
        'name'       => $name,
        'url'        => $user_id > 0 ? get_author_posts_url($user_id) : home_url('/'),
        'anchor_id'  => 'autor-wordpress-' . $user_id,
        'photo_id'   => 0,
        'avatar_url' => $user_id > 0 ? get_avatar_url($user_id, array('size' => 192)) : '',
        'initials'   => culturinfo_authors_initials($name),
        'bio'        => $user_id > 0 ? get_the_author_meta('description', $user_id) : '',
        'socials'    => $website ? array(array('label' => 'Sitio web', 'short' => 'web', 'url' => $website)) : array(),
    );
}

function culturinfo_authors_columns($columns) {
    return array(
        'cb'           => $columns['cb'],
        'writer_photo' => 'Foto',
        'title'        => 'Autor',
        'writer_links' => 'Enlaces',
        'date'         => $columns['date'],
    );
}
add_filter('manage_culturinfo_writer_posts_columns', 'culturinfo_authors_columns');

function culturinfo_authors_column_content($column, $post_id) {
    if ($column === 'writer_photo') {
        echo get_the_post_thumbnail($post_id, array(48, 48), array('style' => 'width:48px;height:48px;object-fit:cover;border-radius:50%')); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    }
    if ($column === 'writer_links') {
        $labels = array();
        foreach (culturinfo_authors_social_fields() as $key => $field) {
            if (get_post_meta($post_id, '_culturinfo_writer_' . $key, true)) {
                $labels[] = $field['label'];
            }
        }
        echo esc_html($labels ? implode(', ', $labels) : '—');
    }
}
add_action('manage_culturinfo_writer_posts_custom_column', 'culturinfo_authors_column_content', 10, 2);
