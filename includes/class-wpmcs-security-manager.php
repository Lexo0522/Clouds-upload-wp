<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 安全管理器
 * 
 * 提供文件类型验证、大小限制、权限控制和敏感信息加密等功能
 */
class WPMCS_Security_Manager {
	
	/**
	 * 默认允许的文件类型（白名单）
	 *
	 * @var array
	 */
	private $allowed_types = array(
		// 图片
		'image/jpeg',
		'image/jpg',
		'image/png',
		'image/gif',
		'image/webp',
		'image/svg+xml',
		'image/bmp',
		'image/tiff',
		'image/ico',
		
		// 视频
		'video/mp4',
		'video/mpeg',
		'video/quicktime',
		'video/x-msvideo',
		'video/x-ms-wmv',
		'video/webm',
		
		// 音频
		'audio/mpeg',
		'audio/mp3',
		'audio/wav',
		'audio/ogg',
		'audio/aac',
		'audio/flac',
		
		// 文档
		'application/pdf',
		'application/msword',
		'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
		'application/vnd.ms-excel',
		'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
		'application/vnd.ms-powerpoint',
		'application/vnd.openxmlformats-officedocument.presentationml.presentation',
		'text/plain',
		'text/csv',
		
		// 压缩文件
		'application/zip',
		'application/x-rar-compressed',
		'application/x-7z-compressed',
		'application/gzip',
	);
	
	/**
	 * 禁止的文件类型（黑名单）
	 *
	 * @var array
	 */
	private $blocked_types = array(
		// 可执行文件
		'application/x-msdownload',
		'application/x-msdos-program',
		'application/x-executable',
		'application/x-dosexec',
		'application/x-sh',
		'application/x-csh',
		'application/x-perl',
		'application/x-python',
		'application/x-php',
		'text/x-php',
		'application/x-httpd-php',
		'application/x-httpd-php-source',
		
		// 脚本文件
		'text/javascript',
		'application/javascript',
		'application/x-javascript',
		'text/vbscript',
		'application/x-vbscript',
		
		// 系统文件
		'application/x-ms-shortcut',
		'application/x-apple-diskimage',
	);
	
	/**
	 * 危险文件扩展名
	 *
	 * @var array
	 */
	private $dangerous_extensions = array(
		'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
		'exe', 'bat', 'cmd', 'com', 'msi',
		'sh', 'bash', 'zsh', 'csh', 'ksh',
		'py', 'pyc', 'pyo',
		'pl', 'pm',
		'rb', 'rbw',
		'asp', 'aspx', 'jsp', 'jspx',
		'sql', 'db', 'sqlite',
		'htaccess', 'htpasswd',
		'svg', // SVG 可能包含恶意脚本，需要特殊处理
	);
	
	/**
	 * 默认文件大小限制（字节）
	 *
	 * @var array
	 */
	private $size_limits = array(
		'image'    => 10485760,    // 10 MB
		'video'    => 524288000,   // 500 MB
		'audio'    => 52428800,    // 50 MB
		'application' => 20971520, // 20 MB
		'text'     => 5242880,     // 5 MB
		'default'  => 10485760,    // 10 MB
	);
	
	/**
	 * 加密密钥选项名
	 */
	const ENCRYPTION_KEY_OPTION = 'wpmcs_encryption_key';
	
	/**
	 * 加密算法
	 */
	const ENCRYPTION_ALGO = 'aes-256-cbc';
	
	/**
	 * @var array
	 */
	private $settings;
	
	/**
	 * 构造函数
	 *
	 * @param array $settings 插件设置
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
		$this->init_hooks();
	}
	
	/**
	 * 初始化钩子
	 */
	public function init_hooks() {
		// 文件上传验证
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'validate_upload' ), 5 );
		
		// 添加设置字段
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		
		// AJAX 处理
		add_action( 'wp_ajax_wpmcs_validate_file', array( $this, 'ajax_validate_file' ) );
	}
	
	/**
	 * 注册设置
	 */
	public function register_settings() {
		// 文件类型白名单
		register_setting( 'wpmcs_settings_group', 'wpmcs_allowed_file_types', array(
			'type' => 'string',
			'description' => '允许上传的文件类型白名单',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => '',
		) );
		
		// 文件类型黑名单
		register_setting( 'wpmcs_settings_group', 'wpmcs_blocked_file_types', array(
			'type' => 'string',
			'description' => '禁止上传的文件类型黑名单',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => '',
		) );
		
		// 文件大小限制
		register_setting( 'wpmcs_settings_group', 'wpmcs_file_size_limits', array(
			'type' => 'array',
			'description' => '各类文件大小限制',
			'sanitize_callback' => array( $this, 'sanitize_size_limits' ),
			'default' => $this->size_limits,
		) );
		
		// 启用严格模式
		register_setting( 'wpmcs_settings_group', 'wpmcs_strict_mode', array(
			'type' => 'string',
			'description' => '启用严格安全模式',
			'sanitize_callback' => 'sanitize_text_field',
			'default' => '0',
		) );
	}
	
	/**
	 * 验证上传文件
	 *
	 * @param array $file 上传文件信息
	 * @return array|WP_Error
	 */
	public function validate_upload( $file ) {
		// 检查是否启用插件
		if ( empty( $this->settings['enabled'] ) ) {
			return $file;
		}
		
		// 获取文件信息
		$file_name = isset( $file['name'] ) ? $file['name'] : '';
		$file_type = isset( $file['type'] ) ? $file['type'] : '';
		$file_size = isset( $file['size'] ) ? $file['size'] : 0;
		$tmp_name = isset( $file['tmp_name'] ) ? $file['tmp_name'] : '';
		
		// 验证文件类型
		$type_validation = $this->validate_file_type( $file_name, $file_type, $tmp_name );
		if ( is_wp_error( $type_validation ) ) {
			return $type_validation;
		}
		
		// 验证文件大小
		$size_validation = $this->validate_file_size( $file_size, $file_type );
		if ( is_wp_error( $size_validation ) ) {
			return $size_validation;
		}
		
		// 验证文件内容（严格模式）
		$strict_mode = get_option( 'wpmcs_strict_mode', '0' );
		if ( $strict_mode === '1' && ! empty( $tmp_name ) ) {
			$content_validation = $this->validate_file_content( $tmp_name, $file_type );
			if ( is_wp_error( $content_validation ) ) {
				return $content_validation;
			}
		}
		
		// 记录安全日志
		$this->log_upload( $file );
		
		return $file;
	}
	
	/**
	 * 验证文件类型
	 *
	 * @param string $file_name 文件名
	 * @param string $file_type 文件 MIME 类型
	 * @param string $file_path 文件临时路径
	 * @return true|WP_Error
	 */
	public function validate_file_type( $file_name, $file_type, $file_path = '' ) {
		// 获取文件扩展名
		$extension = strtolower( pathinfo( $file_name, PATHINFO_EXTENSION ) );
		
		// 检查危险扩展名
		if ( in_array( $extension, $this->dangerous_extensions, true ) ) {
			// SVG 特殊处理
			if ( $extension === 'svg' && $this->is_safe_svg( $file_path ) ) {
				// 允许安全的 SVG
			} else {
				return new WP_Error(
					'dangerous_file_type',
					sprintf( '不允许上传此类型的文件：.%s', $extension )
				);
			}
		}
		
		// 真实 MIME 类型检测
		if ( ! empty( $file_path ) && function_exists( 'mime_content_type' ) ) {
			$real_type = mime_content_type( $file_path );
			if ( $real_type && $real_type !== $file_type ) {
				$file_type = $real_type;
			}
		}
		
		// 获取自定义白名单
		$custom_whitelist = get_option( 'wpmcs_allowed_file_types', '' );
		if ( ! empty( $custom_whitelist ) ) {
			$allowed = array_map( 'trim', explode( ',', $custom_whitelist ) );
			if ( ! in_array( $file_type, $allowed, true ) && ! in_array( $extension, $allowed, true ) ) {
				return new WP_Error(
					'file_type_not_allowed',
					sprintf( '此文件类型不在允许的白名单中：%s', $file_type )
				);
			}
		} else {
			// 使用默认白名单
			if ( ! in_array( $file_type, $this->allowed_types, true ) ) {
				return new WP_Error(
					'file_type_not_allowed',
					sprintf( '不允许上传此类型的文件：%s', $file_type )
				);
			}
		}
		
		// 检查黑名单
		$custom_blacklist = get_option( 'wpmcs_blocked_file_types', '' );
		$blocked = $custom_blacklist 
			? array_map( 'trim', explode( ',', $custom_blacklist ) )
			: $this->blocked_types;
		
		if ( in_array( $file_type, $blocked, true ) || in_array( $extension, $blocked, true ) ) {
			return new WP_Error(
				'file_type_blocked',
				sprintf( '此文件类型在禁止列表中：%s', $file_type )
			);
		}
		
		return true;
	}
	
	/**
	 * 验证文件大小
	 *
	 * @param int    $file_size 文件大小（字节）
	 * @param string $file_type 文件 MIME 类型
	 * @return true|WP_Error
	 */
	public function validate_file_size( $file_size, $file_type ) {
		// 获取文件类型分类
		$category = $this->get_file_category( $file_type );
		
		// 获取自定义大小限制
		$custom_limits = get_option( 'wpmcs_file_size_limits', $this->size_limits );
		
		// 获取该类型的限制
		$limit = isset( $custom_limits[ $category ] ) 
			? $custom_limits[ $category ] 
			: $custom_limits['default'];
		
		// 检查服务器限制
		$server_limit = $this->get_server_upload_limit();
		$limit = min( $limit, $server_limit );
		
		if ( $file_size > $limit ) {
			$limit_mb = round( $limit / 1048576, 2 );
			$file_mb = round( $file_size / 1048576, 2 );
			
			return new WP_Error(
				'file_size_exceeded',
				sprintf( 
					'文件大小超出限制。最大允许 %s MB，当前文件 %s MB',
					$limit_mb,
					$file_mb
				)
			);
		}
		
		return true;
	}
	
	/**
	 * 验证文件内容（严格模式）
	 *
	 * @param string $file_path 文件路径
	 * @param string $file_type 文件 MIME 类型
	 * @return true|WP_Error
	 */
	public function validate_file_content( $file_path, $file_type ) {
		$file_path = (string) $file_path;
		// 检查文件是否存在
		$file_path = (string) $file_path;
		$file_type = (string) $file_type;
		$header = '';
		if ( ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', '文件不存在' );
		}
		
		// 读取文件开头部分
		$handle = fopen( $file_path, 'rb' );
		if ( ! $handle ) {
			return new WP_Error( 'file_read_error', '无法读取文件' );
		}
		
		$header = fread( $handle, 1024 );
		fclose( $handle );
		
		// 检查是否包含恶意代码
		$dangerous_patterns = array(
			'/<\?php/i',
			'/<script[^>]*>.*?<\/script>/is',
			'/eval\s*\(/i',
			'/base64_decode\s*\(/i',
			'/gzinflate\s*\(/i',
			'/str_rot13\s*\(/i',
			'/system\s*\(/i',
			'/exec\s*\(/i',
			'/shell_exec\s*\(/i',
			'/passthru\s*\(/i',
		);
		
		foreach ( $dangerous_patterns as $pattern ) {
			if ( preg_match( $pattern, $header ) ) {
				return new WP_Error(
					'malicious_content',
					'检测到潜在的恶意内容，上传被拒绝'
				);
			}
		}
		
		// 图片文件额外验证
		if ( str_starts_with( $file_type, 'image/' ) ) {
			$image_info = @getimagesize( $file_path );
			if ( $image_info === false && $file_type !== 'image/svg+xml' ) {
				return new WP_Error(
					'invalid_image',
					'图片文件验证失败，可能不是有效的图片'
				);
			}
		}
		
		return true;
	}
	
	/**
	 * 检查 SVG 文件是否安全
	 *
	 * @param string $file_path 文件路径
	 * @return bool
	 */
	private function is_safe_svg( $file_path ) {
		if ( ! file_exists( $file_path ) ) {
			return false;
		}
		
		$content = file_get_contents( $file_path );
		
		// 检查危险内容
		$dangerous = array(
			'/<script[^>]*>.*?<\/script>/is',
			'/onload\s*=/i',
			'/onerror\s*=/i',
			'/onclick\s*=/i',
			'/javascript:/i',
			'/<iframe/i',
			'/<embed/i',
			'/<object/i',
		);
		
		foreach ( $dangerous as $pattern ) {
			if ( preg_match( $pattern, $content ) ) {
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * 获取文件分类
	 *
	 * @param string $file_type MIME 类型
	 * @return string
	 */
	private function get_file_category( $file_type ) {
		$file_type = (string) $file_type;
		if ( str_starts_with( $file_type, 'image/' ) ) {
			return 'image';
		} elseif ( str_starts_with( $file_type, 'video/' ) ) {
			return 'video';
		} elseif ( str_starts_with( $file_type, 'audio/' ) ) {
			return 'audio';
		} elseif ( str_starts_with( $file_type, 'text/' ) ) {
			return 'text';
		} else {
			return 'application';
		}
	}
	
	/**
	 * 获取服务器上传限制
	 *
	 * @return int
	 */
	private function get_server_upload_limit() {
		$limits = array();
		
		// PHP upload_max_filesize
		$upload_max = ini_get( 'upload_max_filesize' );
		if ( $upload_max ) {
			$limits[] = $this->parse_size( $upload_max );
		}
		
		// PHP post_max_size
		$post_max = ini_get( 'post_max_size' );
		if ( $post_max ) {
			$limits[] = $this->parse_size( $post_max );
		}
		
		// WordPress 上传限制
		if ( defined( 'WP_MEMORY_LIMIT' ) ) {
			$limits[] = $this->parse_size( WP_MEMORY_LIMIT );
		}
		
		return ! empty( $limits ) ? min( $limits ) : 10485760;
	}
	
	/**
	 * 解析大小字符串
	 *
	 * @param string $size 大小字符串（如 "10M"）
	 * @return int
	 */
	private function parse_size( $size ) {
		$size = trim( (string) $size );
		if ( '' === $size ) {
			return 0;
		}
		$last = strtolower( $size[ strlen( $size ) - 1 ] );
		$value = (int) $size;
		
		switch ( $last ) {
			case 'g':
				$value *= 1024;
			case 'm':
				$value *= 1024;
			case 'k':
				$value *= 1024;
		}
		
		return $value;
	}
	
	/**
	 * 清理大小限制设置
	 *
	 * @param array $input 输入数据
	 * @return array
	 */
	public function sanitize_size_limits( $input ) {
		$output = array();
		
		foreach ( $this->size_limits as $key => $default ) {
			if ( isset( $input[ $key ] ) ) {
				$value = $this->parse_size( $input[ $key ] );
				$output[ $key ] = max( 0, min( $value, 5368709120 ) ); // 最大 5GB
			} else {
				$output[ $key ] = $default;
			}
		}
		
		return $output;
	}
	
	/**
	 * 记录上传日志
	 *
	 * @param array $file 文件信息
	 */
	private function log_upload( $file ) {
		if ( ! class_exists( 'WPMCS_Logger' ) ) {
			return;
		}
		
		$logger = new WPMCS_Logger();
		
		$logger->info(
			sprintf(
				'文件上传成功：%s (%s, %s)',
				$file['name'],
				$file['type'],
				size_format( $file['size'] )
			),
			array(
				'file_name' => $file['name'],
				'file_type' => $file['type'],
				'file_size' => $file['size'],
			)
		);
	}
	
	// ========================================
	// 加密相关方法
	// ========================================
	
	/**
	 * 生成加密密钥
	 *
	 * @return string
	 */
	public function generate_encryption_key() {
		if ( ! function_exists( 'random_bytes' ) ) {
			return wp_generate_password( 32, true, true );
		}
		
		try {
			return bin2hex( random_bytes( 32 ) );
		} catch ( Exception $e ) {
			return wp_generate_password( 32, true, true );
		}
	}
	
	/**
	 * 获取加密密钥
	 *
	 * @return string
	 */
	private function get_encryption_key() {
		$key = get_option( self::ENCRYPTION_KEY_OPTION );
		
		if ( empty( $key ) ) {
			$key = $this->generate_encryption_key();
			update_option( self::ENCRYPTION_KEY_OPTION, $key );
		}
		
		return $key;
	}
	
	/**
	 * 加密敏感信息
	 *
	 * @param string $data 要加密的数据
	 * @return string|false
	 */
	public function encrypt( $data ) {
		if ( empty( $data ) ) {
			return false;
		}
		
		$key = $this->get_encryption_key();
		$iv_length = openssl_cipher_iv_length( self::ENCRYPTION_ALGO );
		$iv = openssl_random_pseudo_bytes( $iv_length );
		
		$encrypted = openssl_encrypt(
			$data,
			self::ENCRYPTION_ALGO,
			hex2bin( $key ),
			OPENSSL_RAW_DATA,
			$iv
		);
		
		if ( $encrypted === false ) {
			return false;
		}
		
		// 组合 IV 和加密数据
		return base64_encode( $iv . $encrypted );
	}
	
	/**
	 * 解密敏感信息
	 *
	 * @param string $data 加密的数据
	 * @return string|false
	 */
	public function decrypt( $data ) {
		if ( empty( $data ) ) {
			return false;
		}
		
		$key = $this->get_encryption_key();
		$data = base64_decode( $data );
		
		if ( $data === false ) {
			return false;
		}
		
		$iv_length = openssl_cipher_iv_length( self::ENCRYPTION_ALGO );
		$iv = substr( $data, 0, $iv_length );
		$encrypted = substr( $data, $iv_length );
		
		$decrypted = openssl_decrypt(
			$encrypted,
			self::ENCRYPTION_ALGO,
			hex2bin( $key ),
			OPENSSL_RAW_DATA,
			$iv
		);
		
		return $decrypted;
	}
	
	/**
	 * 加密设置中的敏感字段
	 *
	 * @param array $settings 设置数组
	 * @return array
	 */
	public function encrypt_settings( $settings ) {
		$sensitive_fields = array(
			'access_key',
			'secret_key',
			'secret_id',
			'password',
		);
		
		foreach ( $sensitive_fields as $field ) {
			if ( isset( $settings[ $field ] ) && ! empty( $settings[ $field ] ) ) {
				// 检查是否已加密
				if ( ! $this->is_encrypted( $settings[ $field ] ) ) {
					$settings[ $field ] = $this->encrypt( $settings[ $field ] );
				}
			}
		}
		
		return $settings;
	}
	
	/**
	 * 解密设置中的敏感字段
	 *
	 * @param array $settings 设置数组
	 * @return array
	 */
	public function decrypt_settings( $settings ) {
		$sensitive_fields = array(
			'access_key',
			'secret_key',
			'secret_id',
			'password',
		);
		
		foreach ( $sensitive_fields as $field ) {
			if ( isset( $settings[ $field ] ) && ! empty( $settings[ $field ] ) ) {
				if ( $this->is_encrypted( $settings[ $field ] ) ) {
					$decrypted = $this->decrypt( $settings[ $field ] );
					if ( $decrypted !== false ) {
						$settings[ $field ] = $decrypted;
					}
				}
			}
		}
		
		return $settings;
	}
	
	/**
	 * 检查数据是否已加密
	 *
	 * @param string $data 数据
	 * @return bool
	 */
	private function is_encrypted( $data ) {
		// 简单检查：尝试解密
		$decrypted = $this->decrypt( $data );
		return $decrypted !== false;
	}
	
	// ========================================
	// 权限控制
	// ========================================
	
	/**
	 * 检查用户权限
	 *
	 * @param string $capability 权限能力
	 * @return bool
	 */
	public function current_user_can( $capability = 'upload_files' ) {
		return current_user_can( $capability );
	}
	
	/**
	 * 检查是否可以上传文件
	 *
	 * @param int $user_id 用户ID
	 * @return bool
	 */
	public function can_upload_files( $user_id = null ) {
		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}
		
		return user_can( $user_id, 'upload_files' );
	}
	
	/**
	 * 检查是否可以管理云存储设置
	 *
	 * @param int $user_id 用户ID
	 * @return bool
	 */
	public function can_manage_settings( $user_id = null ) {
		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}
		
		return user_can( $user_id, 'manage_options' );
	}
	
	/**
	 * 检查是否可以查看统计信息
	 *
	 * @param int $user_id 用户ID
	 * @return bool
	 */
	public function can_view_stats( $user_id = null ) {
		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}
		
		return user_can( $user_id, 'manage_options' );
	}
	
	/**
	 * 检查是否可以查看日志
	 *
	 * @param int $user_id 用户ID
	 * @return bool
	 */
	public function can_view_logs( $user_id = null ) {
		if ( $user_id === null ) {
			$user_id = get_current_user_id();
		}
		
		return user_can( $user_id, 'manage_options' );
	}
	
	/**
	 * 获取用户角色权限
	 *
	 * @return array
	 */
	public function get_role_capabilities() {
		$roles = array(
			'administrator' => array(
				'upload' => true,
				'manage' => true,
				'view_stats' => true,
				'view_logs' => true,
			),
			'editor' => array(
				'upload' => true,
				'manage' => false,
				'view_stats' => false,
				'view_logs' => false,
			),
			'author' => array(
				'upload' => true,
				'manage' => false,
				'view_stats' => false,
				'view_logs' => false,
			),
			'contributor' => array(
				'upload' => false,
				'manage' => false,
				'view_stats' => false,
				'view_logs' => false,
			),
			'subscriber' => array(
				'upload' => false,
				'manage' => false,
				'view_stats' => false,
				'view_logs' => false,
			),
		);
		
		return $roles;
	}
	
	// ========================================
	// AJAX 处理
	// ========================================
	
	/**
	 * AJAX 验证文件
	 */
	public function ajax_validate_file() {
		check_ajax_referer( 'wpmcs_security', 'nonce' );
		
		if ( ! $this->can_manage_settings() ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$file_url = isset( $_POST['file_url'] ) ? esc_url_raw( $_POST['file_url'] ) : '';
		$file_name = isset( $_POST['file_name'] ) ? sanitize_file_name( $_POST['file_name'] ) : '';
		
		if ( empty( $file_url ) ) {
			wp_send_json_error( array( 'message' => '文件 URL 不能为空' ) );
		}
		
		// 下载文件进行验证
		$response = wp_remote_get( $file_url, array(
			'timeout' => 30,
			'stream' => true,
			'filename' => tempnam( sys_get_temp_dir(), 'wpmcs_' ),
		) );
		
		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => '文件下载失败: ' . $response->get_error_message() ) );
		}
		
		$file_path = $response['filename'];
		$file_type = wp_remote_retrieve_header( $response, 'content-type' );
		$file_size = wp_remote_retrieve_header( $response, 'content-length' );
		
		// 验证文件
		$type_result = $this->validate_file_type( $file_name, $file_type, $file_path );
		if ( is_wp_error( $type_result ) ) {
			wp_send_json_error( array( 'message' => $type_result->get_error_message() ) );
		}
		
		$size_result = $this->validate_file_size( $file_size, $file_type );
		if ( is_wp_error( $size_result ) ) {
			wp_send_json_error( array( 'message' => $size_result->get_error_message() ) );
		}
		
		$content_result = $this->validate_file_content( $file_path, $file_type );
		if ( is_wp_error( $content_result ) ) {
			wp_send_json_error( array( 'message' => $content_result->get_error_message() ) );
		}
		
		// 清理临时文件
		@unlink( $file_path );
		
		wp_send_json_success( array(
			'message' => '文件验证通过',
			'file_type' => $file_type,
			'file_size' => size_format( $file_size ),
		) );
	}
}
