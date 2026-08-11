<?php
/**
 * Configuración idempotente de Rank Math para SEO y vistas previas sociales.
 *
 * No conecta una cuenta de Rank Math: la edición gratuita puede generar los
 * metadatos Open Graph, Twitter Card, sitemap y schema de forma local.
 */

if (PHP_SAPI !== 'cli' || !defined('ABSPATH')) {
    fwrite(STDERR, "Este helper solo puede ejecutarse desde WP-CLI.\n");
    exit(1);
}

if (!function_exists('is_plugin_active')) {
    require_once ABSPATH . 'wp-admin/includes/plugin.php';
}

if (!is_plugin_active('seo-by-rank-math/rank-math.php')) {
    fwrite(STDERR, "Rank Math no está activo.\n");
    exit(1);
}

$site_name = (string) get_option('blogname', 'Culturinfo');
$tagline = (string) get_option('blogdescription', 'Periódico digital de Horizonte Cultural');
$changed = false;

$titles = get_option('rank-math-options-titles', array());
if (!is_array($titles)) {
    $titles = array();
}

$title_defaults = array(
    'knowledgegraph_type'             => 'company',
    'knowledgegraph_name'             => $site_name,
    'website_name'                    => $site_name,
    'homepage_title'                  => '%sitename% %sep% %sitedesc%',
    'homepage_description'            => $tagline,
    'twitter_card_type'               => 'summary_large_image',
    'pt_post_title'                   => '%title% %sep% %sitename%',
    'pt_post_description'             => '%excerpt%',
    'pt_post_default_rich_snippet'    => 'article',
    'pt_post_default_article_type'    => 'NewsArticle',
    'pt_page_title'                   => '%title% %sep% %sitename%',
    'pt_page_description'             => '%excerpt%',
    'tax_category_title'              => '%term% %sep% %sitename%',
    'tax_category_description'        => '%term_description%',
);

foreach ($title_defaults as $key => $value) {
    if (!array_key_exists($key, $titles) || $titles[$key] !== $value) {
        $titles[$key] = $value;
        $changed = true;
    }
}

if ($changed) {
    update_option('rank-math-options-titles', $titles, false);
}

$general = get_option('rank-math-options-general', array());
if (!is_array($general)) {
    $general = array();
}

if (!isset($general['attachment_redirect_default']) || $general['attachment_redirect_default'] !== home_url('/')) {
    $general['attachment_redirect_default'] = home_url('/');
    update_option('rank-math-options-general', $general, false);
    $changed = true;
}

$sitemap = get_option('rank-math-options-sitemap', array());
if (!is_array($sitemap)) {
    $sitemap = array();
}

$sitemap_defaults = array(
    'include_images'        => 'on',
    'include_featured_image'=> 'on',
    'pt_post_sitemap'       => 'on',
    'pt_page_sitemap'       => 'on',
    'tax_category_sitemap'  => 'on',
    'pt_attachment_sitemap' => 'off',
);

foreach ($sitemap_defaults as $key => $value) {
    if (!array_key_exists($key, $sitemap) || $sitemap[$key] !== $value) {
        $sitemap[$key] = $value;
        $changed = true;
    }
}

if ($changed) {
    update_option('rank-math-options-sitemap', $sitemap, false);
}

$modules = get_option('rank_math_modules', array());
if (!is_array($modules)) {
    $modules = array();
}
foreach (array('sitemap', 'rich-snippet') as $module) {
    if (!in_array($module, $modules, true)) {
        $modules[] = $module;
        $changed = true;
    }
}
if ($changed) {
    update_option('rank_math_modules', array_values(array_unique($modules)), false);
}

// Rank Math Free funciona sin cuenta. Estas dos opciones evitan que el wizard
// de registro bloquee el frontend y, con ello, OG/Twitter/schema.
if (!get_option('rank_math_registration_skip')) {
    update_option('rank_math_registration_skip', true, false);
    $changed = true;
}
if (!get_option('rank_math_is_configured')) {
    update_option('rank_math_is_configured', true, false);
    $changed = true;
}

if ($changed) {
    update_option('rank_math_flush_rewrite', 1, false);
}

update_option('culturinfo_rank_math_seed_version', '1.0.0', false);
