<?php
/**
 * Plugin Name: Culturinfo — Seguridad
 * Description: Protección gratuita para login, formularios públicos y cargas de medios.
 * Version: 1.0.0
 * Author: Horizonte Cultural
 * Text Domain: culturinfo-security
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CULTURINFO_SECURITY_VERSION', '1.0.0');
define('CULTURINFO_SECURITY_SCHEMA_VERSION', '1');
define('CULTURINFO_SECURITY_ROLE_VERSION', '1');
define('CULTURINFO_SECURITY_FILE', __FILE__);
define('CULTURINFO_SECURITY_DIR', plugin_dir_path(__FILE__));

require_once CULTURINFO_SECURITY_DIR . 'includes/rate-limits.php';
require_once CULTURINFO_SECURITY_DIR . 'includes/turnstile.php';
require_once CULTURINFO_SECURITY_DIR . 'includes/login.php';
require_once CULTURINFO_SECURITY_DIR . 'includes/comments.php';
require_once CULTURINFO_SECURITY_DIR . 'includes/uploads.php';

function culturinfo_security_activate() {
    culturinfo_security_install_rate_limit_table();
    culturinfo_security_migrate_upload_roles();
    culturinfo_security_ensure_cleanup_schedule();
}
register_activation_hook(__FILE__, 'culturinfo_security_activate');

function culturinfo_security_deactivate() {
    wp_clear_scheduled_hook('culturinfo_security_cleanup');
}
register_deactivation_hook(__FILE__, 'culturinfo_security_deactivate');

function culturinfo_security_maybe_upgrade() {
    if (get_option('culturinfo_security_schema_version') !== CULTURINFO_SECURITY_SCHEMA_VERSION) {
        culturinfo_security_install_rate_limit_table();
    }
    if (get_option('culturinfo_security_role_version') !== CULTURINFO_SECURITY_ROLE_VERSION) {
        culturinfo_security_migrate_upload_roles();
    }
    culturinfo_security_ensure_cleanup_schedule();
}
add_action('init', 'culturinfo_security_maybe_upgrade', 1);
