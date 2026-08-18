<?php
/**
 * Plugin Name: Culturinfo — Contacto editorial
 * Description: Recibe propuestas y mensajes sin adjuntos, con límites de frecuencia y retención definida.
 * Version: 1.0.0
 * Author: Horizonte Cultural
 * Text Domain: culturinfo-contact
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CULTURINFO_CONTACT_VERSION', '1.0.0');
define('CULTURINFO_CONTACT_CAP_VERSION', '1');

function culturinfo_contact_types() {
    return array(
        'general'     => 'Contacto general',
        'propuesta'   => 'Proponer una noticia',
        'correccion'  => 'Solicitar una corrección',
        'publicidad'  => 'Publicidad y alianzas',
        'colaborador' => 'Colaborar con Culturinfo',
    );
}

function culturinfo_contact_capabilities() {
    return array(
        'edit_culturinfo_message',
        'read_culturinfo_message',
        'delete_culturinfo_message',
        'edit_culturinfo_messages',
        'edit_others_culturinfo_messages',
        'read_private_culturinfo_messages',
        'delete_culturinfo_messages',
        'delete_private_culturinfo_messages',
        'delete_others_culturinfo_messages',
        'edit_private_culturinfo_messages',
    );
}

function culturinfo_contact_grant_capabilities() {
    foreach (array('administrator', 'editor') as $role_name) {
        $role = get_role($role_name);
        if (!$role) {
            continue;
        }
        foreach (culturinfo_contact_capabilities() as $capability) {
            $role->add_cap($capability);
        }
    }
    update_option('culturinfo_contact_cap_version', CULTURINFO_CONTACT_CAP_VERSION, false);
}

function culturinfo_contact_maybe_upgrade_capabilities() {
    if (get_option('culturinfo_contact_cap_version') !== CULTURINFO_CONTACT_CAP_VERSION) {
        culturinfo_contact_grant_capabilities();
    }
}
add_action('init', 'culturinfo_contact_maybe_upgrade_capabilities', 5);

function culturinfo_contact_register_post_type() {
    register_post_type('culturinfo_message', array(
        'labels' => array(
            'name'          => 'Mensajes',
            'singular_name' => 'Mensaje',
            'edit_item'     => 'Ver mensaje',
            'search_items'  => 'Buscar mensajes',
            'not_found'     => 'No hay mensajes',
        ),
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => false,
        'menu_icon'           => 'dashicons-email-alt',
        'menu_position'       => 25,
        'supports'            => array('title', 'editor'),
        'capability_type'     => array('culturinfo_message', 'culturinfo_messages'),
        'map_meta_cap'        => true,
        'capabilities'        => array(
            'edit_post'              => 'edit_culturinfo_message',
            'read_post'              => 'read_culturinfo_message',
            'delete_post'            => 'delete_culturinfo_message',
            'edit_posts'             => 'edit_culturinfo_messages',
            'edit_others_posts'      => 'edit_others_culturinfo_messages',
            'publish_posts'          => 'do_not_allow',
            'read_private_posts'     => 'read_private_culturinfo_messages',
            'delete_posts'           => 'delete_culturinfo_messages',
            'delete_private_posts'   => 'delete_private_culturinfo_messages',
            'delete_published_posts' => 'do_not_allow',
            'delete_others_posts'    => 'delete_others_culturinfo_messages',
            'edit_private_posts'     => 'edit_private_culturinfo_messages',
            'edit_published_posts'   => 'do_not_allow',
            'create_posts'           => 'do_not_allow',
        ),
        'has_archive'         => false,
        'rewrite'             => false,
        'exclude_from_search' => true,
    ));
}
add_action('init', 'culturinfo_contact_register_post_type');

function culturinfo_contact_activate() {
    culturinfo_contact_register_post_type();
    culturinfo_contact_grant_capabilities();
    if (!wp_next_scheduled('culturinfo_contact_cleanup')) {
        wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'culturinfo_contact_cleanup');
    }
}
register_activation_hook(__FILE__, 'culturinfo_contact_activate');

function culturinfo_contact_deactivate() {
    wp_clear_scheduled_hook('culturinfo_contact_cleanup');
}
register_deactivation_hook(__FILE__, 'culturinfo_contact_deactivate');

function culturinfo_contact_ensure_schedule() {
    if (!wp_next_scheduled('culturinfo_contact_cleanup')) {
        wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'culturinfo_contact_cleanup');
    }
}
add_action('init', 'culturinfo_contact_ensure_schedule');

function culturinfo_contact_cleanup() {
    $old_messages = get_posts(array(
        'post_type'      => 'culturinfo_message',
        'post_status'    => 'private',
        'posts_per_page' => 100,
        'fields'         => 'ids',
        'date_query'     => array(array('before' => '90 days ago', 'inclusive' => true)),
        'orderby'        => 'ID',
        'order'          => 'ASC',
    ));
    foreach ($old_messages as $message_id) {
        wp_trash_post($message_id);
    }
}
add_action('culturinfo_contact_cleanup', 'culturinfo_contact_cleanup');

function culturinfo_contact_fingerprint() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    if (!empty($_SERVER['HTTP_CF_RAY']) && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
    }
    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
    return substr(hash_hmac('sha256', $ip . '|' . $agent, wp_salt('auth')), 0, 40);
}

function culturinfo_contact_rate_allowed() {
    $key = 'culturinfo_contact_' . culturinfo_contact_fingerprint();
    $count = absint(get_transient($key));
    if ($count >= 3) {
        return false;
    }
    set_transient($key, $count + 1, HOUR_IN_SECONDS);
    return true;
}

function culturinfo_contact_status_message() {
    $status = isset($_GET['contacto']) ? sanitize_key(wp_unslash($_GET['contacto'])) : '';
    if ($status === 'enviado') {
        return '<div class="contact-notice contact-notice--success" role="status">Gracias. El equipo editorial recibió tu mensaje.</div>';
    }
    $messages = array(
        'incompleto' => 'Revisa los campos obligatorios e inténtalo nuevamente.',
        'limite'     => 'Se alcanzó el límite temporal de envíos. Inténtalo nuevamente más tarde.',
        'error'      => 'No fue posible guardar el mensaje. Inténtalo nuevamente.',
    );
    return isset($messages[$status]) ? '<div class="contact-notice contact-notice--error" role="alert">' . esc_html($messages[$status]) . '</div>' : '';
}

function culturinfo_contact_form_shortcode() {
    $types = culturinfo_contact_types();
    ob_start();
    echo wp_kses_post(culturinfo_contact_status_message());
    ?>
    <form class="culturinfo-contact-form" method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="culturinfo_contact_submit">
        <input type="hidden" name="redirect_to" value="<?php echo esc_url(get_permalink()); ?>">
        <?php wp_nonce_field('culturinfo_contact_submit', 'culturinfo_contact_nonce'); ?>
        <div class="contact-honeypot" aria-hidden="true"><label>Website<input type="text" name="contact_website" value="" tabindex="-1" autocomplete="off"></label></div>
        <p class="contact-form-intro">Los campos marcados con * son obligatorios. No envíes contraseñas, documentos de identidad ni información confidencial.</p>
        <div class="contact-form-grid">
            <label><span>Nombre *</span><input type="text" name="contact_name" maxlength="120" autocomplete="name" required></label>
            <label><span>Correo electrónico *</span><input type="email" name="contact_email" maxlength="254" autocomplete="email" required></label>
            <label class="contact-form-wide"><span>Motivo *</span><select name="contact_type" required><option value="">Selecciona una opción</option><?php foreach ($types as $key => $label) : ?><option value="<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></option><?php endforeach; ?></select></label>
            <label class="contact-form-wide"><span>Asunto *</span><input type="text" name="contact_subject" maxlength="180" required></label>
            <label class="contact-form-wide"><span>Mensaje *</span><textarea name="contact_message" maxlength="5000" rows="9" required></textarea></label>
            <label class="contact-consent contact-form-wide"><input type="checkbox" name="contact_consent" value="1" required><span>Acepto que Culturinfo utilice estos datos para responder mi solicitud y los conserve por un máximo de 90 días.</span></label>
        </div>
        <button class="contact-submit" type="submit">Enviar mensaje</button>
    </form>
    <?php
    return (string) ob_get_clean();
}
add_shortcode('culturinfo_contact_form', 'culturinfo_contact_form_shortcode');

function culturinfo_contact_redirect($status) {
    $fallback = home_url('/contacto/');
    $requested = isset($_POST['redirect_to']) ? esc_url_raw(wp_unslash($_POST['redirect_to'])) : $fallback;
    $url = wp_validate_redirect($requested, $fallback);
    wp_safe_redirect(add_query_arg('contacto', sanitize_key($status), $url) . '#formulario-contacto');
    exit;
}

function culturinfo_contact_submit() {
    if (!isset($_POST['culturinfo_contact_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['culturinfo_contact_nonce'])), 'culturinfo_contact_submit')) {
        culturinfo_contact_redirect('error');
    }
    if (!empty($_POST['contact_website'])) {
        culturinfo_contact_redirect('enviado');
    }
    if (!culturinfo_contact_rate_allowed()) {
        culturinfo_contact_redirect('limite');
    }

    $types = culturinfo_contact_types();
    $name = sanitize_text_field(wp_unslash($_POST['contact_name'] ?? ''));
    $email = sanitize_email(wp_unslash($_POST['contact_email'] ?? ''));
    $type = sanitize_key(wp_unslash($_POST['contact_type'] ?? ''));
    $subject = sanitize_text_field(wp_unslash($_POST['contact_subject'] ?? ''));
    $message = sanitize_textarea_field(wp_unslash($_POST['contact_message'] ?? ''));
    $consent = !empty($_POST['contact_consent']);

    if ($name === '' || strlen($name) > 120 || !is_email($email) || !isset($types[$type]) || $subject === '' || strlen($subject) > 180 || $message === '' || strlen($message) > 5000 || !$consent) {
        culturinfo_contact_redirect('incompleto');
    }

    $message_id = wp_insert_post(array(
        'post_type'    => 'culturinfo_message',
        'post_status'  => 'private',
        'post_title'   => $subject,
        'post_content' => $message,
    ), true);
    if (is_wp_error($message_id)) {
        culturinfo_contact_redirect('error');
    }

    update_post_meta($message_id, '_culturinfo_contact_name', $name);
    update_post_meta($message_id, '_culturinfo_contact_email', $email);
    update_post_meta($message_id, '_culturinfo_contact_type', $type);
    update_post_meta($message_id, '_culturinfo_contact_received', current_time('mysql'));

    $mail_subject = '[Culturinfo] ' . $types[$type] . ': ' . $subject;
    $mail_body = "Nombre: {$name}\nCorreo: {$email}\nMotivo: {$types[$type]}\n\n{$message}\n";
    wp_mail(get_option('admin_email'), $mail_subject, $mail_body, array('Reply-To: ' . $email));
    culturinfo_contact_redirect('enviado');
}
add_action('admin_post_nopriv_culturinfo_contact_submit', 'culturinfo_contact_submit');
add_action('admin_post_culturinfo_contact_submit', 'culturinfo_contact_submit');

function culturinfo_contact_details_box() {
    add_meta_box('culturinfo-contact-details', 'Datos del remitente', 'culturinfo_contact_details_box_html', 'culturinfo_message', 'side', 'high');
}
add_action('add_meta_boxes_culturinfo_message', 'culturinfo_contact_details_box');

function culturinfo_contact_details_box_html($post) {
    $type = get_post_meta($post->ID, '_culturinfo_contact_type', true);
    $types = culturinfo_contact_types();
    echo '<p><strong>Nombre</strong><br>' . esc_html(get_post_meta($post->ID, '_culturinfo_contact_name', true)) . '</p>';
    echo '<p><strong>Correo</strong><br><a href="mailto:' . esc_attr(get_post_meta($post->ID, '_culturinfo_contact_email', true)) . '">' . esc_html(get_post_meta($post->ID, '_culturinfo_contact_email', true)) . '</a></p>';
    echo '<p><strong>Motivo</strong><br>' . esc_html($types[$type] ?? $type) . '</p>';
    echo '<p><strong>Recibido</strong><br>' . esc_html(get_post_meta($post->ID, '_culturinfo_contact_received', true)) . '</p>';
    echo '<p class="description">Los mensajes pasan a la papelera después de 90 días.</p>';
}

function culturinfo_contact_columns($columns) {
    return array(
        'cb'           => $columns['cb'],
        'title'        => 'Asunto',
        'contact_name' => 'Remitente',
        'contact_type' => 'Motivo',
        'date'         => 'Recibido',
    );
}
add_filter('manage_culturinfo_message_posts_columns', 'culturinfo_contact_columns');

function culturinfo_contact_column_content($column, $post_id) {
    if ($column === 'contact_name') {
        echo esc_html(get_post_meta($post_id, '_culturinfo_contact_name', true));
    }
    if ($column === 'contact_type') {
        $type = get_post_meta($post_id, '_culturinfo_contact_type', true);
        $types = culturinfo_contact_types();
        echo esc_html($types[$type] ?? $type);
    }
}
add_action('manage_culturinfo_message_posts_custom_column', 'culturinfo_contact_column_content', 10, 2);
