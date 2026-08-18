<?php
/**
 * Plugin Name: Culturinfo — Estadísticas editoriales
 * Description: Estadísticas privadas de noticias, escritores, secciones y publicidad, sin servicios de pago.
 * Version: 1.1.0
 * Author: Horizonte Cultural
 * Text Domain: culturinfo-stats
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CULTURINFO_STATS_VERSION', '1.2.0');

function culturinfo_stats_tables() {
    global $wpdb;
    return array(
        'daily'    => $wpdb->prefix . 'culturinfo_stats_daily',
        'visitors' => $wpdb->prefix . 'culturinfo_stats_visitors',
    );
}

function culturinfo_stats_activate() {
    global $wpdb;
    $tables = culturinfo_stats_tables();
    $charset = $wpdb->get_charset_collate();
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';

    dbDelta("CREATE TABLE {$tables['daily']} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        stat_date date NOT NULL,
        metric varchar(32) NOT NULL,
        object_type varchar(24) NOT NULL,
        object_id bigint(20) unsigned NOT NULL DEFAULT 0,
        dimension varchar(32) NOT NULL DEFAULT '',
        dimension_value varchar(64) NOT NULL DEFAULT '',
        total bigint(20) unsigned NOT NULL DEFAULT 0,
        PRIMARY KEY  (id),
        UNIQUE KEY stat_bucket (stat_date,metric,object_type,object_id,dimension,dimension_value),
        KEY metric_date (metric,stat_date),
        KEY object_lookup (object_type,object_id,stat_date)
    ) $charset;");

    dbDelta("CREATE TABLE {$tables['visitors']} (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        visit_date date NOT NULL,
        visitor_hash char(64) NOT NULL,
        object_type varchar(24) NOT NULL,
        object_id bigint(20) unsigned NOT NULL DEFAULT 0,
        first_seen datetime NOT NULL,
        PRIMARY KEY  (id),
        UNIQUE KEY daily_visitor (visit_date,visitor_hash,object_type,object_id),
        KEY visit_date (visit_date),
        KEY object_lookup (object_type,object_id,visit_date)
    ) $charset;");

    if (!wp_next_scheduled('culturinfo_stats_cleanup')) {
        wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'culturinfo_stats_cleanup');
    }
}
register_activation_hook(__FILE__, 'culturinfo_stats_activate');

function culturinfo_stats_deactivate() {
    wp_clear_scheduled_hook('culturinfo_stats_cleanup');
}
register_deactivation_hook(__FILE__, 'culturinfo_stats_deactivate');

function culturinfo_stats_cleanup() {
    global $wpdb;
    $tables = culturinfo_stats_tables();
    $cutoff = wp_date('Y-m-d', time() - (91 * DAY_IN_SECONDS));
    $wpdb->query($wpdb->prepare("DELETE FROM {$tables['visitors']} WHERE visit_date < %s", $cutoff));
}
add_action('culturinfo_stats_cleanup', 'culturinfo_stats_cleanup');

function culturinfo_stats_is_bot() {
    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
    if ($agent === '') {
        return true;
    }
    return (bool) preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|whatsapp|telegrambot|bingpreview|monitor|uptime/i', $agent);
}

function culturinfo_stats_should_track() {
    if (is_admin() || is_user_logged_in() || wp_doing_ajax() || wp_doing_cron() || is_feed() || is_preview() || is_robots() || is_trackback()) {
        return false;
    }
    if (defined('REST_REQUEST') && REST_REQUEST) {
        return false;
    }
    return !culturinfo_stats_is_bot();
}

function culturinfo_stats_increment($metric, $object_type, $object_id = 0, $dimension = '', $dimension_value = '', $amount = 1) {
    global $wpdb;
    $table = culturinfo_stats_tables()['daily'];
    $date = wp_date('Y-m-d');
    $sql = $wpdb->prepare(
        "INSERT INTO {$table} (stat_date,metric,object_type,object_id,dimension,dimension_value,total)
         VALUES (%s,%s,%s,%d,%s,%s,%d)
         ON DUPLICATE KEY UPDATE total = total + VALUES(total)",
        $date,
        sanitize_key($metric),
        sanitize_key($object_type),
        absint($object_id),
        sanitize_key($dimension),
        substr(sanitize_text_field($dimension_value), 0, 64),
        max(1, min(3600, absint($amount)))
    );
    $wpdb->query($sql);
}

function culturinfo_stats_visitor_hash() {
    $ip = isset($_SERVER['REMOTE_ADDR']) ? sanitize_text_field(wp_unslash($_SERVER['REMOTE_ADDR'])) : '';
    if (!empty($_SERVER['HTTP_CF_RAY']) && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        $ip = sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
    }
    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field(wp_unslash($_SERVER['HTTP_USER_AGENT'])) : '';
    return hash_hmac('sha256', wp_date('Y-m-d') . '|' . $ip . '|' . $agent, wp_salt('auth'));
}

function culturinfo_stats_store_visitor($object_type, $object_id) {
    global $wpdb;
    $table = culturinfo_stats_tables()['visitors'];
    $wpdb->query($wpdb->prepare(
        "INSERT IGNORE INTO {$table} (visit_date,visitor_hash,object_type,object_id,first_seen) VALUES (%s,%s,%s,%d,%s)",
        wp_date('Y-m-d'),
        culturinfo_stats_visitor_hash(),
        sanitize_key($object_type),
        absint($object_id),
        current_time('mysql')
    ));
}

function culturinfo_stats_source() {
    if (empty($_SERVER['HTTP_REFERER'])) {
        return 'directo';
    }
    $referer = wp_parse_url(wp_unslash($_SERVER['HTTP_REFERER']));
    $site = wp_parse_url(home_url('/'));
    $host = isset($referer['host']) ? strtolower($referer['host']) : '';
    if ($host === '' || (!empty($site['host']) && $host === strtolower($site['host']))) {
        return 'interno';
    }
    $sources = array(
        'google.' => 'google', 'bing.' => 'bing', 'facebook.' => 'facebook',
        'instagram.' => 'instagram', 't.co' => 'x-twitter', 'twitter.' => 'x-twitter',
        'youtube.' => 'youtube', 'linkedin.' => 'linkedin', 'whatsapp.' => 'whatsapp',
    );
    foreach ($sources as $needle => $label) {
        if (strpos($host, $needle) !== false) {
            return $label;
        }
    }
    return 'otros-sitios';
}

function culturinfo_stats_device() {
    $agent = isset($_SERVER['HTTP_USER_AGENT']) ? wp_unslash($_SERVER['HTTP_USER_AGENT']) : '';
    if (preg_match('/ipad|tablet|kindle|silk/i', $agent)) {
        return 'tableta';
    }
    return wp_is_mobile() ? 'movil' : 'computadora';
}

function culturinfo_stats_country() {
    $country = isset($_SERVER['HTTP_CF_IPCOUNTRY']) ? strtoupper(sanitize_text_field(wp_unslash($_SERVER['HTTP_CF_IPCOUNTRY']))) : 'XX';
    return preg_match('/^[A-Z]{2}$/', $country) || $country === 'T1' ? $country : 'XX';
}

function culturinfo_stats_page_context() {
    if (is_front_page() || is_home()) {
        return array('home', 0);
    }
    if (is_singular('post')) {
        return array('post', get_queried_object_id());
    }
    if (is_category()) {
        return array('category', get_queried_object_id());
    }
    if (is_page()) {
        return array('page', get_queried_object_id());
    }
    return null;
}

function culturinfo_stats_track_pageview() {
    if (!culturinfo_stats_should_track()) {
        return;
    }
    $context = culturinfo_stats_page_context();
    if (!$context) {
        return;
    }
    list($type, $id) = $context;
    culturinfo_stats_increment('pageview', $type, $id);
    culturinfo_stats_increment('pageview', $type, $id, 'source', culturinfo_stats_source());
    culturinfo_stats_increment('pageview', $type, $id, 'device', culturinfo_stats_device());
    culturinfo_stats_increment('pageview', $type, $id, 'country', culturinfo_stats_country());
    culturinfo_stats_store_visitor('site', 0);
    culturinfo_stats_store_visitor($type, $id);

    if ($type === 'post') {
        $categories = get_the_category($id);
        if ($categories) {
            culturinfo_stats_increment('pageview', $type, $id, 'category', (string) $categories[0]->term_id);
        }
        $writer_id = absint(get_post_meta($id, '_culturinfo_writer_id', true));
        $writer = $writer_id ? 'e:' . $writer_id : 'u:' . absint(get_post_field('post_author', $id));
        culturinfo_stats_increment('pageview', $type, $id, 'writer', $writer);
    }
}
add_action('template_redirect', 'culturinfo_stats_track_pageview', 20);

function culturinfo_stats_popular_post_ids($days = 7, $limit = 4) {
    global $wpdb;
    $days = min(90, max(1, absint($days)));
    $limit = min(12, max(1, absint($limit)));
    $cache_key = 'culturinfo_popular_' . $days . '_' . $limit;
    $cached = get_transient($cache_key);
    if (is_array($cached)) {
        return array_map('absint', $cached);
    }

    $table = culturinfo_stats_tables()['daily'];
    $end = wp_date('Y-m-d');
    $start = wp_date('Y-m-d', time() - (($days - 1) * DAY_IN_SECONDS));
    $ids = $wpdb->get_col($wpdb->prepare(
        "SELECT object_id FROM {$table}
         WHERE metric='pageview' AND object_type='post' AND dimension=''
         AND stat_date BETWEEN %s AND %s
         GROUP BY object_id ORDER BY SUM(total) DESC LIMIT %d",
        $start,
        $end,
        $limit
    ));
    $ids = array_values(array_filter(array_map('absint', $ids), function ($post_id) {
        return get_post_status($post_id) === 'publish';
    }));
    set_transient($cache_key, $ids, 10 * MINUTE_IN_SECONDS);
    return $ids;
}

function culturinfo_stats_enqueue() {
    if (is_admin() || is_user_logged_in()) {
        return;
    }
    wp_enqueue_script('culturinfo-stats', plugin_dir_url(__FILE__) . 'assets/stats.js', array(), CULTURINFO_STATS_VERSION, true);
    wp_localize_script('culturinfo-stats', 'culturinfoStats', array(
        'adEndpoint'      => esc_url_raw(rest_url('culturinfo-stats/v1/ad-event')),
        'articleEndpoint' => esc_url_raw(rest_url('culturinfo-stats/v1/article-event')),
        'articleId'       => is_singular('post') ? get_queried_object_id() : 0,
    ));
}
add_action('wp_enqueue_scripts', 'culturinfo_stats_enqueue', 30);

function culturinfo_stats_same_site_request() {
    $origin = isset($_SERVER['HTTP_ORIGIN']) ? wp_unslash($_SERVER['HTTP_ORIGIN']) : '';
    $referer = isset($_SERVER['HTTP_REFERER']) ? wp_unslash($_SERVER['HTTP_REFERER']) : '';
    $request_host = wp_parse_url($origin ?: $referer, PHP_URL_HOST);
    $site_host = wp_parse_url(home_url('/'), PHP_URL_HOST);
    return $request_host && $site_host && strtolower($request_host) === strtolower($site_host);
}

/**
 * Limita telemetría pública por visitante sin conservar la dirección IP. No es
 * autenticación: evita duplicados accidentales y reduce la inflación básica.
 */
function culturinfo_stats_event_guard($scope, $limit, $ttl) {
    $fingerprint = culturinfo_stats_visitor_hash() . '|' . sanitize_key($scope);
    $key = 'culturinfo_stats_guard_' . substr(hash('sha256', $fingerprint), 0, 40);
    $count = absint(get_transient($key));
    if ($count >= absint($limit)) {
        return false;
    }
    set_transient($key, $count + 1, max(MINUTE_IN_SECONDS, absint($ttl)));
    return true;
}

function culturinfo_stats_event_once($scope, $ttl) {
    return culturinfo_stats_event_guard('once_' . $scope, 1, $ttl);
}

function culturinfo_stats_ignored_response() {
    return rest_ensure_response(array('recorded' => false));
}

function culturinfo_stats_ad_event(WP_REST_Request $request) {
    if (!culturinfo_stats_same_site_request() || culturinfo_stats_is_bot()) {
        return new WP_Error('invalid_origin', 'Solicitud no permitida.', array('status' => 403));
    }
    $event = sanitize_key($request->get_param('event'));
    $ad_id = absint($request->get_param('ad_id'));
    $slot = sanitize_key($request->get_param('slot'));
    $context_type = sanitize_key($request->get_param('context_type'));
    $context_id = absint($request->get_param('context_id'));
    if (!in_array($event, array('impression', 'click'), true) || !$ad_id || get_post_type($ad_id) !== 'culturinfo_ad' || get_post_status($ad_id) !== 'publish') {
        return new WP_Error('invalid_event', 'Evento no válido.', array('status' => 400));
    }
    if (!function_exists('culturinfo_ads_find') || !isset(culturinfo_ads_slots()[$slot])) {
        return new WP_Error('invalid_slot', 'Ubicación no válida.', array('status' => 400));
    }
    $expected = culturinfo_ads_find($slot, $context_id);
    if (!$expected || absint($expected->ID) !== $ad_id) {
        return new WP_Error('inactive_ad', 'El anuncio no está activo en esta ubicación.', array('status' => 400));
    }
    $expected_context = 0 === strpos($slot, 'home_') ? 'home' : (0 === strpos($slot, 'section_') ? 'section' : 'article');
    if ($context_type !== $expected_context) {
        return new WP_Error('invalid_context', 'Contexto no válido.', array('status' => 400));
    }
    if ($context_type === 'home') {
        $context_id = 0;
    } elseif ($context_type === 'section' && !term_exists($context_id, 'category')) {
        return new WP_Error('invalid_context', 'La sección no existe.', array('status' => 400));
    } elseif ($context_type === 'article' && get_post_type($context_id) !== 'post') {
        return new WP_Error('invalid_context', 'La noticia no existe.', array('status' => 400));
    }

    if (!culturinfo_stats_event_guard('ad-all', 80, HOUR_IN_SECONDS)) {
        return culturinfo_stats_ignored_response();
    }
    if ($event === 'impression' && !culturinfo_stats_event_once('ad-impression-' . $ad_id . '-' . $slot . '-' . $context_type . '-' . $context_id, HOUR_IN_SECONDS)) {
        return culturinfo_stats_ignored_response();
    }
    if ($event === 'click' && !culturinfo_stats_event_guard('ad-click-' . $ad_id, 5, HOUR_IN_SECONDS)) {
        return culturinfo_stats_ignored_response();
    }
    $metric = 'ad_' . $event;
    culturinfo_stats_increment($metric, 'ad', $ad_id);
    culturinfo_stats_increment($metric, 'ad', $ad_id, 'slot', $slot);
    culturinfo_stats_increment($metric, 'ad', $ad_id, 'context', $context_type . ':' . $context_id);
    culturinfo_stats_increment($metric, 'ad', $ad_id, 'country', culturinfo_stats_country());
    return rest_ensure_response(array('recorded' => true));
}

function culturinfo_stats_article_event(WP_REST_Request $request) {
    if (!culturinfo_stats_same_site_request() || culturinfo_stats_is_bot()) {
        return new WP_Error('invalid_origin', 'Solicitud no permitida.', array('status' => 403));
    }
    $post_id = absint($request->get_param('post_id'));
    $event = sanitize_key($request->get_param('event'));
    $value = absint($request->get_param('value'));
    if (!$post_id || get_post_type($post_id) !== 'post' || get_post_status($post_id) !== 'publish') {
        return new WP_Error('invalid_article', 'La noticia no existe.', array('status' => 400));
    }
    if (!culturinfo_stats_event_guard('article-all', 160, HOUR_IN_SECONDS)) {
        return culturinfo_stats_ignored_response();
    }
    if ($event === 'scroll' && in_array($value, array(25, 50, 75, 100), true)) {
        if (!culturinfo_stats_event_once('article-scroll-' . $post_id . '-' . $value, DAY_IN_SECONDS)) {
            return culturinfo_stats_ignored_response();
        }
        culturinfo_stats_increment('article_scroll', 'post', $post_id, 'depth', (string) $value);
    } elseif ($event === 'time' && $value >= 1 && $value <= 30) {
        culturinfo_stats_increment('article_time', 'post', $post_id, '', '', $value);
        if ($request->get_param('session_start') && culturinfo_stats_event_once('article-session-' . $post_id, DAY_IN_SECONDS)) {
            culturinfo_stats_increment('article_session', 'post', $post_id);
        }
    } else {
        return new WP_Error('invalid_event', 'Evento no válido.', array('status' => 400));
    }
    return rest_ensure_response(array('recorded' => true));
}

function culturinfo_stats_register_rest() {
    register_rest_route('culturinfo-stats/v1', '/ad-event', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'culturinfo_stats_ad_event',
        'permission_callback' => '__return_true',
        'args'                => array(
            'event'       => array('required' => true, 'type' => 'string'),
            'ad_id'       => array('required' => true, 'type' => 'integer'),
            'slot'        => array('required' => true, 'type' => 'string'),
            'context_type'=> array('required' => true, 'type' => 'string'),
            'context_id'  => array('required' => false, 'type' => 'integer', 'default' => 0),
        ),
    ));
    register_rest_route('culturinfo-stats/v1', '/article-event', array(
        'methods'             => WP_REST_Server::CREATABLE,
        'callback'            => 'culturinfo_stats_article_event',
        'permission_callback' => '__return_true',
        'args'                => array(
            'post_id'       => array('required' => true, 'type' => 'integer'),
            'event'         => array('required' => true, 'type' => 'string'),
            'value'         => array('required' => true, 'type' => 'integer'),
            'session_start' => array('required' => false, 'type' => 'boolean', 'default' => false),
        ),
    ));
}
add_action('rest_api_init', 'culturinfo_stats_register_rest');

function culturinfo_stats_admin_menu() {
    add_menu_page('Estadísticas Culturinfo', 'Estadísticas', 'manage_options', 'culturinfo-stats', 'culturinfo_stats_dashboard', 'dashicons-chart-bar', 23);
}
add_action('admin_menu', 'culturinfo_stats_admin_menu');

function culturinfo_stats_range() {
    $days = isset($_GET['days']) ? absint($_GET['days']) : 30;
    return in_array($days, array(7, 30, 90), true) ? $days : 30;
}

function culturinfo_stats_sum($metric, $start, $dimension = '', $end = '') {
    global $wpdb;
    $table = culturinfo_stats_tables()['daily'];
    if ($end) {
        return (int) $wpdb->get_var($wpdb->prepare(
            "SELECT COALESCE(SUM(total),0) FROM {$table} WHERE metric=%s AND stat_date BETWEEN %s AND %s AND dimension=%s",
            $metric, $start, $end, $dimension
        ));
    }
    return (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(total),0) FROM {$table} WHERE metric=%s AND stat_date >= %s AND dimension=%s",
        $metric, $start, $dimension
    ));
}

function culturinfo_stats_label($type, $value) {
    if ($type === 'source') {
        $labels = array('directo'=>'Directo','interno'=>'Navegación interna','google'=>'Google','bing'=>'Bing','facebook'=>'Facebook','instagram'=>'Instagram','x-twitter'=>'X / Twitter','youtube'=>'YouTube','linkedin'=>'LinkedIn','whatsapp'=>'WhatsApp','otros-sitios'=>'Otros sitios');
        return isset($labels[$value]) ? $labels[$value] : ucfirst($value);
    }
    if ($type === 'device') {
        $labels = array('movil'=>'Móvil','computadora'=>'Computadora','tableta'=>'Tableta');
        return isset($labels[$value]) ? $labels[$value] : ucfirst($value);
    }
    if ($type === 'writer') {
        list($kind, $id) = array_pad(explode(':', $value, 2), 2, 0);
        return $kind === 'e' ? get_the_title(absint($id)) : get_the_author_meta('display_name', absint($id));
    }
    if ($type === 'country') {
        $labels = array(
            'AR'=>'Argentina','BO'=>'Bolivia','BR'=>'Brasil','CA'=>'Canadá','CL'=>'Chile','CN'=>'China','CO'=>'Colombia',
            'CR'=>'Costa Rica','CU'=>'Cuba','DE'=>'Alemania','DO'=>'República Dominicana','EC'=>'Ecuador','ES'=>'España',
            'FR'=>'Francia','GB'=>'Reino Unido','GT'=>'Guatemala','HN'=>'Honduras','HT'=>'Haití','IT'=>'Italia','JP'=>'Japón',
            'MX'=>'México','NI'=>'Nicaragua','NL'=>'Países Bajos','PA'=>'Panamá','PE'=>'Perú','PR'=>'Puerto Rico','PT'=>'Portugal',
            'PY'=>'Paraguay','SV'=>'El Salvador','US'=>'Estados Unidos','UY'=>'Uruguay','VE'=>'Venezuela','T1'=>'Red Tor','XX'=>'Desconocido',
        );
        return isset($labels[$value]) ? $labels[$value] . ' (' . $value . ')' : $value;
    }
    return $value;
}

function culturinfo_stats_format_duration($seconds) {
    $seconds = absint($seconds);
    if ($seconds < 60) {
        return $seconds . ' s';
    }
    $minutes = (int) floor($seconds / 60);
    return $minutes . ' min ' . ($seconds % 60) . ' s';
}

function culturinfo_stats_metric_card($label, $value, $previous, $suffix = '', $format = 'number') {
    $difference = 0.0;
    $trend_class = 'is-neutral';
    if ((float) $previous > 0) {
        $difference = (((float) $value - (float) $previous) / (float) $previous) * 100;
        $trend_class = $difference > 0 ? 'is-up' : ($difference < 0 ? 'is-down' : 'is-neutral');
        $trend = ($difference > 0 ? '+' : '') . number_format_i18n($difference, 1) . '%';
    } elseif ((float) $value > 0) {
        $trend = 'Nuevo';
        $trend_class = 'is-up';
    } else {
        $trend = 'Sin cambio';
    }
    ?>
    <?php $display_value = $format === 'duration' ? culturinfo_stats_format_duration($value) : number_format_i18n($value, $suffix === '%' ? 2 : 0) . $suffix; ?>
    <div><span><?php echo esc_html($label); ?></span><strong><?php echo esc_html($display_value); ?></strong><small class="<?php echo esc_attr($trend_class); ?>"><?php echo esc_html($trend); ?> vs. período anterior</small></div>
    <?php
}

function culturinfo_stats_dashboard() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos para ver estas estadísticas.', 'culturinfo-stats'));
    }
    global $wpdb;
    $table = culturinfo_stats_tables()['daily'];
    $visitors_table = culturinfo_stats_tables()['visitors'];
    $days = culturinfo_stats_range();
    $end = wp_date('Y-m-d');
    $start = wp_date('Y-m-d', time() - (($days - 1) * DAY_IN_SECONDS));
    $previous_end = wp_date('Y-m-d', strtotime($start . ' -1 day'));
    $previous_start = wp_date('Y-m-d', strtotime($previous_end . ' -' . ($days - 1) . ' days'));
    $views = culturinfo_stats_sum('pageview', $start, '', $end);
    $impressions = culturinfo_stats_sum('ad_impression', $start, '', $end);
    $clicks = culturinfo_stats_sum('ad_click', $start, '', $end);
    $previous_views = culturinfo_stats_sum('pageview', $previous_start, '', $previous_end);
    $previous_impressions = culturinfo_stats_sum('ad_impression', $previous_start, '', $previous_end);
    $previous_clicks = culturinfo_stats_sum('ad_click', $previous_start, '', $previous_end);
    $visitors = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$visitors_table} WHERE object_type='site' AND object_id=0 AND visit_date BETWEEN %s AND %s", $start, $end));
    $previous_visitors = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$visitors_table} WHERE object_type='site' AND object_id=0 AND visit_date BETWEEN %s AND %s", $previous_start, $previous_end));
    $ctr = $impressions ? ($clicks / $impressions) * 100 : 0;
    $previous_ctr = $previous_impressions ? ($previous_clicks / $previous_impressions) * 100 : 0;
    $engaged_seconds = culturinfo_stats_sum('article_time', $start, '', $end);
    $engaged_sessions = culturinfo_stats_sum('article_session', $start, '', $end);
    $previous_engaged_seconds = culturinfo_stats_sum('article_time', $previous_start, '', $previous_end);
    $previous_engaged_sessions = culturinfo_stats_sum('article_session', $previous_start, '', $previous_end);
    $average_time = $engaged_sessions ? (int) round($engaged_seconds / $engaged_sessions) : 0;
    $previous_average_time = $previous_engaged_sessions ? (int) round($previous_engaged_seconds / $previous_engaged_sessions) : 0;
    $post_views = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total),0) FROM {$table} WHERE metric='pageview' AND object_type='post' AND dimension='' AND stat_date BETWEEN %s AND %s", $start, $end));
    $previous_post_views = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total),0) FROM {$table} WHERE metric='pageview' AND object_type='post' AND dimension='' AND stat_date BETWEEN %s AND %s", $previous_start, $previous_end));
    $completions = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total),0) FROM {$table} WHERE metric='article_scroll' AND dimension='depth' AND dimension_value='100' AND stat_date BETWEEN %s AND %s", $start, $end));
    $previous_completions = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(total),0) FROM {$table} WHERE metric='article_scroll' AND dimension='depth' AND dimension_value='100' AND stat_date BETWEEN %s AND %s", $previous_start, $previous_end));
    $completion_rate = $post_views ? ($completions / $post_views) * 100 : 0;
    $previous_completion_rate = $previous_post_views ? ($previous_completions / $previous_post_views) * 100 : 0;
    $daily = $wpdb->get_results($wpdb->prepare("SELECT stat_date,SUM(total) total FROM {$table} WHERE metric='pageview' AND dimension='' AND stat_date BETWEEN %s AND %s GROUP BY stat_date ORDER BY stat_date", $start, $end));
    $top_posts = $wpdb->get_results($wpdb->prepare("SELECT object_id,SUM(total) total FROM {$table} WHERE metric='pageview' AND object_type='post' AND dimension='' AND stat_date BETWEEN %s AND %s GROUP BY object_id ORDER BY total DESC LIMIT 10", $start, $end));
    $completed_posts = $wpdb->get_results($wpdb->prepare("SELECT object_id,SUM(total) total FROM {$table} WHERE metric='article_scroll' AND dimension='depth' AND dimension_value='100' AND stat_date BETWEEN %s AND %s GROUP BY object_id ORDER BY total DESC LIMIT 10", $start, $end));
    $dimensions = array();
    foreach (array('writer', 'category', 'source', 'device', 'country') as $dimension) {
        $dimensions[$dimension] = $wpdb->get_results($wpdb->prepare("SELECT dimension_value,SUM(total) total FROM {$table} WHERE metric='pageview' AND dimension=%s AND stat_date BETWEEN %s AND %s GROUP BY dimension_value ORDER BY total DESC LIMIT 10", $dimension, $start, $end));
    }
    $ads = $wpdb->get_results($wpdb->prepare(
        "SELECT object_id,
        dimension_value slot,
        SUM(CASE WHEN metric='ad_impression' THEN total ELSE 0 END) impressions,
        SUM(CASE WHEN metric='ad_click' THEN total ELSE 0 END) clicks
        FROM {$table} WHERE object_type='ad' AND dimension='slot' AND stat_date BETWEEN %s AND %s GROUP BY object_id,dimension_value ORDER BY impressions DESC LIMIT 20",
        $start, $end
    ));
    $ad_countries = $wpdb->get_results($wpdb->prepare(
        "SELECT dimension_value,SUM(total) total FROM {$table} WHERE metric='ad_impression' AND object_type='ad' AND dimension='country' AND stat_date BETWEEN %s AND %s GROUP BY dimension_value ORDER BY total DESC LIMIT 10",
        $start, $end
    ));
    $max_daily = 1;
    foreach ($daily as $row) { $max_daily = max($max_daily, (int) $row->total); }
    ?>
    <div class="wrap culturinfo-stats-wrap">
        <h1>Estadísticas Culturinfo</h1>
        <p class="description">Datos propios almacenados en este WordPress. No se guardan direcciones IP y se excluyen administradores, editores y robots conocidos.</p>
        <nav class="culturinfo-stats-filters" aria-label="Período">
            <?php foreach (array(7,30,90) as $option) : ?>
                <a class="button <?php echo $days === $option ? 'button-primary' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=culturinfo-stats&days=' . $option)); ?>"><?php echo esc_html($option); ?> días</a>
            <?php endforeach; ?>
        </nav>
        <div class="culturinfo-stats-cards">
            <?php culturinfo_stats_metric_card('Visitas a páginas', $views, $previous_views); ?>
            <?php culturinfo_stats_metric_card('Visitantes únicos diarios', $visitors, $previous_visitors); ?>
            <?php culturinfo_stats_metric_card('Tiempo medio de lectura', $average_time, $previous_average_time, '', 'duration'); ?>
            <?php culturinfo_stats_metric_card('Lecturas completadas', $completion_rate, $previous_completion_rate, '%'); ?>
            <?php culturinfo_stats_metric_card('Impresiones de anuncios', $impressions, $previous_impressions); ?>
            <?php culturinfo_stats_metric_card('Clics en anuncios', $clicks, $previous_clicks); ?>
            <?php culturinfo_stats_metric_card('CTR publicitario', $ctr, $previous_ctr, '%'); ?>
        </div>
        <section class="culturinfo-stats-panel culturinfo-stats-chart"><h2>Visitas diarias</h2>
            <?php if (!$daily) : ?><p>Aún no hay visitas registradas en este período.</p><?php else : foreach ($daily as $row) : ?>
                <div class="culturinfo-stats-bar"><time><?php echo esc_html(wp_date('j M', strtotime($row->stat_date))); ?></time><i style="width:<?php echo esc_attr(max(2, round(((int)$row->total / $max_daily) * 100))); ?>%"></i><b><?php echo esc_html(number_format_i18n($row->total)); ?></b></div>
            <?php endforeach; endif; ?>
        </section>
        <div class="culturinfo-stats-grid">
            <?php culturinfo_stats_table('Noticias más leídas', $top_posts, function($row){ return get_the_title($row->object_id) ?: 'Entrada eliminada'; }); ?>
            <?php culturinfo_stats_table('Por escritor', $dimensions['writer'], function($row){ return culturinfo_stats_label('writer', $row->dimension_value) ?: 'Sin nombre'; }); ?>
            <?php culturinfo_stats_table('Por sección', $dimensions['category'], function($row){ $term=get_term(absint($row->dimension_value),'category'); return $term && !is_wp_error($term) ? $term->name : 'Sección eliminada'; }); ?>
            <?php culturinfo_stats_table('Fuentes de tráfico', $dimensions['source'], function($row){ return culturinfo_stats_label('source', $row->dimension_value); }); ?>
            <?php culturinfo_stats_table('Dispositivos', $dimensions['device'], function($row){ return culturinfo_stats_label('device', $row->dimension_value); }); ?>
            <?php culturinfo_stats_table('Países de lectores', $dimensions['country'], function($row){ return culturinfo_stats_label('country', $row->dimension_value); }); ?>
            <?php culturinfo_stats_table('Noticias terminadas', $completed_posts, function($row){ return get_the_title($row->object_id) ?: 'Entrada eliminada'; }); ?>
            <?php culturinfo_stats_table('Países de impresiones publicitarias', $ad_countries, function($row){ return culturinfo_stats_label('country', $row->dimension_value); }); ?>
        </div>
        <section class="culturinfo-stats-panel"><h2>Rendimiento de anuncios</h2>
            <table class="widefat striped"><thead><tr><th>Anuncio</th><th>Ubicación</th><th>Impresiones</th><th>Clics</th><th>CTR</th></tr></thead><tbody>
            <?php if (!$ads) : ?><tr><td colspan="5">Aún no hay actividad publicitaria registrada.</td></tr><?php else : foreach ($ads as $ad) : $ad_ctr=$ad->impressions ? ($ad->clicks/$ad->impressions)*100 : 0; $slots=function_exists('culturinfo_ads_slots') ? culturinfo_ads_slots() : array(); ?>
                <tr><td><a href="<?php echo esc_url(get_edit_post_link($ad->object_id)); ?>"><?php echo esc_html(get_the_title($ad->object_id) ?: 'Anuncio eliminado'); ?></a></td><td><?php echo esc_html(isset($slots[$ad->slot]) ? $slots[$ad->slot] : $ad->slot); ?></td><td><?php echo esc_html(number_format_i18n($ad->impressions)); ?></td><td><?php echo esc_html(number_format_i18n($ad->clicks)); ?></td><td><?php echo esc_html(number_format_i18n($ad_ctr,2)); ?>%</td></tr>
            <?php endforeach; endif; ?></tbody></table>
        </section>
        <p><a class="button button-primary" href="<?php echo esc_url(wp_nonce_url(admin_url('admin-post.php?action=culturinfo_stats_export_ads&days=' . $days), 'culturinfo_stats_export_ads')); ?>">Descargar reporte publicitario CSV</a></p>
        <?php if (is_plugin_active('independent-analytics/iawp.php')) : ?><p><a class="button" href="<?php echo esc_url(admin_url('admin.php?page=independent-analytics')); ?>">Abrir análisis detallado de Independent Analytics</a></p><?php endif; ?>
    </div>
    <?php
}

function culturinfo_stats_table($title, $rows, $label_callback) {
    ?><section class="culturinfo-stats-panel"><h2><?php echo esc_html($title); ?></h2><table class="widefat striped"><thead><tr><th>Nombre</th><th>Visitas</th></tr></thead><tbody>
    <?php if (!$rows) : ?><tr><td colspan="2">Sin datos todavía.</td></tr><?php else : foreach ($rows as $row) : ?><tr><td><?php echo esc_html(call_user_func($label_callback, $row)); ?></td><td><?php echo esc_html(number_format_i18n($row->total)); ?></td></tr><?php endforeach; endif; ?>
    </tbody></table></section><?php
}

function culturinfo_stats_csv_cell($value) {
    $value = wp_strip_all_tags((string) $value);
    return preg_match('/^[=+\-@]/', $value) ? "'" . $value : $value;
}

function culturinfo_stats_export_ads() {
    if (!current_user_can('manage_options')) {
        wp_die(esc_html__('No tienes permisos para exportar estas estadísticas.', 'culturinfo-stats'));
    }
    check_admin_referer('culturinfo_stats_export_ads');
    global $wpdb;
    $table = culturinfo_stats_tables()['daily'];
    $days = culturinfo_stats_range();
    $end = wp_date('Y-m-d');
    $start = wp_date('Y-m-d', time() - (($days - 1) * DAY_IN_SECONDS));
    $rows = $wpdb->get_results($wpdb->prepare(
        "SELECT object_id,dimension,dimension_value,
        SUM(CASE WHEN metric='ad_impression' THEN total ELSE 0 END) impressions,
        SUM(CASE WHEN metric='ad_click' THEN total ELSE 0 END) clicks
        FROM {$table}
        WHERE object_type='ad' AND dimension IN ('slot','country') AND stat_date BETWEEN %s AND %s
        GROUP BY object_id,dimension,dimension_value
        ORDER BY object_id,dimension,impressions DESC",
        $start, $end
    ));
    $filename = 'culturinfo-publicidad-' . $start . '-a-' . $end . '.csv';
    nocache_headers();
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    $output = fopen('php://output', 'w');
    if (!$output) {
        wp_die(esc_html__('No fue posible generar el reporte.', 'culturinfo-stats'));
    }
    fwrite($output, "\xEF\xBB\xBF");
    fputcsv($output, array('Tipo', 'Anuncio', 'Ubicación o país', 'Impresiones', 'Clics', 'CTR', 'Desde', 'Hasta'), ',', '"', '');
    $slots = function_exists('culturinfo_ads_slots') ? culturinfo_ads_slots() : array();
    foreach ($rows as $row) {
        $label = $row->dimension === 'country'
            ? culturinfo_stats_label('country', $row->dimension_value)
            : (isset($slots[$row->dimension_value]) ? $slots[$row->dimension_value] : $row->dimension_value);
        $ctr = $row->impressions ? ((int) $row->clicks / (int) $row->impressions) * 100 : 0;
        fputcsv($output, array(
            $row->dimension === 'country' ? 'País' : 'Ubicación',
            culturinfo_stats_csv_cell(get_the_title($row->object_id) ?: 'Anuncio eliminado'),
            culturinfo_stats_csv_cell($label),
            (int) $row->impressions,
            (int) $row->clicks,
            number_format($ctr, 2, '.', '') . '%',
            $start,
            $end,
        ), ',', '"', '');
    }
    fclose($output);
    exit;
}
add_action('admin_post_culturinfo_stats_export_ads', 'culturinfo_stats_export_ads');

function culturinfo_stats_admin_styles($hook) {
    if ($hook !== 'toplevel_page_culturinfo-stats') { return; }
    wp_enqueue_style('culturinfo-stats-admin', plugin_dir_url(__FILE__) . 'assets/admin.css', array(), CULTURINFO_STATS_VERSION);
}
add_action('admin_enqueue_scripts', 'culturinfo_stats_admin_styles');
