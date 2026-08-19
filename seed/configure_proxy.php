<?php
/**
 * Inserta de forma idempotente la detección HTTPS del proxy en wp-config.php.
 *
 * Se ejecuta antes de cargar WordPress para que is_ssl(), las cookies del panel
 * y los canonicales reconozcan el esquema original enviado por Coolify.
 */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este helper solo puede ejecutarse desde CLI.\n");
    exit(1);
}

$config_path = $argv[1] ?? '';
if ($config_path === '' || !is_file($config_path) || is_link($config_path)) {
    fwrite(STDERR, "wp-config.php no existe o no es un archivo regular.\n");
    exit(1);
}

$marker = 'CULTURINFO_REVERSE_PROXY_HTTPS_V2';
$config = file_get_contents($config_path);
if ($config === false) {
    fwrite(STDERR, "No se pudo leer wp-config.php.\n");
    exit(1);
}

if (strpos($config, $marker) !== false) {
    exit(0);
}

$anchor = "/* That's all, stop editing! Happy publishing. */";
$snippet = <<<'PHP'
/* CULTURINFO_REVERSE_PROXY_HTTPS_V2 */
$culturinfo_trust_proxy = getenv('TRUST_PROXY_HEADERS');
$culturinfo_trust_proxy = $culturinfo_trust_proxy !== false
    && in_array(strtolower(trim($culturinfo_trust_proxy)), array('1', 'true', 'yes', 'on'), true);

if ($culturinfo_trust_proxy && isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $culturinfo_forwarded_protos = array_map(
        'trim',
        explode(',', strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']))
    );

    if (in_array('https', $culturinfo_forwarded_protos, true)) {
        $_SERVER['HTTPS'] = 'on';
        $_SERVER['SERVER_PORT'] = '443';
    }
}

if (!defined('FORCE_SSL_ADMIN')) {
    define('FORCE_SSL_ADMIN', strpos((string) getenv('WP_SITEURL'), 'https://') === 0);
}

unset($culturinfo_trust_proxy, $culturinfo_forwarded_protos);
PHP;

$replacement_count = 0;
$old_pattern = '~\/\* CULTURINFO_REVERSE_PROXY_HTTPS \*\/.*?unset\(\$culturinfo_trust_proxy, \$culturinfo_forwarded_protos\);\R~s';
if (preg_match($old_pattern, $config)) {
    $updated_config = preg_replace($old_pattern, $snippet . "\n", $config, 1, $replacement_count);
} else {
    $updated_config = str_replace(
        $anchor,
        $snippet . "\n\n" . $anchor,
        $config,
        $replacement_count
    );
}

if ($updated_config === null || $replacement_count !== 1) {
    fwrite(STDERR, "No se encontró un ancla única en wp-config.php.\n");
    exit(1);
}

$config_dir = dirname($config_path);
$temporary_path = tempnam($config_dir, '.culturinfo-wp-config-');
if ($temporary_path === false) {
    fwrite(STDERR, "No se pudo crear el archivo temporal de configuración.\n");
    exit(1);
}

if (file_put_contents($temporary_path, $updated_config, LOCK_EX) === false) {
    unlink($temporary_path);
    fwrite(STDERR, "No se pudo escribir la configuración temporal.\n");
    exit(1);
}

$mode = fileperms($config_path);
if ($mode !== false) {
    chmod($temporary_path, $mode & 0777);
}

$owner = fileowner($config_path);
$group = filegroup($config_path);
if ($owner !== false) {
    chown($temporary_path, $owner);
}
if ($group !== false) {
    chgrp($temporary_path, $group);
}

if (!rename($temporary_path, $config_path)) {
    unlink($temporary_path);
    fwrite(STDERR, "No se pudo reemplazar wp-config.php.\n");
    exit(1);
}
