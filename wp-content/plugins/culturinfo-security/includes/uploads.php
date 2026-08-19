<?php

if (!defined('ABSPATH')) {
    exit;
}

function culturinfo_security_migrate_upload_roles() {
    foreach (array('administrator', 'editor') as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->add_cap('upload_files');
        }
    }
    foreach (array('author', 'contributor', 'subscriber') as $role_name) {
        $role = get_role($role_name);
        if ($role) {
            $role->remove_cap('upload_files');
        }
    }
    update_option('culturinfo_security_role_version', CULTURINFO_SECURITY_ROLE_VERSION, false);
}

function culturinfo_security_allowed_mimes() {
    return array(
        'jpg|jpeg|jpe' => 'image/jpeg',
        'png'          => 'image/png',
        'gif'          => 'image/gif',
        'webp'         => 'image/webp',
        'avif'         => 'image/avif',
    );
}

function culturinfo_security_filter_upload_mimes($mimes) {
    unset($mimes);
    return culturinfo_security_allowed_mimes();
}
add_filter('upload_mimes', 'culturinfo_security_filter_upload_mimes', 99);

function culturinfo_security_upload_prefilter($file) {
    if (!current_user_can('upload_files')) {
        $file['error'] = 'Tu usuario no tiene permiso para subir archivos.';
        return $file;
    }

    $name = (string) ($file['name'] ?? '');
    $tmp_name = (string) ($file['tmp_name'] ?? '');
    $size = absint($file['size'] ?? 0);

    if ($name === '' || $tmp_name === '' || !is_file($tmp_name)) {
        $file['error'] = 'El archivo temporal no es válido.';
        return $file;
    }
    if ($size < 1 || $size > 15 * MB_IN_BYTES) {
        $file['error'] = 'La imagen debe pesar como máximo 15 MB.';
        return $file;
    }
    if (preg_match('/\.(php\d*|phtml|pht|phar|cgi|pl|py|sh)(\.|$)/i', $name)) {
        $file['error'] = 'El nombre del archivo contiene una extensión no permitida.';
        return $file;
    }

    $checked = wp_check_filetype_and_ext($tmp_name, $name, culturinfo_security_allowed_mimes());
    if (empty($checked['ext']) || empty($checked['type'])) {
        $file['error'] = 'El contenido del archivo no coincide con un formato de imagen permitido.';
        return $file;
    }

    $image = function_exists('wp_getimagesize') ? wp_getimagesize($tmp_name) : getimagesize($tmp_name);
    if (!is_array($image) || empty($image[0]) || empty($image[1])) {
        $file['error'] = 'No fue posible leer las dimensiones de la imagen.';
        return $file;
    }
    if (((int) $image[0] * (int) $image[1]) > 36000000) {
        $file['error'] = 'La imagen supera el límite de 36 megapíxeles.';
        return $file;
    }

    $user_id = get_current_user_id();
    $quota = culturinfo_security_rate_limit('media_user_day', 'user:' . $user_id, 100, DAY_IN_SECONDS);
    if (!$quota['allowed']) {
        $file['error'] = 'Se alcanzó el límite diario de cargas para este usuario.';
    }
    return $file;
}
add_filter('wp_handle_upload_prefilter', 'culturinfo_security_upload_prefilter');
