<?php

if (!defined('ABSPATH')) {
    exit;
}

function culturinfo_security_login_identity($username) {
    return culturinfo_security_client_identity('login_ip') . ':'
        . culturinfo_security_hash_identity('login_user', strtolower(trim((string) $username)));
}

function culturinfo_security_login_precheck($user, $username, $password) {
    if ($username === '' || $password === '') {
        return $user;
    }

    $pair = culturinfo_security_login_identity($username);
    $ip = culturinfo_security_client_identity('login_ip');
    $pair_status = culturinfo_security_rate_limit('login_pair', $pair, 5, 15 * MINUTE_IN_SECONDS, false);
    $ip_status = culturinfo_security_rate_limit('login_ip', $ip, 20, HOUR_IN_SECONDS, false);

    if (!$pair_status['allowed'] || !$ip_status['allowed']) {
        $retry = max($pair_status['retry_after'], $ip_status['retry_after']);
        status_header(429);
        nocache_headers();
        header('Retry-After: ' . max(1, absint($retry)));
        return new WP_Error('culturinfo_login_limited', 'No fue posible iniciar sesión. Espera unos minutos e inténtalo nuevamente.');
    }

    if (isset($_POST['log'], $_POST['pwd'])) {
        $verified = culturinfo_security_turnstile_verify('culturinfo_login');
        if (is_wp_error($verified)) {
            return $verified;
        }
    }

    return $user;
}
add_filter('authenticate', 'culturinfo_security_login_precheck', 5, 3);

function culturinfo_security_login_failed($username) {
    $pair = culturinfo_security_login_identity($username);
    $ip = culturinfo_security_client_identity('login_ip');
    culturinfo_security_rate_limit('login_pair', $pair, 5, 15 * MINUTE_IN_SECONDS);
    culturinfo_security_rate_limit('login_ip', $ip, 20, HOUR_IN_SECONDS);
}
add_action('wp_login_failed', 'culturinfo_security_login_failed', 10, 1);

function culturinfo_security_login_succeeded($user_login) {
    culturinfo_security_rate_reset('login_pair', culturinfo_security_login_identity($user_login), 15 * MINUTE_IN_SECONDS);
}
add_action('wp_login', 'culturinfo_security_login_succeeded', 10, 1);

function culturinfo_security_login_form_widget() {
    echo culturinfo_security_turnstile_widget('culturinfo_login'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action('login_form', 'culturinfo_security_login_form_widget');

function culturinfo_security_generic_login_error() {
    return 'No fue posible iniciar sesión con esos datos.';
}
add_filter('login_errors', 'culturinfo_security_generic_login_error');

function culturinfo_security_xmlrpc_enabled($enabled) {
    unset($enabled);
    return culturinfo_security_env_bool('CULTURINFO_XMLRPC_ENABLED', false);
}
add_filter('xmlrpc_enabled', 'culturinfo_security_xmlrpc_enabled', 99);

function culturinfo_security_block_xmlrpc_request() {
    if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST && !culturinfo_security_env_bool('CULTURINFO_XMLRPC_ENABLED', false)) {
        status_header(403);
        nocache_headers();
        wp_die('XML-RPC está deshabilitado.', 'Acceso denegado', array('response' => 403));
    }
}
add_action('plugins_loaded', 'culturinfo_security_block_xmlrpc_request', 0);

function culturinfo_security_remove_pingback_header($headers) {
    unset($headers['X-Pingback']);
    return $headers;
}
add_filter('wp_headers', 'culturinfo_security_remove_pingback_header');
