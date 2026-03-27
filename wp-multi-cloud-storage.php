<?php
/**
 * Plugin Name: WP Multi Cloud Storage
 * Plugin URI:  https://github.com/Lexo0522/Clouds-upload-wp
 * Description: WordPress 多云存储插件，支持七牛云、阿里云 OSS、腾讯云 COS、又拍云、多吉云和 AWS S3。
 * Version:     0.2.1
 * Author:      kate522
 * License:     GPL-2.0-or-later
 * Text Domain: wp-multi-cloud-storage
 */
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
define( 'WPMCS_VERSION', '0.2.1' );
define( 'WPMCS_PLUGIN_FILE', __FILE__ );
define( 'WPMCS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPMCS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
require_once WPMCS_PLUGIN_DIR . 'includes/class-wpmcs-autoloader.php';
function wpmcs_get_default_settings() {
	return array(
		'enabled'             => '0',
		'provider'            => 'qiniu',
		'access_key'          => '',
		'secret_key'          => '',
		'secret_id'           => '',
		'username'            => '',
		'password'            => '',
		'bucket'              => '',
		'domain'              => '',
		'region'              => 'ap-beijing',
		'endpoint'            => '',
		'upload_endpoint'     => 'https://upload.qiniup.com',
		'upload_path'         => '',
		'auto_rename'         => '1',
		'rename_pattern'      => '{Y}{m}{d}{H}{i}{s}_{random6}.{ext}',
		'replace_url'         => '1',
		'keep_local_file'     => '1',
		'convert_to_webp'     => '0',
		'webp_quality'        => '82',
		'async_upload'        => '0',
		'enable_cache'        => '1',
		'enable_logging'      => '1',      // 启用日志
		'error_notification'  => '0',      // 错误通知
		'log_retention_days'  => '30',     // 日志保留天数
	);
}
function wpmcs_get_settings() {
	$settings = get_option( 'wpmcs_settings', array() );
	$settings = wp_parse_args( (array) $settings, wpmcs_get_default_settings() );
	// 解密敏感信息
	$sensitive_keys = array( 'access_key', 'secret_key', 'secret_id', 'password' );
	foreach ( $sensitive_keys as $key ) {
		if ( ! empty( $settings[ $key ] ) && WPMCS_Encryption::is_encrypted( $settings[ $key ] ) ) {
			$decrypted = WPMCS_Encryption::decrypt( $settings[ $key ] );
			if ( false === $decrypted ) {
				error_log( sprintf(
					'WPMCS：解密 %s 失败，配置可能已损坏，或者加密密钥已经变更。',
					$key
				) );
				// 返回空值而不是乱码，防止系统崩溃
				$settings[ $key ] = '';
			} else {
				$settings[ $key ] = $decrypted;
			}
		}
	}
	return $settings;
}
/**
 * Get a shared logger instance for the current request.
 *
 * @return WPMCS_Logger
 */
function wpmcs_get_logger() {
	static $logger = null;
	if ( null === $logger ) {
		$logger = new WPMCS_Logger( wpmcs_get_settings() );
	}
	return $logger;
}
/**
 * 获取上传目录信息，同时避免暴露上游弃用警告。
 *
 * @return array<string, string>
 */
function wpmcs_get_upload_dir() {
	$upload_dir = function_exists( 'wp_upload_dir' ) ? @wp_upload_dir() : array();
	if ( ! is_array( $upload_dir ) ) {
		$upload_dir = array();
	}
	$upload_dir['basedir'] = isset( $upload_dir['basedir'] ) ? (string) $upload_dir['basedir'] : '';
	$upload_dir['baseurl'] = isset( $upload_dir['baseurl'] ) ? (string) $upload_dir['baseurl'] : '';
	return $upload_dir;
}
function wpmcs_normalize_option_string( $value ) {
	if ( null === $value ) {
		return '';
	}
	return is_string( $value ) ? $value : (string) $value;
}
add_filter( 'option_upload_path', 'wpmcs_normalize_option_string' );
add_filter( 'option_upload_url_path', 'wpmcs_normalize_option_string' );
/**
 * 保存设置（加密敏感信息）
 *
 * @param array $settings 设置数组
 * @return bool
 */
function wpmcs_save_settings( $settings ) {
	$current_settings = wpmcs_get_settings();
	$settings = wp_parse_args( (array) $settings, $current_settings );
	$sensitive_keys = array( 'access_key', 'secret_key', 'secret_id', 'password' );
	// 加密敏感信息
	foreach ( $sensitive_keys as $key ) {
		if ( ! empty( $settings[ $key ] ) && ! WPMCS_Encryption::is_encrypted( $settings[ $key ] ) ) {
			$encrypted = WPMCS_Encryption::encrypt( $settings[ $key ] );
			if ( false !== $encrypted ) {
				$settings[ $key ] = $encrypted;
			}
		}
	}
	return update_option( 'wpmcs_settings', $settings );
}
/**
 * 根据原始文件名生成唯一文件名。
 *
 * @param string $original_filename 原始上传文件名。
 * @return string
 */
function wpmcs_generate_unique_filename( $original_filename ) {
	return wpmcs_generate_unique_filename_with_pattern( $original_filename, '' );
}
/**
 * 根据规则生成唯一文件名。
 *
 * 支持的占位符：
 * - {Y} {m} {d} {H} {i} {s}
 * - {random6}
 * - {ext}
 * - {filename}
 *
 * @param string $original_filename 原始上传文件名。
 * @param string $pattern 文件名规则。
 * @return string
 */
function wpmcs_generate_unique_filename_with_pattern( $original_filename, $pattern = '' ) {
	$original_filename = (string) $original_filename;
	$pattern           = (string) $pattern;
	$info              = pathinfo( $original_filename );
	$extension         = isset( $info['extension'] ) ? strtolower( (string) $info['extension'] ) : '';
	$basename          = isset( $info['filename'] ) ? sanitize_file_name( (string) $info['filename'] ) : '';
	$random_string     = wp_generate_password( 6, false, false );
	if ( '' === trim( (string) $pattern ) ) {
		$pattern = '{Y}{m}{d}{H}{i}{s}_{random6}.{ext}';
	}
	$replacements = array(
		'{Y}'        => gmdate( 'Y' ),
		'{m}'        => gmdate( 'm' ),
		'{d}'        => gmdate( 'd' ),
		'{H}'        => gmdate( 'H' ),
		'{i}'        => gmdate( 'i' ),
		'{s}'        => gmdate( 's' ),
		'{random6}'  => $random_string,
		'{ext}'      => $extension,
		'{filename}' => $basename,
	);
	$filename = strtr( $pattern, $replacements );
	$filename = str_replace( array( '\\\\', '/' ), '-', $filename );
	$filename = preg_replace( '/\\s+/', '-', $filename );
	$filename = trim( (string) $filename, ".-_ \t\n\r\0\x0B" );
	if ( '' === $filename ) {
		$filename = gmdate( 'YmdHis' ) . '_' . $random_string;
		if ( '' !== $extension ) {
			$filename .= '.' . $extension;
		}
	}
	// 修复扩展名处理，确保只保留一个扩展名。
	// 1. 先移除原始文件名中可能存在的扩展名。
	$without_ext = preg_replace( '/\.[^.]*$/', '', $filename );
	// 2. 只有在扩展名存在时才重新拼接。
	if ( '' !== $extension ) {
		$filename = $without_ext . '.' . $extension;
	}
	return $filename;
}
/**
 * 根据设置构建云存储驱动。
 *
 * @param array<string, mixed> $settings 设置数组。
 * @return Cloud_Storage_Interface|WP_Error
 */
function wpmcs_create_storage_driver( array $settings ) {
	$provider = isset( $settings['provider'] ) ? sanitize_key( (string) $settings['provider'] ) : 'qiniu';
	switch ( $provider ) {
		case 'qiniu':
			return new Qiniu_Storage( $settings );
		case 'aliyun_oss':
			return new Aliyun_OSS_Storage( $settings );
		case 'tencent_cos':
			return new Tencent_COS_Storage( $settings );
		case 'upyun':
			return new Upyun_Storage( $settings );
		case 'dogecloud':
			return new Dogecloud_Storage( $settings );
		case 'aws_s3':
			return new AWS_S3_Storage( $settings );
		default:
			return new WP_Error(
				'wpmcs_unsupported_provider',
				sprintf( '不支持的服务商：%s', $provider ? $provider : 'unknown' )
			);
	}
}
final class WPMCS_Plugin {
	/**
	 * @var WPMCS_Plugin|null
	 */
	private static $instance = null;
	/**
	 * @var WPMCS_Cloud_Adapter|null
	 */
	private $adapter = null;
	/**
	 * @var WPMCS_Upload_Manager|null
	 */
	private $upload_manager = null;
	/**
	 * @var WPMCS_Upload_Interceptor|null
	 */
	private $upload_interceptor = null;
	/**
	 * @var WPMCS_Admin_Page|null
	 */
	private $admin_page = null;
	/**
	 * @var Cloud_Uploader|null
	 */
	private $cloud_uploader = null;
	
	/**
	 * @var WPMCS_Media_Library_Enhancer|null
	 */
	private $media_enhancer = null;
	
	/**
	 * @var WPMCS_Migration_Manager|null
	 */
	private $migration_manager = null;
	
	/**
	 * @var WPMCS_Async_Queue|null
	 */
	private $async_queue = null;
	
	/**
	 * @var WPMCS_Cache_Manager|null
	 */
	private $cache_manager = null;
	/**
	 * @var array
	 */
	private $settings = array();
	
	/**
	 * @var WPMCS_Logger|null
	 */
	private $logger = null;
	
	/**
	 * @var WPMCS_Storage_Stats|null
	 */
	private $storage_stats = null;
	
	/**
	 * @var WPMCS_Security_Manager|null
	 */
	private $security_manager = null;
	
	/**
	 * @var WPMCS_Debug_Manager|null
	 */
	private $debug_manager = null;
	
	/**
	 * @var WPMCS_REST_API|null
	 */
	private $rest_api = null;
	
	/**
	 * @var WPMCS_Webhook_Manager|null
	 */
	private $webhook_manager = null;
	
	/**
	 * @var WPMCS_Batch_Operations|null
	 */
	private $batch_operations = null;
	
	/**
	 * @var WPMCS_Quick_Setup|null
	 */
	private $quick_setup = null;
	
	/**
	 * @var WPMCS_Setup_Wizard|null
	 */
	private $setup_wizard = null;

	private function init_storage_stats() {
		if ( $this->storage_stats instanceof WPMCS_Storage_Stats ) {
			return;
		}

		$this->storage_stats = new WPMCS_Storage_Stats( $this->settings );
		$this->storage_stats->register_hooks();
	}

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}
	public static function activate() {
	$existing_settings = get_option( 'wpmcs_settings', false );
	$existing_version  = get_option( 'wpmcs_version', false );
	$existing_wizard   = get_option( 'wpmcs_wizard_completed', false );
	$first_install     = ( false === $existing_settings && false === $existing_version && false === $existing_wizard );
	$current           = $existing_settings ? $existing_settings : array();
	$merged            = wp_parse_args( (array) $current, wpmcs_get_default_settings() );
	if ( $first_install ) {
		set_transient( 'wpmcs_activation_redirect', 1, 30 );
	}
	update_option( 'wpmcs_settings', $merged );
	$result = self::create_logs_table();
	if ( is_wp_error( $result ) ) {
		wp_die(
			sprintf(
				'初始化日志表失败：%s',
				esc_html( $result->get_error_message() )
			),
			'云存储设置',
			array(
				'response'  => 200,
				'back_link' => true,
			)
		);
	}
	update_option( 'wpmcs_version', WPMCS_VERSION );
}
	public function maybe_redirect_to_setup_wizard() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		if ( wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}
		if ( isset( $_GET['activate-multi'] ) ) {
			return;
		}
		if ( ! get_transient( 'wpmcs_activation_redirect' ) ) {
			return;
		}
		delete_transient( 'wpmcs_activation_redirect' );
		wp_safe_redirect( admin_url( 'admin.php?page=wpmcs-setup-wizard' ) );
		exit;
	}
private static function create_logs_table() {
	global $wpdb;
	$table_name = $wpdb->prefix . 'wpmcs_logs';
	$table_exists = $wpdb->get_var(
		$wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$wpdb->esc_like( $table_name )
		)
	);
	if ( $table_exists ) {
		$existing_columns = $wpdb->get_col( "SHOW COLUMNS FROM `{$table_name}`" );
		if ( in_array( 'id', $existing_columns, true ) && in_array( 'level', $existing_columns, true ) && in_array( 'type', $existing_columns, true ) ) {
			return true;
		}
	}
	$charset_collate = $wpdb->get_charset_collate();
	$sql = "CREATE TABLE {$table_name} (
		id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
		level varchar(20) NOT NULL DEFAULT 'info',
		type varchar(50) NOT NULL DEFAULT 'system',
		message text NOT NULL,
		context longtext,
		attachment_id bigint(20) unsigned DEFAULT NULL,
		user_id bigint(20) unsigned DEFAULT NULL,
		ip_address varchar(45) DEFAULT NULL,
		user_agent varchar(255) DEFAULT NULL,
		request_uri text,
		created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		PRIMARY KEY  (id),
		KEY level (level),
		KEY type (type),
		KEY attachment_id (attachment_id),
		KEY created_at (created_at)
	) {$charset_collate};";
	require_once ABSPATH . 'wp-admin/includes/upgrade.php';
	dbDelta( $sql );
	unset( $sql, $charset_collate, $table_exists, $existing_columns );
	$table_exists = $wpdb->get_var(
		$wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$wpdb->esc_like( $table_name )
		)
	);
	if ( ! $table_exists ) {
		error_log( sprintf( 'WPMCS：创建数据表失败 %s', $table_name ) );
		return new WP_Error(
			'table_creation_failed',
			sprintf( '执行 dbDelta() 后仍无法创建日志表 %s', $table_name )
		);
	}
	return true;
}
private function __construct() {
		$this->maybe_increase_memory_limit();
		$this->settings = wpmcs_get_settings();
		$this->cache_manager = new WPMCS_Cache_Manager();
		$this->security_manager = new WPMCS_Security_Manager( $this->settings );
		if ( get_option( 'wpmcs_debug_mode', false ) ) {
			$this->debug_manager = new WPMCS_Debug_Manager( $this->settings );
		}
		if ( ! empty( get_option( 'wpmcs_webhooks', array() ) ) ) {
			$this->webhook_manager = new WPMCS_Webhook_Manager( $this->settings );
		}
		$this->adapter = $this->create_adapter( $this->settings );
		if ( ! empty( $this->settings['enabled'] ) && $this->adapter ) {
			$storage_driver = wpmcs_create_storage_driver( $this->settings );
			if ( ! is_wp_error( $storage_driver ) ) {
				$this->cloud_uploader = new Cloud_Uploader( $storage_driver, $this->settings );
				$this->async_queue = new WPMCS_Async_Queue( $this->settings );
				$this->upload_interceptor = new WPMCS_Upload_Interceptor( $this->cloud_uploader, $this->settings, $this->cache_manager, $this->async_queue );
				$this->upload_interceptor->register_hooks();
				$this->upload_manager = new WPMCS_Upload_Manager( $this->adapter, $this->settings );
				$this->upload_manager->register_hooks();
				if ( ! empty( $this->settings['async_upload'] ) ) {
					$this->async_queue->register_hooks();
				}
			}
		}
		if ( is_admin() ) {
			global $pagenow;
			$pagenow = isset( $pagenow ) ? $pagenow : '';
			$page = isset( $_GET['page'] ) ? sanitize_key( $_GET['page'] ) : '';
			$action = wp_doing_ajax() && isset( $_REQUEST['action'] ) ? sanitize_key( $_REQUEST['action'] ) : '';
			$is_media_screen = in_array( $pagenow, array( 'upload.php', 'post.php' ), true );
			$is_media_action = in_array( $action, array( 'wpmcs_reupload_attachment', 'wpmcs_copy_cloud_url' ), true );
			$is_batch_action = in_array( $action, array( 'wpmcs_batch_upload', 'wpmcs_batch_delete', 'wpmcs_batch_migrate', 'wpmcs_batch_sync', 'wpmcs_batch_status', 'wpmcs_batch_cancel' ), true );
			$this->admin_page = new WPMCS_Admin_Page();
			$this->admin_page->register_hooks();
			if ( $is_media_screen || $is_media_action ) {
				$this->media_enhancer = new WPMCS_Media_Library_Enhancer( $this->settings );
				$this->media_enhancer->register_hooks();
			}
			$this->migration_manager = new WPMCS_Migration_Manager( $this->settings );
			$this->migration_manager->register_hooks();
			$logs_page = new WPMCS_Logs_Page();
			$logs_page->register_hooks();
			$security_page = new WPMCS_Security_Page();
			$security_page->init_hooks();
			$this->init_storage_stats();
			if ( 'upload.php' === $pagenow || $is_batch_action ) {
				$this->batch_operations = new WPMCS_Batch_Operations( $this->settings );
			}
			$this->quick_setup = new WPMCS_Quick_Setup();
			$this->setup_wizard = new WPMCS_Setup_Wizard();
			add_action( 'admin_init', array( $this, 'maybe_redirect_to_setup_wizard' ) );
			add_action( 'wp_ajax_wpmcs_test_connection', array( $this, 'ajax_test_connection' ) );
			add_action( 'wp_ajax_wpmcs_get_provider_fields', array( $this, 'ajax_get_provider_fields' ) );
		}
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		if ( ! is_admin() && ! empty( $this->settings['replace_url'] ) ) {
			$this->init_storage_stats();
		}
		add_filter( 'cron_schedules', array( $this, 'add_cron_schedules' ) );
		add_action( 'wpmcs_cleanup_logs', array( $this, 'cleanup_logs' ) );
		if ( ! wp_next_scheduled( 'wpmcs_cleanup_logs' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmcs_cleanup_logs' );
		}
		add_action( 'wpmcs_cleanup_temp_files', array( $this, 'cleanup_temp_files' ) );
		if ( ! wp_next_scheduled( 'wpmcs_cleanup_temp_files' ) ) {
			wp_schedule_event( time(), 'daily', 'wpmcs_cleanup_temp_files' );
		}
	}
	private function maybe_increase_memory_limit() {
	$current_limit = $this->get_memory_limit();
	if ( $current_limit < 256 * 1024 * 1024 && function_exists( 'ini_set' ) ) {
		@ini_set( 'memory_limit', '256M' );
		$new_limit = $this->get_memory_limit();
		if ( $new_limit > $current_limit && defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'WPMCS：内存限制从 %s 提升到 %s', $this->format_memory_size( $current_limit ), $this->format_memory_size( $new_limit ) ) );
		}
	}
}
private function get_memory_limit() {
	$memory_limit = ini_get( 'memory_limit' );
	if ( '-1' === $memory_limit ) {
		return PHP_INT_MAX;
	}
	return $this->convert_to_bytes( $memory_limit );
}
private function convert_to_bytes( $value ) {
	$value = trim( (string) $value );
	$unit  = strtolower( substr( $value, -1 ) );
	$value = (int) $value;
	switch ( $unit ) {
		case 'g':
			$value *= 1024;
			// 继续向下判断
		case 'm':
			$value *= 1024;
			// 继续向下判断
		case 'k':
			$value *= 1024;
	}
	return $value;
}
private function format_memory_size( $bytes ) {
	$units = array( 'B', 'KB', 'MB', 'GB' );
	$bytes = max( (int) $bytes, 0 );
	$pow   = $bytes > 0 ? floor( log( $bytes, 1024 ) ) : 0;
	$pow   = min( (int) $pow, count( $units ) - 1 );
	$bytes = $bytes / pow( 1024, $pow );
	return round( $bytes, 2 ) . ' ' . $units[ $pow ];
}
private function create_adapter( array $settings ) {
		if ( empty( $settings['provider'] ) ) {
			return null;
		}
		switch ( $settings['provider'] ) {
			case 'qiniu':
				return new WPMCS_Qiniu_Adapter( $settings );
			
			case 'aliyun_oss':
				return new WPMCS_Aliyun_OSS_Adapter( $settings );
			
			case 'tencent_cos':
				return new WPMCS_Tencent_COS_Adapter( $settings );
			
			case 'upyun':
				return new WPMCS_Upyun_Adapter( $settings );
			
			case 'dogecloud':
				return new WPMCS_Dogecloud_Adapter( $settings );
			
			case 'aws_s3':
				return new WPMCS_AWS_S3_Adapter( $settings );
			
			default:
				return null;
		}
	}
	
	/**
	 * 获取云上传器实例
	 *
	 * @return Cloud_Uploader|null
	 */
	public function get_cloud_uploader() {
		return $this->cloud_uploader;
	}
	
	/**
	 * 获取上传拦截器实例
	 *
	 * @return WPMCS_Upload_Interceptor|null
	 */
	public function get_upload_interceptor() {
		return $this->upload_interceptor;
	}
	
	/**
	 * 获取缓存管理器实例
	 *
	 * @return WPMCS_Cache_Manager|null
	 */
	public function get_cache_manager() {
		// 延迟加载：只在首次访问时实例化
		if ( null === $this->cache_manager ) {
			$this->cache_manager = new WPMCS_Cache_Manager();
		}
		return $this->cache_manager;
	}
	
	/**
	 * 获取异步队列实例
	 *
	 * @return WPMCS_Async_Queue|null
	 */
	public function get_async_queue() {
		return $this->async_queue;
	}
	
	/**
	 * 获取日志管理器实例
	 *
	 * @return WPMCS_Logger|null
	 */
	public function get_logger() {
		// 延迟加载：只在首次访问时实例化
		if ( null === $this->logger ) {
			$this->logger = new WPMCS_Logger( $this->settings );
		}
		return $this->logger;
	}
	
	/**
	 * 获取存储统计管理器实例
	 *
	 * @return WPMCS_Storage_Stats|null
	 */
	public function get_storage_stats() {
		return $this->storage_stats;
	}
	
	/**
	 * 获取安全管理器实例
	 *
	 * @return WPMCS_Security_Manager|null
	 */
	public function get_security_manager() {
		// 延迟加载：只在首次访问时实例化
		if ( null === $this->security_manager ) {
			$settings = wpmcs_get_settings();
			$this->security_manager = new WPMCS_Security_Manager( $settings );
		}
		return $this->security_manager;
	}
	
	/**
	 * 获取调试管理器实例
	 *
	 * @return WPMCS_Debug_Manager|null
	 */
	public function get_debug_manager() {
		return $this->debug_manager;
	}
	
	/**
	 * 获取 REST API 实例
	 *
	 * @return WPMCS_REST_API|null
	 */
	public function get_rest_api() {
		return $this->rest_api;
	}
	
	/**
	 * 获取 Webhook 管理器实例
	 *
	 * @return WPMCS_Webhook_Manager|null
	 */
	public function get_webhook_manager() {
		return $this->webhook_manager;
	}
	
	/**
	 * 获取批量操作管理器实例
	 *
	 * @return WPMCS_Batch_Operations|null
	 */
	public function get_batch_operations() {
		return $this->batch_operations;
	}
	
	/**
	 * 获取快速设置实例
	 *
	 * @return WPMCS_Quick_Setup|null
	 */
	public function get_quick_setup() {
		return $this->quick_setup;
	}
	
	/**
	 * 获取配置向导实例
	 *
	 * @return WPMCS_Setup_Wizard|null
	 */
	public function get_setup_wizard() {
		return $this->setup_wizard;
	}
	
	/**
	 * 定时清理日志
	 */
	/**
	 * 延迟注册 REST API 路由。
	 */
	public function register_rest_routes() {
		$rest_api = new WPMCS_REST_API( wpmcs_get_settings() );
		$rest_api->register_routes();
	}
	public function cleanup_logs() {
		$this->get_logger()->cleanup_old_logs();
	}
	/**
	 * 定时清理临时文件
	 */
	public function cleanup_temp_files() {
		$count = WPMCS_Temp_File_Manager::cleanup_temp_files();
		// 仅在调试模式下记录日志
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log( sprintf( 'WPMCS：清理了 %d 个临时文件', $count ) );
		}
	}
	
	/**
	 * 添加自定义 cron 时间间隔
	 *
	 * @param array $schedules 没有时间间隔，添加自定义 cron 时间间隔。
	 * @return array
	 */
	public function add_cron_schedules( $schedules ) {
	$schedules['every_minute'] = array(
		'interval' => 60,
		'display'  => '每分钟一次',
	);
	$schedules['every_five_minutes'] = array(
		'interval' => 300,
		'display'  => '每五分钟一次',
	);
	return $schedules;
}
/**
 * AJAX 测试连接。
 */
public function ajax_test_connection() {
	if ( ! current_user_can( 'manage_options' ) ) {
		wp_send_json_error( array( 'message' => '没有权限' ) );
	}
	if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpmcs_test_connection' ) ) {
		wp_send_json_error( array( 'message' => '安全验证失败' ) );
	}
	$settings = $this->settings;
	if ( isset( $_POST['wpmcs_settings'] ) && is_array( $_POST['wpmcs_settings'] ) ) {
		$admin_page = new WPMCS_Admin_Page();
		$settings = $admin_page->sanitize_settings( wp_unslash( $_POST['wpmcs_settings'] ) );
	}
	$tester  = new WPMCS_Connection_Tester( $settings );
	$results = $tester->run_full_test();
	if ( $results['success'] ) {
		wp_send_json_success( $results );
	}
	wp_send_json_error( $results );
}
public function ajax_get_provider_fields() {
		// 验证权限
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		// 验证 nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpmcs_provider_fields' ) ) {
			wp_send_json_error( array( 'message' => '安全验证失败' ) );
		}
		
		// 获取服务商
		$provider = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : 'qiniu';
		
		// 获取当前设置
		$settings = wpmcs_get_settings();
		
		// 获取配置字段 HTML
		ob_start();
		// 实例化管理页面类来渲染字段
		if ( ! $this->admin_page ) {
			$this->admin_page = new WPMCS_Admin_Page();
		}
		$this->admin_page->render_provider_fields_html( $provider, $settings );
		
		$html = ob_get_clean();
		
		wp_send_json_success( array( 'html' => $html ) );
	}
}
register_activation_hook( WPMCS_PLUGIN_FILE, array( 'WPMCS_Plugin', 'activate' ) );
add_action( 'plugins_loaded', array( 'WPMCS_Plugin', 'instance' ) );
