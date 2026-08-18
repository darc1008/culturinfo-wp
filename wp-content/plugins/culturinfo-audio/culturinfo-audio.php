<?php
/**
 * Plugin Name: Culturinfo — Audio de noticias
 * Description: Genera localmente una versión MP3 de cada noticia publicada y la procesa mediante una cola editorial.
 * Version: 1.0.0
 * Author: Horizonte Cultural
 * Text Domain: culturinfo-audio
 */

if (!defined('ABSPATH')) {
    exit;
}

define('CULTURINFO_AUDIO_VERSION', '1.0.0');
define('CULTURINFO_AUDIO_QUEUE_OPTION', 'culturinfo_audio_queue');
define('CULTURINFO_AUDIO_WORKER_HOOK', 'culturinfo_audio_process_queue');
define('CULTURINFO_AUDIO_LOCK_OPTION', 'culturinfo_audio_worker_lock');
define('CULTURINFO_AUDIO_VOICE', 'es_MX-claude-high');
define('CULTURINFO_AUDIO_VOICE_LABEL', 'Español latino');

function culturinfo_audio_python_path() {
    return getenv('CULTURINFO_PIPER_PYTHON') ?: '/opt/culturinfo/piper/bin/python';
}

function culturinfo_audio_model_path() {
    return getenv('CULTURINFO_PIPER_MODEL') ?: '/opt/culturinfo/voices/es_MX-claude-high.onnx';
}

function culturinfo_audio_plain_text($post_id) {
    $post = get_post(absint($post_id));
    if (!$post || $post->post_type !== 'post') {
        return '';
    }

    $content = preg_replace('/<!--.*?-->/s', ' ', (string) $post->post_content);
    $content = strip_shortcodes($content);
    $content = wp_strip_all_tags($content, true);
    $excerpt = wp_strip_all_tags((string) $post->post_excerpt, true);
    $text = get_the_title($post) . '. ';
    if ($excerpt !== '') {
        $text .= $excerpt . '. ';
    }
    $text .= $content;
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, get_bloginfo('charset') ?: 'UTF-8');
    $text = preg_replace('#https?://\S+#iu', ' ', $text);
    $text = preg_replace('/\s+/u', ' ', $text);
    $text = trim((string) $text);

    $maximum = 60000;
    if (function_exists('mb_strlen') && mb_strlen($text, 'UTF-8') > $maximum) {
        $text = mb_substr($text, 0, $maximum, 'UTF-8');
    } elseif (strlen($text) > $maximum) {
        $text = substr($text, 0, $maximum);
    }

    return trim($text);
}

function culturinfo_audio_content_hash($post_id) {
    $text = culturinfo_audio_plain_text($post_id);
    return $text === '' ? '' : hash('sha256', CULTURINFO_AUDIO_VERSION . '|' . CULTURINFO_AUDIO_VOICE . '|' . $text);
}

function culturinfo_audio_get_queue() {
    $stored = get_option(CULTURINFO_AUDIO_QUEUE_OPTION, array());
    if (!is_array($stored)) {
        return array();
    }

    $queue = array();
    foreach ($stored as $post_id => $hash) {
        $post_id = absint($post_id);
        $hash = sanitize_text_field((string) $hash);
        if ($post_id && preg_match('/^[a-f0-9]{64}$/', $hash)) {
            $queue[$post_id] = $hash;
        }
    }
    return $queue;
}

function culturinfo_audio_save_queue($queue) {
    if (!$queue) {
        delete_option(CULTURINFO_AUDIO_QUEUE_OPTION);
        return;
    }
    update_option(CULTURINFO_AUDIO_QUEUE_OPTION, $queue, false);
}

function culturinfo_audio_schedule_worker($delay = 3) {
    if (!wp_next_scheduled(CULTURINFO_AUDIO_WORKER_HOOK)) {
        wp_schedule_single_event(time() + max(1, absint($delay)), CULTURINFO_AUDIO_WORKER_HOOK);
    }
}

function culturinfo_audio_safe_file($post_id, $relative = '') {
    $relative = $relative ?: get_post_meta($post_id, '_culturinfo_audio_file', true);
    $relative = ltrim(str_replace('\\', '/', (string) $relative), '/');
    $pattern = '#^culturinfo-audio/[0-9]{4}/[0-9]{2}/post-' . absint($post_id) . '-[a-f0-9]{16}\.mp3$#';
    if (!preg_match($pattern, $relative)) {
        return '';
    }

    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        return '';
    }
    $base = realpath($uploads['basedir']);
    $unresolved = trailingslashit($uploads['basedir']) . $relative;
    if (is_link($unresolved)) {
        return '';
    }
    $candidate = realpath($unresolved);
    if (!$base || !$candidate) {
        return '';
    }
    if (strpos($candidate, trailingslashit($base)) !== 0 || !is_file($candidate)) {
        return '';
    }
    return $candidate;
}

function culturinfo_audio_public_url($post_id) {
    $relative = ltrim(str_replace('\\', '/', (string) get_post_meta($post_id, '_culturinfo_audio_file', true)), '/');
    if (!culturinfo_audio_safe_file($post_id, $relative)) {
        return '';
    }
    $uploads = wp_upload_dir();
    $segments = array_map('rawurlencode', explode('/', $relative));
    return trailingslashit($uploads['baseurl']) . implode('/', $segments);
}

function culturinfo_audio_enqueue($post_id, $force = false) {
    $post_id = absint($post_id);
    $post = get_post($post_id);
    if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish') {
        return false;
    }

    $hash = culturinfo_audio_content_hash($post_id);
    if (!$hash) {
        update_post_meta($post_id, '_culturinfo_audio_status', 'error');
        update_post_meta($post_id, '_culturinfo_audio_error', 'La noticia no contiene texto suficiente para generar el audio.');
        return false;
    }

    $current_hash = get_post_meta($post_id, '_culturinfo_audio_hash', true);
    if (!$force && hash_equals((string) $current_hash, $hash) && culturinfo_audio_safe_file($post_id)) {
        return false;
    }

    $queue = culturinfo_audio_get_queue();
    $queue[$post_id] = $hash;
    culturinfo_audio_save_queue($queue);
    update_post_meta($post_id, '_culturinfo_audio_requested_hash', $hash);
    update_post_meta($post_id, '_culturinfo_audio_status', 'queued');
    delete_post_meta($post_id, '_culturinfo_audio_error');
    culturinfo_audio_schedule_worker();
    return true;
}

function culturinfo_audio_enqueue_on_publish($new_status, $old_status, $post) {
    unset($old_status);
    if ($post instanceof WP_Post && $post->post_type === 'post' && $new_status === 'publish') {
        culturinfo_audio_enqueue($post->ID);
    }
}
add_action('transition_post_status', 'culturinfo_audio_enqueue_on_publish', 20, 3);

function culturinfo_audio_enqueue_on_update($post_id, $post_after, $post_before) {
    if (!$post_after instanceof WP_Post || $post_after->post_type !== 'post' || $post_after->post_status !== 'publish') {
        return;
    }
    if ($post_after->post_title !== $post_before->post_title
        || $post_after->post_excerpt !== $post_before->post_excerpt
        || $post_after->post_content !== $post_before->post_content) {
        culturinfo_audio_enqueue($post_id);
    }
}
add_action('post_updated', 'culturinfo_audio_enqueue_on_update', 20, 3);

function culturinfo_audio_enqueue_existing() {
    $page = 1;
    do {
        $query = new WP_Query(array(
            'post_type'              => 'post',
            'post_status'            => 'publish',
            'posts_per_page'         => 100,
            'paged'                  => $page,
            'fields'                 => 'ids',
            'orderby'                => 'ID',
            'order'                  => 'ASC',
            'no_found_rows'          => false,
            'update_post_meta_cache' => false,
            'update_post_term_cache' => false,
        ));
        foreach ($query->posts as $post_id) {
            culturinfo_audio_enqueue($post_id);
        }
        $page++;
    } while ($page <= (int) $query->max_num_pages);
    wp_reset_postdata();
}

function culturinfo_audio_activate() {
    culturinfo_audio_enqueue_existing();
}
register_activation_hook(__FILE__, 'culturinfo_audio_activate');

function culturinfo_audio_keep_worker_alive() {
    if (culturinfo_audio_get_queue()) {
        culturinfo_audio_schedule_worker();
    }
}
add_action('init', 'culturinfo_audio_keep_worker_alive', 30);

function culturinfo_audio_acquire_lock() {
    $existing = get_option(CULTURINFO_AUDIO_LOCK_OPTION, array());
    if (is_array($existing) && !empty($existing['time']) && (time() - absint($existing['time'])) < 600) {
        return '';
    }
    if ($existing) {
        delete_option(CULTURINFO_AUDIO_LOCK_OPTION);
    }

    $token = wp_generate_uuid4();
    $created = add_option(CULTURINFO_AUDIO_LOCK_OPTION, array(
        'token' => $token,
        'time'  => time(),
    ), '', false);
    return $created ? $token : '';
}

function culturinfo_audio_release_lock($token) {
    $existing = get_option(CULTURINFO_AUDIO_LOCK_OPTION, array());
    if (is_array($existing) && isset($existing['token']) && hash_equals((string) $existing['token'], (string) $token)) {
        delete_option(CULTURINFO_AUDIO_LOCK_OPTION);
    }
}

function culturinfo_audio_run_command($command, $timeout = 300) {
    if (!function_exists('proc_open')) {
        return array('code' => 1, 'error' => 'La ejecución de procesos está deshabilitada.');
    }

    $descriptors = array(
        0 => array('pipe', 'r'),
        1 => array('pipe', 'w'),
        2 => array('pipe', 'w'),
    );
    $pipes = array();
    $process = proc_open($command, $descriptors, $pipes, null, null, array('bypass_shell' => true));
    if (!is_resource($process)) {
        return array('code' => 1, 'error' => 'No se pudo iniciar el generador.');
    }

    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $started = microtime(true);
    $output = '';
    $error = '';
    $exit_code = -1;

    while (true) {
        $output .= stream_get_contents($pipes[1]);
        $error .= stream_get_contents($pipes[2]);
        $status = proc_get_status($process);
        if (!$status['running']) {
            $exit_code = (int) $status['exitcode'];
            break;
        }
        if ((microtime(true) - $started) > $timeout) {
            proc_terminate($process);
            $error .= ' Tiempo máximo excedido.';
            $exit_code = 124;
            break;
        }
        usleep(100000);
    }

    $output .= stream_get_contents($pipes[1]);
    $error .= stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    $closed_code = proc_close($process);
    if ($exit_code < 0 && $closed_code >= 0) {
        $exit_code = $closed_code;
    }

    return array(
        'code'   => $exit_code,
        'output' => substr($output, -3000),
        'error'  => substr($error, -3000),
    );
}

function culturinfo_audio_generation_target($post_id, $hash) {
    $uploads = wp_upload_dir();
    if (!empty($uploads['error'])) {
        throw new RuntimeException('El directorio de medios no está disponible.');
    }

    $subdirectory = 'culturinfo-audio/' . wp_date('Y/m');
    $directory = trailingslashit($uploads['basedir']) . $subdirectory;
    if (is_link($directory) || (!is_dir($directory) && !wp_mkdir_p($directory))) {
        throw new RuntimeException('No se pudo crear el directorio de audio.');
    }

    $base_real = realpath($uploads['basedir']);
    $directory_real = realpath($directory);
    if (!$base_real || !$directory_real || strpos($directory_real, trailingslashit($base_real)) !== 0) {
        throw new RuntimeException('La ruta de audio no es válida.');
    }

    $filename = 'post-' . absint($post_id) . '-' . substr($hash, 0, 16) . '.mp3';
    return array(
        'directory' => $directory_real,
        'relative'  => $subdirectory . '/' . $filename,
        'file'      => trailingslashit($directory_real) . $filename,
    );
}

function culturinfo_audio_generate($post_id, $hash) {
    $python = culturinfo_audio_python_path();
    $model = culturinfo_audio_model_path();
    $lame = '/usr/bin/lame';
    if (!is_executable($python) || !is_file($model) || !is_executable($lame)) {
        throw new RuntimeException('El motor de audio local no está instalado correctamente.');
    }

    $text = culturinfo_audio_plain_text($post_id);
    if ($text === '') {
        throw new RuntimeException('La noticia no contiene texto para convertir.');
    }

    $text_file = wp_tempnam('culturinfo-audio-' . absint($post_id) . '.txt');
    if (!$text_file || file_put_contents($text_file, $text, LOCK_EX) === false) {
        throw new RuntimeException('No se pudo preparar el texto de la noticia.');
    }
    chmod($text_file, 0600);
    $wav_file = $text_file . '.wav';
    $mp3_file = $text_file . '.mp3';

    try {
        $piper = culturinfo_audio_run_command(array(
            $python,
            '-m',
            'piper',
            '-m',
            $model,
            '-f',
            $wav_file,
            '--input-file',
            $text_file,
            '--sentence-silence',
            '0.18',
        ), 300);
        if ($piper['code'] !== 0 || !is_file($wav_file) || filesize($wav_file) < 1024) {
            throw new RuntimeException('Piper no pudo generar la narración.');
        }

        $conversion = culturinfo_audio_run_command(array(
            $lame,
            '--silent',
            '-b',
            '64',
            '-m',
            'm',
            $wav_file,
            $mp3_file,
        ), 120);
        if ($conversion['code'] !== 0 || !is_file($mp3_file) || filesize($mp3_file) < 1024) {
            throw new RuntimeException('No se pudo convertir la narración a MP3.');
        }

        $duration = max(0, (int) round(max(0, filesize($wav_file) - 44) / 44100));

        $target = culturinfo_audio_generation_target($post_id, $hash);
        $part_file = $target['file'] . '.' . wp_generate_password(10, false, false) . '.part';
        if (!copy($mp3_file, $part_file)) {
            throw new RuntimeException('No se pudo guardar el MP3 en el volumen persistente.');
        }
        chmod($part_file, 0644);
        if (!rename($part_file, $target['file'])) {
            wp_delete_file($part_file);
            throw new RuntimeException('No se pudo publicar el archivo de audio.');
        }

        return array(
            'relative' => $target['relative'],
            'duration' => $duration,
        );
    } finally {
        foreach (array($text_file, $wav_file, $mp3_file) as $temporary) {
            if ($temporary && is_file($temporary)) {
                wp_delete_file($temporary);
            }
        }
    }
}

function culturinfo_audio_delete_relative($post_id, $relative) {
    $safe_file = culturinfo_audio_safe_file($post_id, $relative);
    if ($safe_file) {
        wp_delete_file($safe_file);
    }
}

function culturinfo_audio_process_queue() {
    $queue = culturinfo_audio_get_queue();
    if (!$queue) {
        return;
    }

    $token = culturinfo_audio_acquire_lock();
    if (!$token) {
        culturinfo_audio_schedule_worker(30);
        return;
    }

    $post_id = (int) array_key_first($queue);
    $requested_hash = (string) $queue[$post_id];
    try {
        $post = get_post($post_id);
        if (!$post || $post->post_type !== 'post' || $post->post_status !== 'publish') {
            $latest_queue = culturinfo_audio_get_queue();
            if (isset($latest_queue[$post_id]) && hash_equals($latest_queue[$post_id], $requested_hash)) {
                unset($latest_queue[$post_id]);
                culturinfo_audio_save_queue($latest_queue);
            }
            return;
        }

        $current_hash = culturinfo_audio_content_hash($post_id);
        if (!$current_hash || !hash_equals($current_hash, $requested_hash)) {
            culturinfo_audio_enqueue($post_id, true);
            return;
        }

        update_post_meta($post_id, '_culturinfo_audio_status', 'generating');
        $old_relative = get_post_meta($post_id, '_culturinfo_audio_file', true);
        $generated = culturinfo_audio_generate($post_id, $requested_hash);

        update_post_meta($post_id, '_culturinfo_audio_file', $generated['relative']);
        update_post_meta($post_id, '_culturinfo_audio_hash', $requested_hash);
        update_post_meta($post_id, '_culturinfo_audio_duration', absint($generated['duration']));
        update_post_meta($post_id, '_culturinfo_audio_generated_at', current_time('mysql'));
        update_post_meta($post_id, '_culturinfo_audio_status', 'ready');
        delete_post_meta($post_id, '_culturinfo_audio_error');
        delete_post_meta($post_id, '_culturinfo_audio_requested_hash');

        if ($old_relative && $old_relative !== $generated['relative']) {
            culturinfo_audio_delete_relative($post_id, $old_relative);
        }
    } catch (Throwable $error) {
        update_post_meta($post_id, '_culturinfo_audio_status', 'error');
        update_post_meta($post_id, '_culturinfo_audio_error', sanitize_text_field($error->getMessage()));
        error_log('Culturinfo audio: fallo al generar la noticia ' . $post_id . '. ' . $error->getMessage());
    } finally {
        $latest_queue = culturinfo_audio_get_queue();
        if (isset($latest_queue[$post_id]) && hash_equals((string) $latest_queue[$post_id], $requested_hash)) {
            unset($latest_queue[$post_id]);
            culturinfo_audio_save_queue($latest_queue);
        }
        culturinfo_audio_release_lock($token);
        if (culturinfo_audio_get_queue()) {
            culturinfo_audio_schedule_worker(5);
        }
    }
}
add_action(CULTURINFO_AUDIO_WORKER_HOOK, 'culturinfo_audio_process_queue');

function culturinfo_audio_remove_from_queue($post_id) {
    $post_id = absint($post_id);
    $queue = culturinfo_audio_get_queue();
    if (isset($queue[$post_id])) {
        unset($queue[$post_id]);
        culturinfo_audio_save_queue($queue);
    }
}
add_action('trashed_post', 'culturinfo_audio_remove_from_queue');

function culturinfo_audio_delete_post_file($post_id) {
    if (get_post_type($post_id) !== 'post') {
        return;
    }
    culturinfo_audio_remove_from_queue($post_id);
    culturinfo_audio_delete_relative($post_id, get_post_meta($post_id, '_culturinfo_audio_file', true));
}
add_action('before_delete_post', 'culturinfo_audio_delete_post_file');

function culturinfo_audio_format_duration($seconds) {
    $seconds = absint($seconds);
    if (!$seconds) {
        return '';
    }
    return sprintf('%d:%02d', floor($seconds / 60), $seconds % 60);
}

function culturinfo_audio_render_player($post_id = 0) {
    $post_id = absint($post_id ?: get_the_ID());
    $status = get_post_meta($post_id, '_culturinfo_audio_status', true);
    $heading_id = 'article-audio-title-' . $post_id;
    if (in_array($status, array('queued', 'generating'), true)) {
        ?>
        <section class="article-reader article-reader-processing site-shell" data-culturinfo-audio-state="<?php echo esc_attr($status); ?>" aria-labelledby="<?php echo esc_attr($heading_id); ?>">
            <div class="article-reader-icon" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
            <div class="article-reader-copy">
                <span class="article-reader-kicker"><?php esc_html_e('Versión en audio', 'culturinfo-audio'); ?></span>
                <h2 id="<?php echo esc_attr($heading_id); ?>"><?php esc_html_e('Audio en preparación', 'culturinfo-audio'); ?></h2>
                <p><?php esc_html_e('La narración se agregará automáticamente cuando termine el procesamiento.', 'culturinfo-audio'); ?></p>
            </div>
            <span class="article-reader-processing-label"><?php echo $status === 'generating' ? esc_html__('Generando MP3…', 'culturinfo-audio') : esc_html__('En cola', 'culturinfo-audio'); ?></span>
        </section>
        <?php
        return;
    }

    if ($status !== 'ready') {
        return;
    }
    $url = culturinfo_audio_public_url($post_id);
    if (!$url) {
        return;
    }
    $duration = culturinfo_audio_format_duration(get_post_meta($post_id, '_culturinfo_audio_duration', true));
    ?>
    <section class="article-reader article-reader-ready site-shell" data-culturinfo-audio aria-labelledby="<?php echo esc_attr($heading_id); ?>">
        <div class="article-reader-icon" aria-hidden="true"><span></span><span></span><span></span><span></span></div>
        <div class="article-reader-copy">
            <span class="article-reader-kicker"><?php esc_html_e('Versión en audio', 'culturinfo-audio'); ?></span>
            <h2 id="<?php echo esc_attr($heading_id); ?>"><?php esc_html_e('Escuchar esta noticia', 'culturinfo-audio'); ?></h2>
            <p><?php echo esc_html(CULTURINFO_AUDIO_VOICE_LABEL . ($duration ? ' · ' . $duration : '')); ?></p>
        </div>
        <div class="article-reader-audio-controls">
            <audio controls preload="metadata" data-article-audio>
                <source src="<?php echo esc_url($url); ?>" type="audio/mpeg">
                <?php esc_html_e('Tu navegador no puede reproducir este audio.', 'culturinfo-audio'); ?>
            </audio>
            <label class="article-reader-speed">
                <span><?php esc_html_e('Velocidad', 'culturinfo-audio'); ?></span>
                <select data-audio-rate>
                    <option value="0.75">0.75×</option>
                    <option value="1" selected>1×</option>
                    <option value="1.25">1.25×</option>
                    <option value="1.5">1.5×</option>
                    <option value="2">2×</option>
                </select>
            </label>
        </div>
    </section>
    <?php
}

function culturinfo_audio_enqueue_assets() {
    if (!is_singular('post')) {
        return;
    }
    $post_id = get_queried_object_id();
    if (get_post_meta($post_id, '_culturinfo_audio_status', true) !== 'ready' || !culturinfo_audio_public_url($post_id)) {
        return;
    }
    $path = plugin_dir_path(__FILE__) . 'assets/audio-player.js';
    wp_enqueue_script(
        'culturinfo-audio-player',
        plugins_url('assets/audio-player.js', __FILE__),
        array(),
        file_exists($path) ? (string) filemtime($path) : CULTURINFO_AUDIO_VERSION,
        true
    );
}
add_action('wp_enqueue_scripts', 'culturinfo_audio_enqueue_assets');

function culturinfo_audio_add_meta_box() {
    add_meta_box(
        'culturinfo-article-audio',
        'Audio de la noticia',
        'culturinfo_audio_meta_box',
        'post',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'culturinfo_audio_add_meta_box');

function culturinfo_audio_status_label($status) {
    $labels = array(
        'queued'     => 'En cola',
        'generating' => 'Generando MP3',
        'ready'      => 'Disponible',
        'error'      => 'Requiere atención',
    );
    return isset($labels[$status]) ? $labels[$status] : 'Pendiente de publicación';
}

function culturinfo_audio_meta_box($post) {
    wp_nonce_field('culturinfo_audio_regenerate', 'culturinfo_audio_nonce');
    $status = get_post_meta($post->ID, '_culturinfo_audio_status', true);
    $error = get_post_meta($post->ID, '_culturinfo_audio_error', true);
    $generated_at = get_post_meta($post->ID, '_culturinfo_audio_generated_at', true);
    $url = culturinfo_audio_public_url($post->ID);
    ?>
    <p><strong>Estado:</strong> <?php echo esc_html(culturinfo_audio_status_label($status)); ?></p>
    <?php if ($generated_at) : ?><p class="description">Última generación: <?php echo esc_html($generated_at); ?></p><?php endif; ?>
    <?php if ($url) : ?><p><a href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener">Escuchar MP3 actual</a></p><?php endif; ?>
    <?php if ($error) : ?><p style="color:#b32d2e"><strong>Error:</strong> <?php echo esc_html($error); ?></p><?php endif; ?>
    <p>
        <label>
            <input type="checkbox" name="culturinfo_audio_regenerate" value="1">
            Regenerar el audio al guardar
        </label>
    </p>
    <p class="description">Las noticias nuevas o modificadas se procesan automáticamente después de publicarse.</p>
    <?php
}

function culturinfo_audio_manual_regeneration($post_id) {
    if (!isset($_POST['culturinfo_audio_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['culturinfo_audio_nonce'])), 'culturinfo_audio_regenerate')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id) || empty($_POST['culturinfo_audio_regenerate'])) {
        return;
    }
    if (get_post_status($post_id) === 'publish') {
        culturinfo_audio_enqueue($post_id, true);
    }
}
add_action('save_post_post', 'culturinfo_audio_manual_regeneration', 40);

function culturinfo_audio_posts_column($columns) {
    $result = array();
    foreach ($columns as $key => $label) {
        $result[$key] = $label;
        if ($key === 'comments') {
            $result['culturinfo_audio'] = 'Audio';
        }
    }
    if (!isset($result['culturinfo_audio'])) {
        $result['culturinfo_audio'] = 'Audio';
    }
    return $result;
}
add_filter('manage_post_posts_columns', 'culturinfo_audio_posts_column');

function culturinfo_audio_posts_column_content($column, $post_id) {
    if ($column === 'culturinfo_audio') {
        echo esc_html(culturinfo_audio_status_label(get_post_meta($post_id, '_culturinfo_audio_status', true)));
    }
}
add_action('manage_post_posts_custom_column', 'culturinfo_audio_posts_column_content', 10, 2);
