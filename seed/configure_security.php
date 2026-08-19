<?php
/** Inserta la política de modificaciones web antes de cargar WordPress. */

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "Este helper solo puede ejecutarse desde CLI.\n");
    exit(1);
}

$config_path = $argv[1] ?? '';
if ($config_path === '' || !is_file($config_path) || is_link($config_path)) {
    fwrite(STDERR, "wp-config.php no existe o no es un archivo regular.\n");
    exit(1);
}

$marker = 'CULTURINFO_FILE_SECURITY';
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
/* CULTURINFO_FILE_SECURITY */
if (!defined('DISALLOW_FILE_EDIT')) {
    define('DISALLOW_FILE_EDIT', true);
}
if (!defined('DISALLOW_FILE_MODS')) {
    define('DISALLOW_FILE_MODS', !(defined('WP_CLI') && WP_CLI));
}
PHP;

$replacement_count = 0;
$updated = str_replace($anchor, $snippet . "\n\n" . $anchor, $config, $replacement_count);
if ($replacement_count !== 1) {
    fwrite(STDERR, "No se encontró un ancla única en wp-config.php.\n");
    exit(1);
}

$temporary = tempnam(dirname($config_path), '.culturinfo-security-');
if ($temporary === false || file_put_contents($temporary, $updated, LOCK_EX) === false) {
    if ($temporary !== false && is_file($temporary)) {
        unlink($temporary);
    }
    fwrite(STDERR, "No se pudo escribir la configuración temporal.\n");
    exit(1);
}

$mode = fileperms($config_path);
if ($mode !== false) {
    chmod($temporary, $mode & 0777);
}
if (!rename($temporary, $config_path)) {
    unlink($temporary);
    fwrite(STDERR, "No se pudo reemplazar wp-config.php.\n");
    exit(1);
}
