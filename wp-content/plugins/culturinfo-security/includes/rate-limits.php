<?php

if (!defined('ABSPATH')) {
    exit;
}

function culturinfo_security_rate_limit_table() {
    global $wpdb;
    return $wpdb->prefix . 'culturinfo_rate_limits';
}

function culturinfo_security_install_rate_limit_table() {
    global $wpdb;
    $table = culturinfo_security_rate_limit_table();
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("CREATE TABLE {$table} (
        rate_key char(64) NOT NULL,
        scope varchar(64) NOT NULL,
        window_start bigint(20) unsigned NOT NULL,
        request_count bigint(20) unsigned NOT NULL DEFAULT 0,
        expires_at bigint(20) unsigned NOT NULL,
        updated_at datetime NOT NULL,
        PRIMARY KEY  (rate_key),
        KEY expires_at (expires_at),
        KEY scope_window (scope,window_start)
    ) {$charset};");

    update_option('culturinfo_security_schema_version', CULTURINFO_SECURITY_SCHEMA_VERSION, false);
}

function culturinfo_security_env_bool($name, $default = false) {
    $value = getenv($name);
    if ($value === false || trim((string) $value) === '') {
        return (bool) $default;
    }
    return in_array(strtolower(trim((string) $value)), array('1', 'true', 'yes', 'on'), true);
}

function culturinfo_security_client_ip() {
    $candidate = isset($_SERVER['REMOTE_ADDR']) ? wp_unslash($_SERVER['REMOTE_ADDR']) : '';
    $trust_proxy = culturinfo_security_env_bool('TRUST_PROXY_HEADERS', false);

    if ($trust_proxy && !empty($_SERVER['HTTP_CF_RAY']) && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $candidate = wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']);
    }

    $candidate = trim((string) $candidate);
    return filter_var($candidate, FILTER_VALIDATE_IP) ? $candidate : '0.0.0.0';
}

function culturinfo_security_hash_identity($scope, $identity) {
    return hash_hmac(
        'sha256',
        sanitize_key((string) $scope) . '|' . (string) $identity,
        wp_salt('auth')
    );
}

function culturinfo_security_client_identity($scope = 'client') {
    return culturinfo_security_hash_identity($scope, culturinfo_security_client_ip());
}

function culturinfo_security_rate_key($scope, $identity, $window_start) {
    return hash_hmac(
        'sha256',
        sanitize_key((string) $scope) . '|' . (string) $identity . '|' . (string) $window_start,
        wp_salt('secure_auth')
    );
}

/**
 * Incrementa y comprueba un bucket fijo de forma atómica.
 *
 * @return array{allowed:bool,count:int,retry_after:int}
 */
function culturinfo_security_rate_limit($scope, $identity, $limit, $window, $increment = true) {
    global $wpdb;

    $scope = substr(sanitize_key((string) $scope), 0, 64);
    $limit = max(1, absint($limit));
    $window = max(1, absint($window));
    $now = time();
    $window_start = (int) (floor($now / $window) * $window);
    $expires_at = $window_start + $window;
    $rate_key = culturinfo_security_rate_key($scope, $identity, $window_start);
    $table = culturinfo_security_rate_limit_table();

    if ($increment) {
        $sql = $wpdb->prepare(
            "INSERT INTO {$table} (rate_key, scope, window_start, request_count, expires_at, updated_at)
             VALUES (%s, %s, %d, 1, %d, %s)
             ON DUPLICATE KEY UPDATE request_count = request_count + 1, updated_at = VALUES(updated_at)",
            $rate_key,
            $scope,
            $window_start,
            $expires_at,
            current_time('mysql', true)
        );
        $result = $wpdb->query($sql);
        if ($result === false) {
            return array('allowed' => false, 'count' => $limit + 1, 'retry_after' => max(1, $expires_at - $now));
        }
    }

    $count = absint($wpdb->get_var($wpdb->prepare(
        "SELECT request_count FROM {$table} WHERE rate_key = %s",
        $rate_key
    )));

    return array(
        'allowed'     => $increment ? $count <= $limit : $count < $limit,
        'count'       => $count,
        'retry_after' => max(1, $expires_at - $now),
    );
}

function culturinfo_security_rate_reset($scope, $identity, $window) {
    global $wpdb;
    $window = max(1, absint($window));
    $window_start = (int) (floor(time() / $window) * $window);
    $rate_key = culturinfo_security_rate_key($scope, $identity, $window_start);
    $wpdb->delete(culturinfo_security_rate_limit_table(), array('rate_key' => $rate_key), array('%s'));
}

function culturinfo_security_rate_block($message, $retry_after) {
    $retry_after = max(1, absint($retry_after));
    status_header(429);
    nocache_headers();
    header('Retry-After: ' . $retry_after);
    wp_die(
        esc_html($message),
        esc_html__('Demasiadas solicitudes', 'culturinfo-security'),
        array('response' => 429)
    );
}

function culturinfo_security_notification_allowed() {
    $result = culturinfo_security_rate_limit('notification_email', 'global', 20, HOUR_IN_SECONDS);
    return !empty($result['allowed']);
}

function culturinfo_security_ensure_cleanup_schedule() {
    if (!wp_next_scheduled('culturinfo_security_cleanup')) {
        wp_schedule_event(time() + HOUR_IN_SECONDS, 'daily', 'culturinfo_security_cleanup');
    }
}

function culturinfo_security_cleanup() {
    global $wpdb;
    $table = culturinfo_security_rate_limit_table();
    $cutoff = time() - DAY_IN_SECONDS;
    $wpdb->query($wpdb->prepare("DELETE FROM {$table} WHERE expires_at < %d", $cutoff));
}
add_action('culturinfo_security_cleanup', 'culturinfo_security_cleanup');
