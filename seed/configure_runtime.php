<?php
/**
 * Verificación rápida y segura para arranques posteriores al seed completo.
 */

require_once ABSPATH . 'wp-admin/includes/plugin.php';

$site_url = rtrim((string) (getenv('WP_SITEURL') ?: 'https://culturinfo.statusloop.app'), '/');
$scheme = wp_parse_url($site_url, PHP_URL_SCHEME);
if (!filter_var($site_url, FILTER_VALIDATE_URL) || !in_array($scheme, array('http', 'https'), true)) {
    fwrite(STDERR, "ERROR: WP_SITEURL debe ser una URL absoluta HTTP o HTTPS.\n");
    exit(1);
}

$options = array(
    'home'                => $site_url,
    'siteurl'             => $site_url,
    'blogname'            => (string) (getenv('WP_TITLE') ?: 'Culturinfo'),
    'blogdescription'     => (string) (getenv('WP_TAGLINE') ?: 'Periódico digital de Horizonte Cultural'),
    'timezone_string'     => (string) (getenv('WP_TIMEZONE') ?: 'America/Santo_Domingo'),
    'WPLANG'              => (string) (getenv('WP_LOCALE') ?: 'es_ES'),
    'permalink_structure' => '/%postname%/',
);

$changed = array();
foreach ($options as $name => $value) {
    if ((string) get_option($name) !== $value) {
        update_option($name, $value);
        $changed[] = $name;
    }
}
if (in_array('permalink_structure', $changed, true)) {
    flush_rewrite_rules(true);
}

$theme = wp_get_theme('culturinfo');
if (!$theme->exists()) {
    fwrite(STDERR, "ERROR: falta el tema versionado culturinfo.\n");
    exit(1);
}
if (get_stylesheet() !== 'culturinfo') {
    switch_theme('culturinfo');
}

$required_plugins = array(
    'culturinfo-security/culturinfo-security.php',
    'culturinfo-ads/culturinfo-ads.php',
    'culturinfo-authors/culturinfo-authors.php',
    'culturinfo-stats/culturinfo-stats.php',
    'culturinfo-publishing/culturinfo-publishing.php',
    'culturinfo-contact/culturinfo-contact.php',
    'culturinfo-audio/culturinfo-audio.php',
    'classic-editor/classic-editor.php',
    'seo-by-rank-math/rank-math.php',
    'independent-analytics/iawp.php',
    'two-factor/two-factor.php',
);

foreach ($required_plugins as $plugin) {
    if (!file_exists(WP_PLUGIN_DIR . '/' . $plugin)) {
        fwrite(STDERR, 'ERROR: falta el plugin esencial ' . $plugin . ".\n");
        exit(1);
    }
    if (!is_plugin_active($plugin)) {
        $result = activate_plugin($plugin);
        if (is_wp_error($result)) {
            fwrite(STDERR, 'ERROR: no se pudo activar ' . $plugin . ': ' . $result->get_error_message() . "\n");
            exit(1);
        }
    }
}

echo $changed
    ? 'Verificación rápida aplicada; opciones actualizadas: ' . implode(', ', $changed) . ".\n"
    : "Verificación rápida completada sin cambios.\n";
