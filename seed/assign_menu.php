<?php
/**
 * culturinfo - One-shot menu assignment
 * Run via: wp eval-file /tmp/assign_menu.php
 */
$menu_id = (int) getenv('CULTURINFO_MENU_ID');
if (!$menu_id) {
    echo "ERROR: CULTURINFO_MENU_ID not set\n";
    return;
}

// 1. theme_mods_colormag (ColorMag lee esto)
$mods = get_option('theme_mods_colormag');
if (!is_array($mods)) $mods = array();
$mods['nav_menu_locations'] = array('primary' => $menu_id);
update_option('theme_mods_colormag', $mods);

// 2. nav_menu_locations (registro global de WP)
update_option('nav_menu_locations', array('primary' => $menu_id));

// 3. Verificar
$check1 = get_option('theme_mods_colormag');
$check2 = get_option('nav_menu_locations');
echo "theme_mods_colormag.nav_menu_locations = " . json_encode($check1['nav_menu_locations'] ?? null) . "\n";
echo "nav_menu_locations = " . json_encode($check2) . "\n";

// 4. Listar items del menu asignado
$items = wp_get_nav_menu_items($menu_id);
echo "Menu items: " . count($items) . "\n";
foreach ($items as $item) {
    echo "  - ID=" . $item->ID . " | " . $item->title . " -> " . $item->url . "\n";
}
