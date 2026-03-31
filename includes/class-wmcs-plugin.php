<?php

if (! defined('ABSPATH')) {
    exit;
}

class WMCS_Plugin {
    private const ATTACHMENT_PROCESS_HOOK = 'wmcs_process_attachment_media';
    private const MAX_ATTACHMENT_PROCESS_ATTEMPTS = 3;
    private const CACHE_PREFIX = 'wmcs_cache_';
    private const CACHE_VERSION_OPTION = 'wmcs_cache_version';
    private static $instance = null;
    private $settings;
    private $logger;
    private $client;

    public static function instance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    private function __construct() {
        $this->settings = new WMCS_Settings();
        $this->logger = new WMCS_Logger();
        $this->client = new WMCS_S3_Client($this->logger);

        add_action('admin_menu', array($this, 'register_menu'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_assets'));
        add_action('rest_api_init', array($this, 'register_rest_routes'));
        add_action(self::ATTACHMENT_PROCESS_HOOK, array($this, 'process_attachment_media'), 10, 2);

        // Restore the upload-time controls that are explicitly user-configurable.
        add_filter('upload_dir', array($this, 'filter_upload_dir'));
        add_filter('wp_handle_upload_prefilter', array($this, 'filter_upload_prefilter'));
        add_filter('upload_size_limit', array($this, 'filter_upload_size_limit'));
        add_filter('upload_mimes', array($this, 'filter_upload_mimes'));
        add_filter('intermediate_image_sizes_advanced', array($this, 'filter_intermediate_image_sizes_advanced'), 20, 3);
        add_filter('big_image_size_threshold', array($this, 'filter_big_image_size_threshold'), 20, 4);
        add_filter('wp_generate_attachment_metadata', array($this, 'queue_attachment_processing'), 20, 3);
        add_filter('wp_get_attachment_url', array($this, 'filter_attachment_url'), 20, 2);
        add_filter('the_content', array($this, 'filter_content_attachment_urls'), 20);
    }

    public function register_menu() {
        add_menu_page(
            'WP Multi Cloud Storage',
            'Cloud Storage',
            'manage_options',
            'wmcs',
            array($this, 'render_admin_page'),
            'dashicons-cloud',
            58
        );
    }

    public function render_admin_page() {
        if (! current_user_can('manage_options')) {
            return;
        }

        $access = $this->validate_ip_whitelist_access($this->settings->get());
        if (is_wp_error($access)) {
            wp_die(
                esc_html($access->get_error_message()),
                esc_html__('Access denied', 'wp-multi-cloud-storage'),
                array('response' => 403)
            );
        }

        echo '<div class="wrap wmcs-admin-page"><div id="wmcs-admin-root"></div>';

        if (! file_exists(WMCS_PLUGIN_PATH . 'assets/build/wmcs-admin.js')) {
            echo '<div class="notice notice-warning" style="margin-top:16px;"><p>';
            echo esc_html__('Build assets are missing. Run npm install and npm run build in this plugin directory.', 'wp-multi-cloud-storage');
            echo '</p></div>';
        }

        echo '</div>';
    }

    public function enqueue_assets($hook_suffix) {
        if ($hook_suffix !== 'toplevel_page_wmcs') {
            return;
        }

        $access = $this->validate_ip_whitelist_access($this->settings->get());
        if (is_wp_error($access)) {
            return;
        }

        $css_file = WMCS_PLUGIN_PATH . 'assets/build/wmcs-admin.css';
        $css_url = WMCS_PLUGIN_URL . 'assets/build/wmcs-admin.css';
        $js_file = WMCS_PLUGIN_PATH . 'assets/build/wmcs-admin.js';

        if (! file_exists($css_file)) {
            $fallback_css_file = WMCS_PLUGIN_PATH . 'assets/build/assets/wmcs-admin.css';
            if (file_exists($fallback_css_file)) {
                $css_file = $fallback_css_file;
                $css_url = WMCS_PLUGIN_URL . 'assets/build/assets/wmcs-admin.css';
            }
        }

        if (file_exists($css_file)) {
            wp_enqueue_style('wmcs-admin', $css_url, array(), filemtime($css_file));
        }

        if (file_exists($js_file)) {
            wp_enqueue_script('wmcs-admin', WMCS_PLUGIN_URL . 'assets/build/wmcs-admin.js', array(), filemtime($js_file), true);
            wp_add_inline_script('wmcs-admin', 'window.wmcsAdmin = ' . wp_json_encode(array(
                'restUrl'  => rest_url('wmcs/v1'),
                'nonce'    => wp_create_nonce('wp_rest'),
                'version'  => WMCS_VERSION,
                'settings' => $this->format_public_settings($this->settings->get()),
                'stats'    => $this->logger->get_stats(),
            )) . ';', 'before');
        }
    }

    public function register_rest_routes() {
        register_rest_route('wmcs/v1', '/settings', array(
            array(
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => array($this, 'rest_get_settings'),
                'permission_callback' => array($this, 'can_manage'),
            ),
            array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, 'rest_save_settings'),
                'permission_callback' => array($this, 'can_manage'),
            ),
        ));

        foreach (array(
            '/test-connection' => 'rest_test_connection',
            '/clear-cache'     => 'rest_clear_cache',
            '/reset'           => 'rest_reset_settings',
            '/import'          => 'rest_import_settings',
        ) as $route => $callback) {
            register_rest_route('wmcs/v1', $route, array(
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => array($this, $callback),
                'permission_callback' => array($this, 'can_manage'),
            ));
        }

        register_rest_route('wmcs/v1', '/export', array(
            'methods'             => WP_REST_Server::READABLE,
            'callback'            => array($this, 'rest_export_settings'),
            'permission_callback' => array($this, 'can_manage'),
        ));
    }

    public function can_manage() {
        if (! current_user_can('manage_options')) {
            return false;
        }

        return $this->validate_ip_whitelist_access($this->settings->get());
    }

    public function rest_get_settings() {
        $settings = $this->settings->get();

        return rest_ensure_response(array(
            'settings' => $this->format_public_settings($settings),
            'stats'    => $this->logger->get_stats(),
            'logs'     => $this->logger->get_logs(20, $settings['advanced']['logRetention']),
        ));
    }

    public function rest_save_settings(WP_REST_Request $request) {
        $incoming = $this->normalize_incoming_settings($request->get_param('settings'));
        $sanitized = $this->settings->sanitize($incoming);
        $validation = $this->validate_settings_constraints($sanitized);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $settings = $this->settings->update($incoming);
        $this->invalidate_runtime_cache();
        $this->logger->log('info', 'Settings saved.');

        return $this->format_settings_response($settings);
    }

    public function rest_test_connection(WP_REST_Request $request) {
        $settings = $this->settings->sanitize($this->normalize_incoming_settings($request->get_param('settings')));
        $validation = $this->validate_settings_constraints($settings);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $cache_key = $this->get_runtime_cache_key('connection_test', md5(wp_json_encode($settings)));
        $cached_result = $this->get_cached_runtime_value($settings, $cache_key);
        if (is_array($cached_result)) {
            $this->logger->set_stat('lastTestedAt', gmdate('c'));
            $this->logger->log('info', 'Connection test served from cache.');

            return rest_ensure_response($cached_result + array('cached' => true));
        }

        $result = $this->client->test_connection($settings);

        if (is_wp_error($result)) {
            $this->logger->log('error', 'Connection test failed.', array(
                'message' => $result->get_error_message(),
            ));

            return new WP_REST_Response(array(
                'success' => false,
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_data()['code'] ?? null,
            ), 400);
        }

        $this->logger->set_stat('lastTestedAt', gmdate('c'));
        $this->set_cached_runtime_value($settings, $cache_key, $result);
        $this->logger->log('info', 'Connection test passed.', array(
            'code' => $result['code'] ?? null,
        ));

        return rest_ensure_response($result);
    }

    public function rest_clear_cache() {
        global $wpdb;

        if (isset($wpdb)) {
            $like_prefix = esc_sql($wpdb->esc_like('_transient_' . self::CACHE_PREFIX) . '%');
            $like_timeout_prefix = esc_sql($wpdb->esc_like('_transient_timeout_' . self::CACHE_PREFIX) . '%');
            $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '{$like_prefix}' OR option_name LIKE '{$like_timeout_prefix}'");
        }

        $this->invalidate_runtime_cache();
        $this->logger->log('info', 'Plugin cache cleared.');

        return rest_ensure_response(array(
            'message' => 'Cache cleared.',
        ));
    }

    public function rest_reset_settings() {
        $settings = $this->settings->reset();
        $this->invalidate_runtime_cache();
        $this->logger->log('warning', 'Settings reset to defaults.');

        return $this->format_settings_response($settings);
    }

    public function rest_export_settings() {
        return rest_ensure_response(array(
            'settings' => $this->format_public_settings($this->settings->export()),
        ));
    }

    public function rest_import_settings(WP_REST_Request $request) {
        $incoming = $this->normalize_incoming_settings($request->get_param('settings'));
        $sanitized = $this->settings->sanitize($incoming);
        $validation = $this->validate_settings_constraints($sanitized);
        if (is_wp_error($validation)) {
            return $validation;
        }

        $settings = $this->settings->import($incoming);
        $this->invalidate_runtime_cache();
        $this->logger->log('info', 'Settings imported.');

        return $this->format_settings_response($settings);
    }

    public function filter_upload_dir($uploads) {
        $settings = $this->settings->get();
        if (! $this->is_runtime_enabled($settings)) {
            return $uploads;
        }

        $path_template = trim((string) ($settings['upload']['path'] ?? ''));
        if ($path_template === '') {
            return $uploads;
        }

        $subdir = '/' . trim($this->replace_path_tokens($path_template), '/');
        $uploads['subdir'] = $subdir;
        $uploads['path'] = $uploads['basedir'] . $subdir;
        $uploads['url'] = $uploads['baseurl'] . $subdir;

        return $uploads;
    }

    public function filter_upload_prefilter($file) {
        $settings = $this->settings->get();
        if (! $this->is_runtime_enabled($settings) || empty($settings['upload']['autoRename'])) {
            return $file;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $name = pathinfo($file['name'], PATHINFO_FILENAME);
        $pattern = $settings['upload']['namingPattern'] ?? 'uuid';

        switch ($pattern) {
            case 'original':
                $new_name = sanitize_title($name);
                break;
            case 'timestamp':
                $new_name = gmdate('YmdHis') . '-' . wp_rand(1000, 9999);
                break;
            case 'md5':
                $new_name = md5($name . microtime(true) . wp_rand());
                break;
            case 'uuid':
            default:
                $new_name = wp_generate_uuid4();
                break;
        }

        $file['name'] = $new_name . ($extension ? '.' . strtolower($extension) : '');

        return $file;
    }

    public function filter_upload_size_limit($size) {
        $settings = $this->settings->get();
        $limit = absint($settings['advanced']['maxUploadSize'] ?? 100);

        return min($size, $limit * MB_IN_BYTES);
    }

    public function filter_upload_mimes($mimes) {
        $settings = $this->settings->get();
        if (empty($settings['advanced']['restrictTypes'])) {
            return $mimes;
        }

        return array(
            'jpg|jpeg|jpe' => 'image/jpeg',
            'gif'          => 'image/gif',
            'png'          => 'image/png',
            'webp'         => 'image/webp',
            'svg'          => 'image/svg+xml',
            'pdf'          => 'application/pdf',
            'zip'          => 'application/zip',
            'mp4'          => 'video/mp4',
            'mp3'          => 'audio/mpeg',
        );
    }

    public function filter_intermediate_image_sizes_advanced($sizes, $image_meta, $attachment_id) {
        $settings = $this->settings->get();
        if (! $this->is_runtime_enabled($settings) || ! wp_attachment_is_image($attachment_id)) {
            return $sizes;
        }

        if (empty($settings['image']['autoThumbnail'])) {
            return array();
        }

        $custom_sizes = $settings['image']['customSizes'] ?? array();
        if (! is_array($custom_sizes) || empty($custom_sizes)) {
            return $sizes;
        }

        foreach ($custom_sizes as $size) {
            if (! is_array($size)) {
                continue;
            }

            $name = sanitize_key($size['name'] ?? '');
            $width = absint($size['width'] ?? 0);
            $height = absint($size['height'] ?? 0);

            if ($name === '' || $width < 1 || $height < 1) {
                continue;
            }

            $sizes[$name] = array(
                'width'  => $width,
                'height' => $height,
                'crop'   => ! empty($size['crop']),
            );
        }

        return $sizes;
    }

    public function filter_big_image_size_threshold($threshold, $imagesize, $file, $attachment_id) {
        $settings = $this->settings->get();
        if (! $this->is_runtime_enabled($settings) || ! wp_attachment_is_image($attachment_id)) {
            return $threshold;
        }

        if (empty($settings['image']['autoThumbnail'])) {
            return false;
        }

        return $threshold;
    }

    public function process_attachment_images($metadata, $attachment_id, $context = 'create') {
        $settings = $this->settings->get();
        if (! $this->is_runtime_enabled($settings) || empty($settings['image']['enableWebp'])) {
            return $metadata;
        }

        if (! wp_attachment_is_image($attachment_id) || empty($metadata['file'])) {
            return $metadata;
        }

        if (! wp_image_editor_supports(array(
            'mime_type' => 'image/webp',
            'methods'   => array('save'),
        ))) {
            $this->logger->log('warning', 'WebP conversion skipped because this server does not support saving WebP images.', array(
                'attachmentId' => $attachment_id,
                'context'      => $context,
            ));

            return $metadata;
        }

        $attached_file = get_attached_file($attachment_id);
        if (! $attached_file || ! file_exists($attached_file)) {
            return $metadata;
        }

        $source_mime = wp_get_image_mime($attached_file);
        if (! in_array($source_mime, array('image/jpeg', 'image/png'), true)) {
            return $metadata;
        }

        $max_size_bytes = max(1, absint($settings['image']['webpMaxSize'] ?? 10)) * MB_IN_BYTES;
        $source_size = filesize($attached_file);
        if ($source_size !== false && $source_size > $max_size_bytes) {
            $this->logger->log('info', 'WebP conversion skipped because the source image exceeds the configured size limit.', array(
                'attachmentId' => $attachment_id,
                'path'         => $attached_file,
                'size'         => $source_size,
                'limit'        => $max_size_bytes,
            ));

            return $metadata;
        }

        $quality = min(100, max(1, absint($settings['image']['webpQuality'] ?? 85)));
        $keep_original = ! empty($settings['image']['keepOriginal']);
        $upload_dir = wp_get_upload_dir();
        $basedir = trailingslashit($upload_dir['basedir']);
        $converted_any = false;

        $converted_original = $this->convert_image_to_webp($attached_file, $quality);
        if (! is_wp_error($converted_original)) {
            $converted_any = true;
            $metadata['file'] = ltrim(str_replace('\\', '/', str_replace($basedir, '', $converted_original['path'])), '/');
            $metadata['filesize'] = $converted_original['filesize'] ?? filesize($converted_original['path']);
            update_attached_file($attachment_id, $converted_original['path']);
            wp_update_post(array(
                'ID'             => $attachment_id,
                'post_mime_type' => 'image/webp',
            ));

            if (! $keep_original && $attached_file !== $converted_original['path'] && file_exists($attached_file)) {
                wp_delete_file($attached_file);
            }
        } else {
            $this->logger->log('error', 'WebP conversion failed for the main attachment image.', array(
                'attachmentId' => $attachment_id,
                'path'         => $attached_file,
                'message'      => $converted_original->get_error_message(),
            ));

            return $metadata;
        }

        if (! empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            $base_dir = trailingslashit(dirname($attached_file));

            foreach ($metadata['sizes'] as $size_name => $size_data) {
                if (empty($size_data['file'])) {
                    continue;
                }

                $size_path = $base_dir . $size_data['file'];
                if (! file_exists($size_path)) {
                    continue;
                }

                $size_mime = wp_get_image_mime($size_path);
                if (! in_array($size_mime, array('image/jpeg', 'image/png'), true)) {
                    continue;
                }

                $converted_size = $this->convert_image_to_webp($size_path, $quality);
                if (is_wp_error($converted_size)) {
                    $this->logger->log('warning', 'WebP conversion failed for an image sub-size.', array(
                        'attachmentId' => $attachment_id,
                        'size'         => $size_name,
                        'path'         => $size_path,
                        'message'      => $converted_size->get_error_message(),
                    ));
                    continue;
                }

                $converted_any = true;
                $metadata['sizes'][$size_name]['file'] = $converted_size['file'];
                $metadata['sizes'][$size_name]['mime-type'] = 'image/webp';
                if (isset($converted_size['filesize'])) {
                    $metadata['sizes'][$size_name]['filesize'] = $converted_size['filesize'];
                }

                if (! $keep_original && $size_path !== $converted_size['path'] && file_exists($size_path)) {
                    wp_delete_file($size_path);
                }
            }
        }

        if ($converted_any) {
            $this->logger->log('info', 'Attachment converted to WebP.', array(
                'attachmentId' => $attachment_id,
                'context'      => $context,
                'keepOriginal' => $keep_original,
            ));
        }

        return $metadata;
    }

    public function queue_attachment_processing($metadata, $attachment_id, $context = 'create') {
        if ($context !== 'create') {
            return $metadata;
        }

        $settings = $this->settings->get();
        if (! $this->should_process_attachment($settings, $attachment_id, $metadata)) {
            return $metadata;
        }

        if (! wp_next_scheduled(self::ATTACHMENT_PROCESS_HOOK, array($attachment_id, 1))) {
            wp_schedule_single_event(time() + 5, self::ATTACHMENT_PROCESS_HOOK, array($attachment_id, 1));
            $this->logger->log('info', 'Attachment queued for background processing.', array(
                'attachmentId' => $attachment_id,
                'context'      => $context,
            ));
        }

        return $metadata;
    }

    public function process_attachment_media($attachment_id, $attempt = 1) {
        $settings = $this->settings->get();
        $attempt = max(1, absint($attempt));

        $metadata = wp_get_attachment_metadata($attachment_id);
        if (! is_array($metadata) || empty($metadata['file'])) {
            $this->schedule_attachment_processing_retry($attachment_id, $attempt, 'Attachment metadata is not ready yet.');
            return;
        }

        $attached_file = get_attached_file($attachment_id);
        if (! $attached_file || ! file_exists($attached_file)) {
            $this->schedule_attachment_processing_retry($attachment_id, $attempt, 'Attachment file is not available yet.');
            return;
        }

        if (! $this->is_runtime_enabled($settings)) {
            return;
        }

        if (! empty($settings['image']['enableWebp']) && wp_attachment_is_image($attachment_id)) {
            $metadata = $this->process_attachment_images($metadata, $attachment_id, 'async');
            wp_update_attachment_metadata($attachment_id, $metadata);
        }

        if (! $this->can_offload($settings)) {
            return;
        }

        $offload_result = $this->process_attachment_offload($attachment_id);
        if (! $offload_result) {
            $this->schedule_attachment_processing_retry($attachment_id, $attempt, 'Background offload did not complete successfully.');
        }
    }

    public function process_attachment_offload($attachment_id) {
        $settings = $this->settings->get();
        if (! $this->can_offload($settings)) {
            return true;
        }

        if (get_post_meta($attachment_id, '_wmcs_remote_url', true)) {
            return true;
        }

        $metadata = wp_get_attachment_metadata($attachment_id);
        $attached_file = get_attached_file($attachment_id);
        if (! $attached_file || ! file_exists($attached_file)) {
            return false;
        }

        $upload_dir = wp_get_upload_dir();
        $base_dir = trailingslashit($upload_dir['basedir']);
        $relative_file = ltrim(str_replace('\\', '/', str_replace($base_dir, '', $attached_file)), '/');
        $files = array(array('path' => $attached_file, 'key' => $relative_file));

        if (! empty($metadata['sizes']) && is_array($metadata['sizes'])) {
            $current_dir = trailingslashit(dirname($attached_file));
            $relative_dir = dirname($relative_file);

            foreach ($metadata['sizes'] as $size) {
                if (! empty($size['file'])) {
                    $files[] = array(
                        'path' => $current_dir . $size['file'],
                        'key'  => trim($relative_dir . '/' . $size['file'], '/'),
                    );
                }
            }
        }

        $remote_objects = array();
        foreach ($files as $file) {
            if (! file_exists($file['path'])) {
                continue;
            }

            $mime = wp_check_filetype($file['path']);
            $response = $this->client->put_object(
                $settings,
                $file['key'],
                $file['path'],
                $mime['type'] ?? 'application/octet-stream'
            );

            $code = is_wp_error($response) ? 0 : wp_remote_retrieve_response_code($response);
            if (is_wp_error($response) || $code < 200 || $code >= 300) {
                $this->logger->log('error', 'Attachment offload failed.', array(
                    'attachmentId' => $attachment_id,
                    'file'         => $file['key'],
                    'code'         => $code,
                ));

                return false;
            }

            $remote_objects[$file['key']] = $this->client->get_object_url($settings, $file['key']);
        }

        if (! empty($remote_objects[$relative_file])) {
            update_post_meta($attachment_id, '_wmcs_remote_url', esc_url_raw($remote_objects[$relative_file]));
            update_post_meta($attachment_id, '_wmcs_remote_objects', $remote_objects);
            $this->invalidate_runtime_cache();

            $this->logger->increment_stat('totalUploads');
            $this->logger->increment_stat('offloadedAttachments');
            $this->logger->set_stat('lastSyncAt', gmdate('c'));
            $this->logger->log('info', 'Attachment offloaded.', array(
                'attachmentId' => $attachment_id,
            ));
        }

        if (empty($settings['upload']['keepLocal'])) {
            foreach ($files as $file) {
                if (file_exists($file['path'])) {
                    wp_delete_file($file['path']);
                }
            }
        }

        return true;
    }

    public function filter_attachment_url($url, $attachment_id) {
        $settings = $this->settings->get();
        if (empty($settings['upload']['replaceUrl'])) {
            return $url;
        }

        $remote_url = get_post_meta($attachment_id, '_wmcs_remote_url', true);

        return $remote_url ? esc_url_raw($remote_url) : $url;
    }

    public function filter_content_attachment_urls($content) {
        if (! is_string($content) || $content === '') {
            return $content;
        }

        $settings = $this->settings->get();
        if (! $this->is_runtime_enabled($settings) || empty($settings['upload']['replaceUrl'])) {
            return $content;
        }

        if (strpos($content, 'wp-image-') === false) {
            return $content;
        }

        preg_match_all('/wp-image-(\d+)/', $content, $matches);
        $attachment_ids = array_values(array_unique(array_map('absint', $matches[1] ?? array())));
        if (empty($attachment_ids)) {
            return $content;
        }

        $replacements = array();
        foreach ($attachment_ids as $attachment_id) {
            $replacements += $this->get_attachment_url_replacements($attachment_id);
        }

        if (empty($replacements)) {
            return $content;
        }

        // Replace longer URLs first so srcset entries are rewritten predictably.
        uksort($replacements, static function ($left, $right) {
            return strlen($right) <=> strlen($left);
        });

        return strtr($content, $replacements);
    }

    private function format_settings_response($settings) {
        return rest_ensure_response(array(
            'settings' => $this->format_public_settings($settings),
            'stats'    => $this->logger->get_stats(),
            'logs'     => $this->logger->get_logs(20, $settings['advanced']['logRetention']),
        ));
    }

    private function normalize_incoming_settings($settings) {
        if (! is_array($settings)) {
            return array();
        }

        if (isset($settings['providerConfig']) && is_array($settings['providerConfig'])) {
            $settings['provider_config'] = $settings['providerConfig'];
        }

        unset($settings['providerConfig']);

        return $settings;
    }

    private function format_public_settings($settings) {
        if (! is_array($settings)) {
            return array();
        }

        if (isset($settings['provider_config']) && is_array($settings['provider_config'])) {
            $settings['providerConfig'] = $settings['provider_config'];
        }

        unset($settings['provider_config']);

        return $settings;
    }

    private function is_runtime_enabled($settings) {
        return ! empty($settings['enabled']);
    }

    private function can_offload($settings) {
        if (! $this->is_runtime_enabled($settings)) {
            return false;
        }

        $provider = $settings['provider_config'] ?? array();

        return ! empty($provider['endpoint'])
            && ! empty($provider['region'])
            && ! empty($provider['bucket'])
            && ! empty($provider['accessKey'])
            && ! empty($provider['secretKey']);
    }

    private function should_process_attachment($settings, $attachment_id, $metadata) {
        if (! $this->is_runtime_enabled($settings) || ! is_array($metadata) || empty($metadata['file'])) {
            return false;
        }

        if (! wp_attachment_is_image($attachment_id)) {
            return $this->can_offload($settings);
        }

        return ! empty($settings['image']['enableWebp']) || $this->can_offload($settings);
    }

    private function get_attachment_url_replacements($attachment_id) {
        $settings = $this->settings->get();
        $remote_url = get_post_meta($attachment_id, '_wmcs_remote_url', true);
        $remote_objects = get_post_meta($attachment_id, '_wmcs_remote_objects', true);
        $relative_file = get_post_meta($attachment_id, '_wp_attached_file', true);
        $upload_dir = wp_get_upload_dir();
        $base_url = trailingslashit($upload_dir['baseurl']);

        if (! $remote_url || ! is_string($relative_file) || trim($relative_file) === '') {
            return array();
        }

        $cache_key = $this->get_runtime_cache_key('attachment_urls', implode('|', array(
            $attachment_id,
            $relative_file,
            $remote_url,
            md5(wp_json_encode($remote_objects)),
            $base_url,
        )));
        $cached_replacements = $this->get_cached_runtime_value($settings, $cache_key);
        if (is_array($cached_replacements)) {
            return $cached_replacements;
        }

        $replacements = array(
            $base_url . ltrim(str_replace('\\', '/', $relative_file), '/') => esc_url_raw($remote_url),
        );

        if (! is_array($remote_objects)) {
            return $replacements;
        }

        foreach ($remote_objects as $relative_path => $remote_object_url) {
            if (! is_string($relative_path) || ! is_string($remote_object_url) || $relative_path === '') {
                continue;
            }

            $replacements[$base_url . ltrim(str_replace('\\', '/', $relative_path), '/')] = esc_url_raw($remote_object_url);
        }

        $this->set_cached_runtime_value($settings, $cache_key, $replacements);

        return $replacements;
    }

    private function schedule_attachment_processing_retry($attachment_id, $attempt, $reason) {
        if ($attempt >= self::MAX_ATTACHMENT_PROCESS_ATTEMPTS) {
            $this->logger->log('error', 'Attachment background processing stopped after repeated failures.', array(
                'attachmentId' => $attachment_id,
                'attempt'      => $attempt,
                'reason'       => $reason,
            ));

            return;
        }

        $next_attempt = $attempt + 1;
        wp_schedule_single_event(time() + (15 * $next_attempt), self::ATTACHMENT_PROCESS_HOOK, array($attachment_id, $next_attempt));
        $this->logger->log('warning', 'Attachment background processing was rescheduled.', array(
            'attachmentId' => $attachment_id,
            'attempt'      => $next_attempt,
            'reason'       => $reason,
        ));
    }

    private function validate_settings_constraints($settings) {
        $advanced = $settings['advanced'] ?? array();
        if (empty($advanced['ipWhitelist'])) {
            return true;
        }

        $entries = $advanced['ipWhitelistEntries'] ?? array();
        if (! is_array($entries) || empty($entries)) {
            return new WP_Error(
                'wmcs_ip_whitelist_empty',
                'IP whitelist is enabled but no IP addresses or CIDR ranges are configured.',
                array('status' => 400)
            );
        }

        foreach ($entries as $entry) {
            if (! $this->is_valid_ip_whitelist_entry($entry)) {
                return new WP_Error(
                    'wmcs_ip_whitelist_invalid',
                    sprintf('Invalid IP whitelist entry: %s', sanitize_text_field((string) $entry)),
                    array('status' => 400)
                );
            }
        }

        return true;
    }

    private function validate_ip_whitelist_access($settings) {
        $advanced = $settings['advanced'] ?? array();
        if (empty($advanced['ipWhitelist'])) {
            return true;
        }

        $entries = $advanced['ipWhitelistEntries'] ?? array();
        if (! is_array($entries) || empty($entries)) {
            return new WP_Error(
                'wmcs_ip_whitelist_empty',
                'IP whitelist is enabled but no IP addresses or CIDR ranges are configured.',
                array('status' => 403)
            );
        }

        $request_ip = $this->get_current_request_ip();
        if ($request_ip === '') {
            return new WP_Error(
                'wmcs_ip_unknown',
                'Could not determine the current request IP address.',
                array('status' => 403)
            );
        }

        foreach ($entries as $entry) {
            if ($this->ip_matches_whitelist_entry($request_ip, $entry)) {
                return true;
            }
        }

        return new WP_Error(
            'wmcs_ip_denied',
            sprintf('Your IP address %s is not allowed to access this plugin.', $request_ip),
            array('status' => 403)
        );
    }

    private function get_current_request_ip() {
        $candidates = array(
            $_SERVER['HTTP_CF_CONNECTING_IP'] ?? '',
            $_SERVER['HTTP_X_REAL_IP'] ?? '',
            $_SERVER['REMOTE_ADDR'] ?? '',
        );

        if (! empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
            $candidates = array_merge(
                $candidates,
                array_map('trim', explode(',', (string) $_SERVER['HTTP_X_FORWARDED_FOR']))
            );
        }

        foreach ($candidates as $candidate) {
            $candidate = trim((string) $candidate);
            if ($candidate !== '' && filter_var($candidate, FILTER_VALIDATE_IP)) {
                return $candidate;
            }
        }

        return '';
    }

    private function is_valid_ip_whitelist_entry($entry) {
        $entry = trim((string) $entry);
        if ($entry === '') {
            return false;
        }

        if (strpos($entry, '/') === false) {
            return (bool) filter_var($entry, FILTER_VALIDATE_IP);
        }

        list($network, $prefix) = array_pad(explode('/', $entry, 2), 2, '');
        if (! filter_var($network, FILTER_VALIDATE_IP) || ! ctype_digit($prefix)) {
            return false;
        }

        $max_prefix = strpos($network, ':') !== false ? 128 : 32;

        return (int) $prefix >= 0 && (int) $prefix <= $max_prefix;
    }

    private function ip_matches_whitelist_entry($request_ip, $entry) {
        $request_ip = trim((string) $request_ip);
        $entry = trim((string) $entry);

        if (! filter_var($request_ip, FILTER_VALIDATE_IP) || ! $this->is_valid_ip_whitelist_entry($entry)) {
            return false;
        }

        if (strpos($entry, '/') === false) {
            $request_binary = inet_pton($request_ip);
            $entry_binary = inet_pton($entry);

            return $request_binary !== false && $entry_binary !== false && $request_binary === $entry_binary;
        }

        list($network, $prefix) = explode('/', $entry, 2);
        $request_binary = inet_pton($request_ip);
        $network_binary = inet_pton($network);
        if ($request_binary === false || $network_binary === false || strlen($request_binary) !== strlen($network_binary)) {
            return false;
        }

        return $this->binary_ip_matches_prefix($request_binary, $network_binary, (int) $prefix);
    }

    private function binary_ip_matches_prefix($request_binary, $network_binary, $prefix) {
        $full_bytes = intdiv($prefix, 8);
        $remaining_bits = $prefix % 8;

        if ($full_bytes > 0 && substr($request_binary, 0, $full_bytes) !== substr($network_binary, 0, $full_bytes)) {
            return false;
        }

        if ($remaining_bits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remaining_bits)) & 0xFF;

        return (ord($request_binary[$full_bytes]) & $mask) === (ord($network_binary[$full_bytes]) & $mask);
    }

    private function get_runtime_cache_ttl($settings) {
        if (empty($settings['advanced']['enableCache'])) {
            return 0;
        }

        return max(1, absint($settings['advanced']['cacheDuration'] ?? 24)) * HOUR_IN_SECONDS;
    }

    private function get_runtime_cache_version() {
        $version = absint(get_option(self::CACHE_VERSION_OPTION, 1));
        if ($version < 1) {
            $version = 1;
            update_option(self::CACHE_VERSION_OPTION, $version, false);
        }

        return $version;
    }

    private function get_runtime_cache_key($namespace, $identifier) {
        return self::CACHE_PREFIX . $this->get_runtime_cache_version() . '_' . md5($namespace . '|' . $identifier);
    }

    private function get_cached_runtime_value($settings, $cache_key) {
        if ($this->get_runtime_cache_ttl($settings) < 1) {
            return false;
        }

        return get_transient($cache_key);
    }

    private function set_cached_runtime_value($settings, $cache_key, $value) {
        $ttl = $this->get_runtime_cache_ttl($settings);
        if ($ttl < 1) {
            return;
        }

        set_transient($cache_key, $value, $ttl);
    }

    private function invalidate_runtime_cache() {
        update_option(self::CACHE_VERSION_OPTION, $this->get_runtime_cache_version() + 1, false);
    }

    private function convert_image_to_webp($source_path, $quality) {
        $destination = preg_replace('/\.[^.]+$/', '', $source_path) . '.webp';
        if (! is_string($destination) || $destination === '.webp') {
            return new WP_Error('wmcs_webp_path_failed', 'Could not determine a destination path for the WebP image.');
        }

        $editor = wp_get_image_editor($source_path);
        if (is_wp_error($editor)) {
            return $editor;
        }

        $quality_result = $editor->set_quality($quality);
        if (is_wp_error($quality_result)) {
            return $quality_result;
        }

        return $editor->save($destination, 'image/webp');
    }

    private function replace_path_tokens($template) {
        return strtr($template, array(
            '{Y}' => gmdate('Y'),
            '{m}' => gmdate('m'),
            '{d}' => gmdate('d'),
        ));
    }
}
