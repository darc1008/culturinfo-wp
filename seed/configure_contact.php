<?php
/**
 * Crea o completa la página de contacto sin borrar contenido editorial previo.
 */

$page = get_page_by_path('contacto', OBJECT, 'page');
$shortcode = '[culturinfo_contact_form]';
$intro = '¿Quieres proponer una colaboración, solicitar una corrección o conversar con el equipo editorial? Completa el formulario y selecciona el motivo de tu mensaje.';

if (!$page) {
    $page_id = wp_insert_post(array(
        'post_type'    => 'page',
        'post_status'  => 'publish',
        'post_title'   => 'Contacto',
        'post_name'    => 'contacto',
        'post_content' => $intro . "\n\n<div id=\"formulario-contacto\">{$shortcode}</div>",
    ), true);
    if (is_wp_error($page_id)) {
        fwrite(STDERR, 'No fue posible crear la página de contacto: ' . $page_id->get_error_message() . PHP_EOL);
        exit(1);
    }
    echo $page_id;
    return;
}

if (strpos($page->post_content, $shortcode) === false) {
    $content = trim($page->post_content);
    if ($content === '' || $content === '¿Quieres proponer una colaboración, enviar una historia o conversar con el equipo editorial? Escríbenos a través de los canales oficiales de Horizonte Cultural.') {
        $content = $intro;
    }
    $content .= "\n\n<div id=\"formulario-contacto\">{$shortcode}</div>";
    $updated = wp_update_post(array('ID' => $page->ID, 'post_content' => $content), true);
    if (is_wp_error($updated)) {
        fwrite(STDERR, 'No fue posible actualizar la página de contacto: ' . $updated->get_error_message() . PHP_EOL);
        exit(1);
    }
}

echo $page->ID;
