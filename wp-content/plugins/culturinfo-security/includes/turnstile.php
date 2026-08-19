<?php

if (!defined('ABSPATH')) {
    exit;
}

function culturinfo_security_turnstile_enabled() {
    return culturinfo_security_env_bool('CULTURINFO_TURNSTILE_ENABLED', false);
}

function culturinfo_security_turnstile_site_key() {
    return trim((string) getenv('CULTURINFO_TURNSTILE_SITE_KEY'));
}

function culturinfo_security_turnstile_secret_key() {
    return trim((string) getenv('CULTURINFO_TURNSTILE_SECRET_KEY'));
}

function culturinfo_security_turnstile_configured() {
    return culturinfo_security_turnstile_enabled()
        && culturinfo_security_turnstile_site_key() !== ''
        && culturinfo_security_turnstile_secret_key() !== '';
}

function culturinfo_security_turnstile_widget($action) {
    if (!culturinfo_security_turnstile_configured()) {
        return '';
    }

    static $script_printed = false;
    $html = '';
    if (!$script_printed) {
        $html .= '<script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>';
        $script_printed = true;
    }
    $html .= sprintf(
        '<div class="cf-turnstile culturinfo-turnstile" data-sitekey="%s" data-action="%s" data-language="es" data-theme="auto"></div>',
        esc_attr(culturinfo_security_turnstile_site_key()),
        esc_attr(sanitize_key($action))
    );
    return $html;
}

/** @return true|WP_Error */
function culturinfo_security_turnstile_verify($action) {
    if (!culturinfo_security_turnstile_configured()) {
        return true;
    }

    $token = isset($_POST['cf-turnstile-response'])
        ? sanitize_text_field(wp_unslash($_POST['cf-turnstile-response']))
        : '';
    if ($token === '') {
        return new WP_Error('turnstile_missing', 'Completa la verificación de seguridad.');
    }

    $response = wp_remote_post('https://challenges.cloudflare.com/turnstile/v0/siteverify', array(
        'timeout' => 5,
        'body'    => array(
            'secret'   => culturinfo_security_turnstile_secret_key(),
            'response' => $token,
            'remoteip' => culturinfo_security_client_ip(),
        ),
    ));

    if (is_wp_error($response)) {
        return new WP_Error('turnstile_unavailable', 'La verificación de seguridad no está disponible. Inténtalo nuevamente.');
    }

    $data = json_decode((string) wp_remote_retrieve_body($response), true);
    if (!is_array($data) || empty($data['success'])) {
        return new WP_Error('turnstile_invalid', 'La verificación de seguridad venció o no es válida.');
    }

    if (sanitize_key((string) ($data['action'] ?? '')) !== sanitize_key($action)) {
        return new WP_Error('turnstile_action', 'La verificación de seguridad no corresponde a este formulario.');
    }

    $expected_host = strtolower((string) wp_parse_url(home_url('/'), PHP_URL_HOST));
    $verified_host = strtolower((string) ($data['hostname'] ?? ''));
    if ($expected_host !== '' && $verified_host !== $expected_host) {
        return new WP_Error('turnstile_hostname', 'La verificación de seguridad pertenece a otro dominio.');
    }

    return true;
}

function culturinfo_security_turnstile_admin_notice() {
    if (!current_user_can('manage_options') || !culturinfo_security_turnstile_enabled() || culturinfo_security_turnstile_configured()) {
        return;
    }
    echo '<div class="notice notice-warning"><p>'
        . esc_html__('Culturinfo: Turnstile está habilitado pero faltan la clave pública o secreta en las variables de entorno.', 'culturinfo-security')
        . '</p></div>';
}
add_action('admin_notices', 'culturinfo_security_turnstile_admin_notice');
