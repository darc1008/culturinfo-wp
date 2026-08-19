<?php

if (!defined('ABSPATH')) {
    exit;
}

function culturinfo_security_comment_form_fields() {
    echo '<div class="culturinfo-comment-honeypot" aria-hidden="true">'
        . '<label>Website<input type="text" name="culturinfo_comment_website" value="" tabindex="-1" autocomplete="off"></label>'
        . '</div>';
    echo culturinfo_security_turnstile_widget('culturinfo_comment'); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
}
add_action('comment_form_after_fields', 'culturinfo_security_comment_form_fields');
add_action('comment_form_logged_in_after', 'culturinfo_security_comment_form_fields');

function culturinfo_security_preprocess_comment($commentdata) {
    $comment_type = isset($commentdata['comment_type']) ? (string) $commentdata['comment_type'] : 'comment';
    if ($comment_type !== '' && $comment_type !== 'comment') {
        return $commentdata;
    }

    if (is_user_logged_in() && current_user_can('moderate_comments')) {
        return $commentdata;
    }

    if (!empty($_POST['culturinfo_comment_website'])) {
        wp_die('Gracias. Tu comentario será revisado.', 'Comentario recibido', array('response' => 200));
    }

    $identity = culturinfo_security_client_identity('comment_ip');
    $attempt = culturinfo_security_rate_limit('comment_attempt_15m', $identity, 10, 15 * MINUTE_IN_SECONDS);
    if (!$attempt['allowed']) {
        culturinfo_security_rate_block(
            'Se alcanzó el límite temporal de comentarios. Inténtalo nuevamente más tarde.',
            $attempt['retry_after']
        );
    }

    $verified = culturinfo_security_turnstile_verify('culturinfo_comment');
    if (is_wp_error($verified)) {
        wp_die(esc_html($verified->get_error_message()), 'Verificación requerida', array('response' => 403));
    }

    $short = culturinfo_security_rate_limit('comment_15m', $identity, 5, 15 * MINUTE_IN_SECONDS);
    $daily = culturinfo_security_rate_limit('comment_day', $identity, 20, DAY_IN_SECONDS);
    $email = strtolower(trim((string) ($commentdata['comment_author_email'] ?? '')));
    $email_identity = culturinfo_security_hash_identity('comment_email', $email);
    $email_status = culturinfo_security_rate_limit('comment_email', $email_identity, 5, HOUR_IN_SECONDS);

    if (!$short['allowed'] || !$daily['allowed'] || !$email_status['allowed']) {
        culturinfo_security_rate_block(
            'Se alcanzó el límite temporal de comentarios. Inténtalo nuevamente más tarde.',
            max($short['retry_after'], $daily['retry_after'], $email_status['retry_after'])
        );
    }

    $commentdata['comment_approved'] = 0;
    return $commentdata;
}
add_filter('preprocess_comment', 'culturinfo_security_preprocess_comment', 5);

function culturinfo_security_limit_comment_notifications($emails) {
    return culturinfo_security_notification_allowed() ? $emails : array();
}
add_filter('comment_notification_recipients', 'culturinfo_security_limit_comment_notifications');
add_filter('comment_moderation_recipients', 'culturinfo_security_limit_comment_notifications');
