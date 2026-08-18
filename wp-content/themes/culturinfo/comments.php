<?php
/**
 * Comentarios de una noticia.
 *
 * @package Culturinfo
 */

if (post_password_required()) {
    return;
}
?>
<section id="comments" class="comments-area" aria-label="<?php esc_attr_e('Comentarios', 'culturinfo'); ?>">
    <?php if (have_comments()) : ?>
        <header class="comments-heading">
            <span class="comments-kicker"><?php esc_html_e('La conversación', 'culturinfo'); ?></span>
            <h2 id="comments-title">
                <?php
                printf(
                    esc_html(_n('%s comentario', '%s comentarios', get_comments_number(), 'culturinfo')),
                    esc_html(number_format_i18n(get_comments_number()))
                );
                ?>
            </h2>
        </header>

        <ol class="comment-list">
            <?php
            wp_list_comments(array(
                'avatar_size' => 56,
                'style'       => 'ol',
                'short_ping'  => true,
            ));
            ?>
        </ol>

        <?php
        the_comments_pagination(array(
            'prev_text' => esc_html__('← Comentarios anteriores', 'culturinfo'),
            'next_text' => esc_html__('Comentarios siguientes →', 'culturinfo'),
        ));
        ?>
    <?php endif; ?>

    <?php if (!comments_open() && get_comments_number()) : ?>
        <p class="comments-closed"><?php esc_html_e('La conversación de esta noticia está cerrada.', 'culturinfo'); ?></p>
    <?php endif; ?>

    <?php
    comment_form(array(
        'title_reply'          => esc_html__('Participa en la conversación', 'culturinfo'),
        'title_reply_before'   => '<h2 id="reply-title" class="comment-reply-title">',
        'title_reply_after'    => '</h2>',
        'comment_notes_before' => '<p class="comment-policy">' . esc_html__('Tu comentario será revisado antes de publicarse. Evita datos personales, insultos y enlaces no relacionados.', 'culturinfo') . '</p>',
        'label_submit'         => esc_html__('Enviar para revisión', 'culturinfo'),
        'class_submit'         => 'comment-submit',
    ));
    ?>
</section>
