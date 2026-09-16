<?php
/**
 * Normaliza la sección Ánfora Cultural sin duplicar categorías ni publicaciones.
 *
 * Se ejecuta antes de crear/sincronizar las seis secciones del sitio.
 */

$taxonomy = 'category';
$legacy_slug = 'anfora-cultura';
$canonical_slug = 'anfora-cultural';
$canonical_name = 'Ánfora Cultural';
$description = 'Patrimonio, memoria e identidad: el legado cultural puesto en conversación.';

$legacy = get_term_by('slug', $legacy_slug, $taxonomy);
$canonical = get_term_by('slug', $canonical_slug, $taxonomy);

if (!$canonical && $legacy) {
    $result = wp_update_term((int) $legacy->term_id, $taxonomy, array(
        'name'        => $canonical_name,
        'slug'        => $canonical_slug,
        'description' => $description,
    ));
    if (is_wp_error($result)) {
        fwrite(STDERR, 'ERROR: no se pudo renombrar la sección Ánfora Cultural: ' . $result->get_error_message() . "\n");
        exit(1);
    }
    echo "Sección migrada de {$legacy_slug} a {$canonical_slug}.\n";
    return;
}

if ($canonical && $legacy && (int) $canonical->term_id !== (int) $legacy->term_id) {
    $post_ids = get_posts(array(
        'post_type'              => 'post',
        'post_status'            => array_keys(get_post_stati()),
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'no_found_rows'          => true,
        'ignore_sticky_posts'    => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => false,
        'tax_query'              => array(array(
            'taxonomy' => $taxonomy,
            'field'    => 'term_id',
            'terms'    => array((int) $legacy->term_id),
        )),
    ));

    foreach ($post_ids as $post_id) {
        $result = wp_set_post_terms((int) $post_id, array((int) $canonical->term_id), $taxonomy, true);
        if (is_wp_error($result)) {
            fwrite(STDERR, 'ERROR: no se pudo migrar la publicación ' . (int) $post_id . ': ' . $result->get_error_message() . "\n");
            exit(1);
        }
    }

    if ((int) get_option('default_category') === (int) $legacy->term_id) {
        update_option('default_category', (int) $canonical->term_id);
    }

    $result = wp_delete_term((int) $legacy->term_id, $taxonomy);
    if (is_wp_error($result) || !$result) {
        $message = is_wp_error($result) ? $result->get_error_message() : 'resultado vacío';
        fwrite(STDERR, 'ERROR: no se pudo retirar la categoría duplicada: ' . $message . "\n");
        exit(1);
    }
    echo sprintf("Categoría duplicada retirada; %d publicaciones conservadas.\n", count($post_ids));
}

if ($canonical) {
    $result = wp_update_term((int) $canonical->term_id, $taxonomy, array(
        'name'        => $canonical_name,
        'description' => $description,
    ));
    if (is_wp_error($result)) {
        fwrite(STDERR, 'ERROR: no se pudo actualizar Ánfora Cultural: ' . $result->get_error_message() . "\n");
        exit(1);
    }
}
