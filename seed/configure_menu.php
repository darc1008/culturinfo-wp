<?php
/**
 * Sincroniza el menú editorial sin duplicar sus secciones.
 *
 * Se ejecuta en cada despliegue con WP-CLI. Reutiliza el menú asignado a la
 * ubicación principal, conserva enlaces adicionales creados por el cliente y
 * elimina únicamente copias repetidas de las seis secciones administradas.
 */

$sections = array(
    'con-palabras'                  => 'Con Palabras',
    'arte-plural'                   => 'Arte Plural',
    'reflexiones-filo-linguisticas' => 'Reflexiones Filo-lingüísticas',
    'anfora-cultura'                => 'Ánfora Cultura',
    'ventana-social'                => 'Ventana Social',
    'aula-abierta'                  => 'Aula Abierta',
);

$locations = get_theme_mod('nav_menu_locations', array());
if (!is_array($locations)) {
    $locations = array();
}

$menu = !empty($locations['primary'])
    ? wp_get_nav_menu_object((int) $locations['primary'])
    : false;

if (!$menu) {
    $menu = wp_get_nav_menu_object('Menú Principal');
}

if (!$menu) {
    $menu_id = wp_create_nav_menu('Menú Principal');
    if (is_wp_error($menu_id)) {
        fwrite(STDERR, 'ERROR: no se pudo crear el menú principal: ' . $menu_id->get_error_message() . "\n");
        exit(1);
    }
    $menu = wp_get_nav_menu_object($menu_id);
}

$menu_id = (int) $menu->term_id;
$items = wp_get_nav_menu_items($menu_id, array('post_status' => 'any'));
$items = is_array($items) ? $items : array();
$managed_ids = array();
$removed = 0;
$position = 1;

foreach ($sections as $slug => $title) {
    $expected_path = '/category/' . $slug;
    $matches = array();

    foreach ($items as $item) {
        $item_path = wp_parse_url((string) $item->url, PHP_URL_PATH);
        $item_path = '/' . trim(rawurldecode((string) $item_path), '/');
        $is_expected_url = untrailingslashit($item_path) === $expected_path;
        $is_expected_term = $item->object === 'category'
            && get_term_field('slug', (int) $item->object_id, 'category') === $slug;

        if ($is_expected_url || $is_expected_term) {
            $matches[] = $item;
        }
    }

    $item_id = $matches ? (int) array_shift($matches)->ID : 0;
    $item_id = wp_update_nav_menu_item($menu_id, $item_id, array(
        'menu-item-title'    => $title,
        'menu-item-url'      => home_url('/category/' . $slug . '/'),
        'menu-item-status'   => 'publish',
        'menu-item-position' => $position,
        'menu-item-type'     => 'custom',
    ));

    if (is_wp_error($item_id)) {
        fwrite(STDERR, 'ERROR: no se pudo sincronizar ' . $title . ': ' . $item_id->get_error_message() . "\n");
        exit(1);
    }

    $managed_ids[] = (int) $item_id;
    $position++;

    foreach ($matches as $duplicate) {
        if (wp_delete_post((int) $duplicate->ID, true)) {
            $removed++;
        }
    }
}

// Los enlaces ajenos a las secciones permanecen después de los elementos
// editoriales y mantienen su orden relativo.
foreach ($items as $item) {
    if (in_array((int) $item->ID, $managed_ids, true) || get_post_status((int) $item->ID) === false) {
        continue;
    }

    $item_path = wp_parse_url((string) $item->url, PHP_URL_PATH);
    $item_path = '/' . trim(rawurldecode((string) $item_path), '/');
    $is_managed = false;
    foreach (array_keys($sections) as $slug) {
        if (untrailingslashit($item_path) === '/category/' . $slug) {
            $is_managed = true;
            break;
        }
    }
    if ($is_managed) {
        continue;
    }

    wp_update_post(array(
        'ID'         => (int) $item->ID,
        'menu_order' => $position,
    ));
    $position++;
}

$locations['primary'] = $menu_id;
$locations['footer'] = $menu_id;
set_theme_mod('nav_menu_locations', $locations);

echo sprintf(
    "Menú %d sincronizado: %d secciones, %d duplicados eliminados.\n",
    $menu_id,
    count($sections),
    $removed
);
