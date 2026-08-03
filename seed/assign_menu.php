<?php
/**
 * Asigna el menú principal al tema activo de forma idempotente.
 * Ejecutar con: CULTURINFO_MENU_ID=123 wp eval-file /seed/assign_menu.php
 */

$menu_id = (int) getenv('CULTURINFO_MENU_ID');
if (!$menu_id) {
    fwrite(STDERR, "ERROR: CULTURINFO_MENU_ID no está definido\n");
    return;
}

$locations = get_theme_mod('nav_menu_locations', array());
if (!is_array($locations)) {
    $locations = array();
}

$locations['primary'] = $menu_id;
$locations['footer'] = $menu_id;
set_theme_mod('nav_menu_locations', $locations);

echo 'Tema activo: ' . get_stylesheet() . "\n";
echo 'Ubicaciones: ' . wp_json_encode(get_theme_mod('nav_menu_locations')) . "\n";
