<?php
/**
 * Plugin Name: Culturinfo — Programación editorial
 * Description: Publica semanalmente los borradores en un día predeterminado, con horarios distintos por sección.
 * Version: 1.1.0
 * Author: Horizonte Cultural
 * Text Domain: culturinfo-publishing
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CULTURINFO_PUBLISHING_VERSION', '1.1.0');
define('CULTURINFO_PUBLISHING_OPTION', 'culturinfo_publishing_settings');
define('CULTURINFO_PUBLISHING_RUNS_OPTION', 'culturinfo_publishing_last_runs');
define('CULTURINFO_PUBLISHING_LOG_OPTION', 'culturinfo_publishing_log');
define('CULTURINFO_PUBLISHING_CRON', 'culturinfo_publishing_tick');
define('CULTURINFO_PUBLISHING_APPROVAL_META', '_culturinfo_ready_to_publish');
define('CULTURINFO_PUBLISHING_CAP_VERSION', '1');

function culturinfo_publishing_capability() {
    return apply_filters('culturinfo_publishing_capability', 'manage_culturinfo_publishing');
}

function culturinfo_publishing_grant_capability() {
    foreach (array('administrator', 'editor') as $role_name) {
        $role = get_role($role_name);
        if ($role && !$role->has_cap(culturinfo_publishing_capability())) {
            $role->add_cap(culturinfo_publishing_capability());
        }
    }
    update_option('culturinfo_publishing_cap_version', CULTURINFO_PUBLISHING_CAP_VERSION, false);
}

function culturinfo_publishing_maybe_upgrade_capability() {
    if (get_option('culturinfo_publishing_cap_version') !== CULTURINFO_PUBLISHING_CAP_VERSION) {
        culturinfo_publishing_grant_capability();
    }
}
add_action('init', 'culturinfo_publishing_maybe_upgrade_capability', 5);

function culturinfo_publishing_days() {
    return array(
        0 => 'Domingo',
        1 => 'Lunes',
        2 => 'Martes',
        3 => 'Miércoles',
        4 => 'Jueves',
        5 => 'Viernes',
        6 => 'Sábado',
    );
}

function culturinfo_publishing_defaults() {
    return array(
        'enabled'      => 0,
        'default_day'  => 6,
        'default_time' => '08:00',
        'overrides'    => array(),
    );
}

function culturinfo_publishing_settings() {
    $saved = get_option(CULTURINFO_PUBLISHING_OPTION, array());
    if (!is_array($saved)) {
        $saved = array();
    }
    $settings = wp_parse_args($saved, culturinfo_publishing_defaults());
    if (!is_array($settings['overrides'])) {
        $settings['overrides'] = array();
    }
    return $settings;
}

function culturinfo_publishing_sanitize_time($value, $fallback = '08:00') {
    $value = sanitize_text_field((string) $value);
    if (!preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $value)) {
        return $fallback;
    }
    return $value;
}

function culturinfo_publishing_cron_schedules($schedules) {
    $schedules['culturinfo_every_15_minutes'] = array(
        'interval' => 15 * MINUTE_IN_SECONDS,
        'display'  => 'Cada 15 minutos — Culturinfo',
    );
    return $schedules;
}
add_filter('cron_schedules', 'culturinfo_publishing_cron_schedules');

function culturinfo_publishing_schedule_cron() {
    if (!wp_next_scheduled(CULTURINFO_PUBLISHING_CRON)) {
        wp_schedule_event(time() + 60, 'culturinfo_every_15_minutes', CULTURINFO_PUBLISHING_CRON);
    }
}
add_action('init', 'culturinfo_publishing_schedule_cron');

function culturinfo_publishing_activate() {
    culturinfo_publishing_grant_capability();
    culturinfo_publishing_schedule_cron();
}
register_activation_hook(__FILE__, 'culturinfo_publishing_activate');

function culturinfo_publishing_deactivate() {
    wp_clear_scheduled_hook(CULTURINFO_PUBLISHING_CRON);
}
register_deactivation_hook(__FILE__, 'culturinfo_publishing_deactivate');

function culturinfo_publishing_register_approval_meta() {
    register_post_meta('post', CULTURINFO_PUBLISHING_APPROVAL_META, array(
        'type'              => 'boolean',
        'single'            => true,
        'show_in_rest'      => false,
        'sanitize_callback' => 'rest_sanitize_boolean',
        'auth_callback'     => function () {
            return current_user_can(culturinfo_publishing_capability());
        },
    ));
}
add_action('init', 'culturinfo_publishing_register_approval_meta');

function culturinfo_publishing_add_approval_box() {
    if (!current_user_can(culturinfo_publishing_capability())) {
        return;
    }
    add_meta_box(
        'culturinfo-publishing-approval',
        'Revisión editorial',
        'culturinfo_publishing_approval_box',
        'post',
        'side',
        'high'
    );
}
add_action('add_meta_boxes_post', 'culturinfo_publishing_add_approval_box');

function culturinfo_publishing_approval_box($post) {
    wp_nonce_field('culturinfo_publishing_approval', 'culturinfo_publishing_approval_nonce');
    $approved = '1' === get_post_meta($post->ID, CULTURINFO_PUBLISHING_APPROVAL_META, true);
    ?>
    <p><label><input type="checkbox" name="culturinfo_ready_to_publish" value="1" <?php checked($approved); ?>> <strong>Lista para publicación automática</strong></label></p>
    <p class="description">Solo las noticias aprobadas se publicarán en el próximo horario. Si un autor modifica después el borrador, deberá aprobarse nuevamente.</p>
    <?php
}

function culturinfo_publishing_save_approval($post_id, $post) {
    if (!$post || $post->post_type !== 'post' || (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || wp_is_post_revision($post_id)) {
        return;
    }

    $can_approve = current_user_can(culturinfo_publishing_capability()) && current_user_can('edit_post', $post_id);
    $valid_nonce = isset($_POST['culturinfo_publishing_approval_nonce'])
        && wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['culturinfo_publishing_approval_nonce'])), 'culturinfo_publishing_approval');

    if ($can_approve && $valid_nonce) {
        if (!empty($_POST['culturinfo_ready_to_publish'])) {
            update_post_meta($post_id, CULTURINFO_PUBLISHING_APPROVAL_META, '1');
        } else {
            delete_post_meta($post_id, CULTURINFO_PUBLISHING_APPROVAL_META);
        }
        return;
    }

    if ($post->post_status === 'draft' && is_user_logged_in() && !$can_approve) {
        delete_post_meta($post_id, CULTURINFO_PUBLISHING_APPROVAL_META);
    }
}
add_action('save_post_post', 'culturinfo_publishing_save_approval', 20, 2);

function culturinfo_publishing_post_columns($columns) {
    $columns['culturinfo_ready'] = 'Revisión';
    return $columns;
}
add_filter('manage_post_posts_columns', 'culturinfo_publishing_post_columns');

function culturinfo_publishing_post_column($column, $post_id) {
    if ($column !== 'culturinfo_ready') {
        return;
    }
    echo '1' === get_post_meta($post_id, CULTURINFO_PUBLISHING_APPROVAL_META, true)
        ? '<span style="color:#087a36;font-weight:700">Lista</span>'
        : '<span style="color:#8a4b00">Pendiente</span>';
}
add_action('manage_post_posts_custom_column', 'culturinfo_publishing_post_column', 10, 2);

function culturinfo_publishing_schedule_definitions($settings = null) {
    $settings = is_array($settings) ? $settings : culturinfo_publishing_settings();
    $definitions = array(
        'default' => array(
            'label'   => 'Programación general',
            'day'     => absint($settings['default_day']),
            'time'    => culturinfo_publishing_sanitize_time($settings['default_time']),
            'term_id' => 0,
        ),
    );

    foreach ($settings['overrides'] as $term_id => $override) {
        $term_id = absint($term_id);
        if (!$term_id || empty($override['enabled']) || !term_exists($term_id, 'category')) {
            continue;
        }
        $term = get_term($term_id, 'category');
        $definitions['section_' . $term_id] = array(
            'label'   => $term && !is_wp_error($term) ? 'Sección: ' . $term->name : 'Sección #' . $term_id,
            'day'     => isset($override['day']) ? absint($override['day']) : absint($settings['default_day']),
            'time'    => culturinfo_publishing_sanitize_time($override['time'] ?? '', $settings['default_time']),
            'term_id' => $term_id,
        );
    }
    return $definitions;
}

function culturinfo_publishing_post_schedule_key($post_id, $settings = null) {
    $settings = is_array($settings) ? $settings : culturinfo_publishing_settings();
    $categories = get_the_category($post_id);
    if ($categories) {
        $primary_id = absint($categories[0]->term_id);
        if (!empty($settings['overrides'][$primary_id]['enabled'])) {
            return 'section_' . $primary_id;
        }
    }
    return 'default';
}

function culturinfo_publishing_latest_occurrence($day, $time, $now = null) {
    $now = $now instanceof DateTimeImmutable ? $now : current_datetime();
    $day = min(6, max(0, absint($day)));
    list($hour, $minute) = array_map('intval', explode(':', culturinfo_publishing_sanitize_time($time)));
    $days_back = ((int) $now->format('w') - $day + 7) % 7;
    $occurrence = $now->setTime($hour, $minute, 0)->modify('-' . $days_back . ' days');
    if ($days_back === 0 && $occurrence > $now) {
        $occurrence = $occurrence->modify('-7 days');
    }
    return $occurrence;
}

function culturinfo_publishing_next_occurrence($day, $time, $now = null) {
    $now = $now instanceof DateTimeImmutable ? $now : current_datetime();
    $latest = culturinfo_publishing_latest_occurrence($day, $time, $now);
    return $latest > $now ? $latest : $latest->modify('+7 days');
}

function culturinfo_publishing_schedule_is_due($key, $definition, $last_runs, $now = null) {
    $latest = culturinfo_publishing_latest_occurrence($definition['day'], $definition['time'], $now);
    if (empty($last_runs[$key])) {
        return true;
    }
    $last = DateTimeImmutable::createFromFormat('Y-m-d H:i:s', $last_runs[$key], wp_timezone());
    return !$last || $last < $latest;
}

function culturinfo_publishing_draft_groups($settings, $definitions) {
    $groups = array_fill_keys(array_keys($definitions), array());
    $draft_ids = get_posts(array(
        'post_type'              => 'post',
        'post_status'            => 'draft',
        'posts_per_page'         => -1,
        'fields'                 => 'ids',
        'orderby'                => 'ID',
        'order'                  => 'ASC',
        'no_found_rows'          => true,
        'ignore_sticky_posts'    => true,
        'update_post_meta_cache' => false,
        'update_post_term_cache' => true,
        'meta_key'               => CULTURINFO_PUBLISHING_APPROVAL_META,
        'meta_value'             => '1',
    ));
    foreach ($draft_ids as $post_id) {
        $key = culturinfo_publishing_post_schedule_key($post_id, $settings);
        if (!isset($groups[$key])) {
            $key = 'default';
        }
        $groups[$key][] = absint($post_id);
    }
    return $groups;
}

function culturinfo_publishing_publish_posts($post_ids) {
    $published = 0;
    $errors = array();
    foreach ($post_ids as $post_id) {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post' || $post->post_status !== 'draft') {
            continue;
        }
        $result = wp_update_post(array(
            'ID'            => $post_id,
            'post_status'   => 'publish',
            'post_date'     => current_time('mysql'),
            'post_date_gmt' => current_time('mysql', true),
            'edit_date'     => true,
        ), true);
        if (is_wp_error($result)) {
            $errors[$post_id] = $result->get_error_message();
        } else {
            $published++;
        }
    }
    return array('published' => $published, 'errors' => $errors);
}

function culturinfo_publishing_add_log($key, $definition, $result, $trigger) {
    $log = get_option(CULTURINFO_PUBLISHING_LOG_OPTION, array());
    if (!is_array($log)) {
        $log = array();
    }
    array_unshift($log, array(
        'time'      => current_time('mysql'),
        'key'       => sanitize_key($key),
        'label'     => sanitize_text_field($definition['label']),
        'trigger'   => $trigger === 'manual' ? 'manual' : 'automatic',
        'published' => absint($result['published']),
        'errors'    => count($result['errors']),
    ));
    update_option(CULTURINFO_PUBLISHING_LOG_OPTION, array_slice($log, 0, 30), false);
}

function culturinfo_publishing_process($force = false) {
    $settings = culturinfo_publishing_settings();
    if (!$force && empty($settings['enabled'])) {
        return array('published' => 0, 'errors' => 0, 'schedules' => 0);
    }
    if (get_transient('culturinfo_publishing_lock')) {
        return array('published' => 0, 'errors' => 0, 'schedules' => 0, 'locked' => true);
    }
    set_transient('culturinfo_publishing_lock', 1, 5 * MINUTE_IN_SECONDS);

    $definitions = culturinfo_publishing_schedule_definitions($settings);
    $groups = culturinfo_publishing_draft_groups($settings, $definitions);
    $last_runs = get_option(CULTURINFO_PUBLISHING_RUNS_OPTION, array());
    if (!is_array($last_runs)) {
        $last_runs = array();
    }
    $now = current_datetime();
    $summary = array('published' => 0, 'errors' => 0, 'schedules' => 0);

    foreach ($definitions as $key => $definition) {
        if (!$force && !culturinfo_publishing_schedule_is_due($key, $definition, $last_runs, $now)) {
            continue;
        }
        $result = culturinfo_publishing_publish_posts($groups[$key] ?? array());
        $summary['published'] += $result['published'];
        $summary['errors'] += count($result['errors']);
        $summary['schedules']++;
        culturinfo_publishing_add_log($key, $definition, $result, $force ? 'manual' : 'automatic');
        if (!$force) {
            $last_runs[$key] = $now->format('Y-m-d H:i:s');
        }
    }

    if (!$force) {
        update_option(CULTURINFO_PUBLISHING_RUNS_OPTION, $last_runs, false);
    }
    delete_transient('culturinfo_publishing_lock');
    return $summary;
}

function culturinfo_publishing_cron_callback() {
    culturinfo_publishing_process(false);
}
add_action(CULTURINFO_PUBLISHING_CRON, 'culturinfo_publishing_cron_callback');

function culturinfo_publishing_admin_menu() {
    add_menu_page(
        'Programación editorial',
        'Programación',
        culturinfo_publishing_capability(),
        'culturinfo-publishing',
        'culturinfo_publishing_admin_page',
        'dashicons-calendar-alt',
        24
    );
}
add_action('admin_menu', 'culturinfo_publishing_admin_menu');

function culturinfo_publishing_day_select($name, $selected) {
    echo '<select name="' . esc_attr($name) . '">';
    foreach (culturinfo_publishing_days() as $number => $label) {
        echo '<option value="' . esc_attr($number) . '" ' . selected(absint($selected), $number, false) . '>' . esc_html($label) . '</option>';
    }
    echo '</select>';
}

function culturinfo_publishing_admin_page() {
    if (!current_user_can(culturinfo_publishing_capability())) {
        wp_die(esc_html__('No tienes permisos para administrar esta programación.', 'culturinfo-publishing'));
    }
    $settings = culturinfo_publishing_settings();
    $categories = get_categories(array('hide_empty' => false, 'orderby' => 'name'));
    $definitions = culturinfo_publishing_schedule_definitions($settings);
    $groups = culturinfo_publishing_draft_groups($settings, $definitions);
    $draft_count = array_sum(array_map('count', $groups));
    $all_draft_count = (int) wp_count_posts('post')->draft;
    $waiting_review = max(0, $all_draft_count - $draft_count);
    $category_draft_counts = array();
    foreach ($groups as $post_ids) {
        foreach ($post_ids as $post_id) {
            $post_categories = get_the_category($post_id);
            if ($post_categories) {
                $primary_id = absint($post_categories[0]->term_id);
                $category_draft_counts[$primary_id] = ($category_draft_counts[$primary_id] ?? 0) + 1;
            }
        }
    }
    $log = get_option(CULTURINFO_PUBLISHING_LOG_OPTION, array());
    $days = culturinfo_publishing_days();
    ?>
    <div class="wrap culturinfo-publishing-wrap">
        <h1>Programación editorial</h1>
        <p>Publica en bloque únicamente las noticias guardadas como <strong>borrador</strong> y marcadas como <strong>Lista para publicación automática</strong>. La fecha pública será el momento real en que el proceso las publique.</p>
        <?php if (isset($_GET['saved'])) : ?><div class="notice notice-success is-dismissible"><p>La programación fue guardada. Los cambios aplicarán desde la próxima fecha configurada.</p></div><?php endif; ?>
        <?php if (isset($_GET['run'])) : ?><div class="notice notice-success is-dismissible"><p>Proceso manual terminado: <strong><?php echo esc_html(absint($_GET['published'] ?? 0)); ?></strong> noticias publicadas.</p></div><?php endif; ?>

        <div class="culturinfo-publishing-summary">
            <div><span>Estado</span><strong class="<?php echo empty($settings['enabled']) ? 'is-off' : 'is-on'; ?>"><?php echo empty($settings['enabled']) ? 'Desactivado' : 'Activo'; ?></strong></div>
            <div><span>Listas para publicar</span><strong><?php echo esc_html(number_format_i18n($draft_count)); ?></strong></div>
            <div><span>Esperando revisión</span><strong><?php echo esc_html(number_format_i18n($waiting_review)); ?></strong></div>
            <div><span>Zona horaria</span><strong><?php echo esc_html(wp_timezone_string()); ?></strong></div>
            <div><span>Frecuencia de revisión</span><strong>Cada 15 minutos</strong></div>
        </div>

        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
            <input type="hidden" name="action" value="culturinfo_publishing_save">
            <?php wp_nonce_field('culturinfo_publishing_save'); ?>
            <section class="culturinfo-publishing-panel">
                <h2>Programación general</h2>
                <label class="culturinfo-publishing-toggle"><input type="checkbox" name="enabled" value="1" <?php checked(!empty($settings['enabled'])); ?>> Activar publicación automática semanal</label>
                <div class="culturinfo-publishing-fields">
                    <label><span>Día por defecto</span><?php culturinfo_publishing_day_select('default_day', $settings['default_day']); ?></label>
                    <label><span>Hora</span><input type="time" name="default_time" value="<?php echo esc_attr($settings['default_time']); ?>" required></label>
                </div>
                <?php $next = culturinfo_publishing_next_occurrence($settings['default_day'], $settings['default_time']); ?>
                <p class="description">Próxima fecha general: <strong><?php echo esc_html(wp_date('l, j \d\e F \d\e Y — H:i', $next->getTimestamp(), wp_timezone())); ?></strong>.</p>
            </section>

            <section class="culturinfo-publishing-panel">
                <h2>Excepciones por sección</h2>
                <p>Activa una fila cuando una sección deba publicarse en un día u hora diferente. Las demás seguirán la programación general.</p>
                <table class="widefat striped culturinfo-publishing-table">
                    <thead><tr><th>Usar excepción</th><th>Sección</th><th>Día</th><th>Hora</th><th>Borradores</th></tr></thead>
                    <tbody>
                    <?php foreach ($categories as $category) : $override = $settings['overrides'][$category->term_id] ?? array(); ?>
                        <tr>
                            <td><input type="checkbox" name="overrides[<?php echo esc_attr($category->term_id); ?>][enabled]" value="1" <?php checked(!empty($override['enabled'])); ?> aria-label="Usar excepción para <?php echo esc_attr($category->name); ?>"></td>
                            <td><strong><?php echo esc_html($category->name); ?></strong></td>
                            <td><?php culturinfo_publishing_day_select('overrides[' . $category->term_id . '][day]', $override['day'] ?? $settings['default_day']); ?></td>
                            <td><input type="time" name="overrides[<?php echo esc_attr($category->term_id); ?>][time]" value="<?php echo esc_attr($override['time'] ?? $settings['default_time']); ?>"></td>
                            <td><?php echo esc_html(number_format_i18n($category_draft_counts[$category->term_id] ?? 0)); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p class="description">Si una noticia tiene varias categorías, se toma la primera sección, igual que en la portada del periódico.</p>
            </section>
            <?php submit_button('Guardar programación'); ?>
        </form>

        <section class="culturinfo-publishing-panel culturinfo-publishing-manual">
            <h2>Publicación manual</h2>
            <p>Publica inmediatamente todos los borradores aprobados, sin esperar el día configurado. Los borradores pendientes de revisión no se modificarán.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('¿Publicar ahora todos los borradores? Esta acción cambiará su estado a Publicado.');">
                <input type="hidden" name="action" value="culturinfo_publishing_run_now">
                <?php wp_nonce_field('culturinfo_publishing_run_now'); ?>
                <?php submit_button('Publicar borradores aprobados ahora', 'secondary', 'submit', false); ?>
            </form>
        </section>

        <section class="culturinfo-publishing-panel">
            <h2>Historial de ejecuciones</h2>
            <table class="widefat striped"><thead><tr><th>Fecha</th><th>Programación</th><th>Origen</th><th>Publicadas</th><th>Errores</th></tr></thead><tbody>
            <?php if (empty($log)) : ?><tr><td colspan="5">Todavía no hay ejecuciones.</td></tr><?php else : foreach (array_slice($log, 0, 15) as $entry) : ?>
                <tr><td><?php echo esc_html($entry['time']); ?></td><td><?php echo esc_html($entry['label']); ?></td><td><?php echo esc_html($entry['trigger'] === 'manual' ? 'Manual' : 'Automática'); ?></td><td><?php echo esc_html(absint($entry['published'])); ?></td><td><?php echo esc_html(absint($entry['errors'])); ?></td></tr>
            <?php endforeach; endif; ?></tbody></table>
        </section>
    </div>
    <?php
}

function culturinfo_publishing_save_settings() {
    if (!current_user_can(culturinfo_publishing_capability())) {
        wp_die(esc_html__('No tienes permisos para guardar esta programación.', 'culturinfo-publishing'));
    }
    check_admin_referer('culturinfo_publishing_save');
    $default_day = isset($_POST['default_day']) ? absint($_POST['default_day']) : 6;
    $default_day = min(6, $default_day);
    $default_time = culturinfo_publishing_sanitize_time(wp_unslash($_POST['default_time'] ?? '08:00'));
    $submitted = isset($_POST['overrides']) && is_array($_POST['overrides']) ? wp_unslash($_POST['overrides']) : array();
    $overrides = array();
    foreach ($submitted as $term_id => $override) {
        $term_id = absint($term_id);
        if (!$term_id || !term_exists($term_id, 'category') || !is_array($override)) {
            continue;
        }
        $overrides[$term_id] = array(
            'enabled' => empty($override['enabled']) ? 0 : 1,
            'day'     => min(6, absint($override['day'] ?? $default_day)),
            'time'    => culturinfo_publishing_sanitize_time($override['time'] ?? $default_time, $default_time),
        );
    }
    update_option(CULTURINFO_PUBLISHING_OPTION, array(
        'enabled'      => empty($_POST['enabled']) ? 0 : 1,
        'default_day'  => $default_day,
        'default_time' => $default_time,
        'overrides'    => $overrides,
    ), false);

    // Guardar una nueva configuración inicia un ciclo futuro; evita publicar
    // inmediatamente por una fecha anterior al cambio recién realizado.
    $now = current_time('mysql');
    $definitions = culturinfo_publishing_schedule_definitions(culturinfo_publishing_settings());
    update_option(CULTURINFO_PUBLISHING_RUNS_OPTION, array_fill_keys(array_keys($definitions), $now), false);
    wp_safe_redirect(admin_url('admin.php?page=culturinfo-publishing&saved=1'));
    exit;
}
add_action('admin_post_culturinfo_publishing_save', 'culturinfo_publishing_save_settings');

function culturinfo_publishing_run_now() {
    if (!current_user_can(culturinfo_publishing_capability())) {
        wp_die(esc_html__('No tienes permisos para publicar estos borradores.', 'culturinfo-publishing'));
    }
    check_admin_referer('culturinfo_publishing_run_now');
    $summary = culturinfo_publishing_process(true);
    $url = add_query_arg(array(
        'page'      => 'culturinfo-publishing',
        'run'       => 1,
        'published' => absint($summary['published']),
        'errors'    => absint($summary['errors']),
    ), admin_url('admin.php'));
    wp_safe_redirect($url);
    exit;
}
add_action('admin_post_culturinfo_publishing_run_now', 'culturinfo_publishing_run_now');

function culturinfo_publishing_admin_assets($hook) {
    if ($hook !== 'toplevel_page_culturinfo-publishing') {
        return;
    }
    wp_enqueue_style('culturinfo-publishing-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', array(), CULTURINFO_PUBLISHING_VERSION);
}
add_action('admin_enqueue_scripts', 'culturinfo_publishing_admin_assets');

function culturinfo_publishing_plugin_link($links) {
    array_unshift($links, '<a href="' . esc_url(admin_url('admin.php?page=culturinfo-publishing')) . '">Configurar</a>');
    return $links;
}
add_filter('plugin_action_links_' . plugin_basename(__FILE__), 'culturinfo_publishing_plugin_link');
