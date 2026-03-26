<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 安全设置页面
 */
class WPMCS_Security_Page {
	
	/**
	 * 构造函数
	 */
	public function __construct() {
		$this->init_hooks();
	}
	
	/**
	 * 初始化钩子
	 */
	public function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'wp_ajax_wpmcs_regenerate_encryption_key', array( $this, 'ajax_regenerate_key' ) );
	}
	
	/**
	 * 添加菜单页面
	 */
	public function add_menu_page() {
		add_submenu_page(
			WPMCS_Admin_Page::MENU_SLUG,
			'安全设置',
			'云存储安全',
			'manage_options',
			'wpmcs-security',
			array( $this, 'render_page' )
		);
	}
	
	/**
	 * 注册设置
	 */
	public function register_settings() {
		register_setting( 'wpmcs_security_settings_group', 'wpmcs_allowed_file_types' );
		register_setting( 'wpmcs_security_settings_group', 'wpmcs_blocked_file_types' );
		register_setting( 'wpmcs_security_settings_group', 'wpmcs_block_dangerous_extensions' );
		register_setting( 'wpmcs_security_settings_group', 'wpmcs_strict_mode' );
		register_setting( 'wpmcs_security_settings_group', 'wpmcs_check_real_mime' );
		register_setting( 'wpmcs_security_settings_group', 'wpmcs_encrypt_sensitive_data' );
		
		// 文件大小限制
		register_setting( 'wpmcs_security_settings_group', 'wpmcs_file_size_limits_image' );
		register_setting( 'wpmcs_security_settings_group', 'wpmcs_file_size_limits_video' );
		register_setting( 'wpmcs_security_settings_group', 'wpmcs_file_size_limits_audio' );
		register_setting( 'wpmcs_security_settings_group', 'wpmcs_file_size_limits_application' );
		register_setting( 'wpmcs_security_settings_group', 'wpmcs_file_size_limits_text' );
		register_setting( 'wpmcs_security_settings_group', 'wpmcs_file_size_limits_default' );
	}
	
	/**
	 * 渲染页面
	 */
	public function render_page() {
		// 获取文件大小限制
		$size_limits = array(
			'image' => get_option( 'wpmcs_file_size_limits_image', 10485760 ),
			'video' => get_option( 'wpmcs_file_size_limits_video', 524288000 ),
			'audio' => get_option( 'wpmcs_file_size_limits_audio', 52428800 ),
			'application' => get_option( 'wpmcs_file_size_limits_application', 20971520 ),
			'text' => get_option( 'wpmcs_file_size_limits_text', 5242880 ),
			'default' => get_option( 'wpmcs_file_size_limits_default', 10485760 ),
		);
		
		// 获取安全统计数据
		$upload_count = $this->get_today_upload_count();
		$blocked_count = $this->get_blocked_count();
		$warning_count = $this->get_warning_count();
		$security_score = $this->calculate_security_score();
		$recent_events = $this->get_recent_security_events();
		
		include WPMCS_PLUGIN_DIR . 'admin/views/security-page.php';
	}
	
	/**
	 * AJAX 重新生成密钥
	 */
	public function ajax_regenerate_key() {
		check_ajax_referer( 'wpmcs_security', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$security_manager = new WPMCS_Security_Manager( array() );
		$new_key = $security_manager->generate_encryption_key();
		
		if ( update_option( 'wpmcs_encryption_key', $new_key ) ) {
			wp_send_json_success( array( 'message' => '加密密钥已重新生成' ) );
		} else {
			wp_send_json_error( array( 'message' => '密钥生成失败' ) );
		}
	}
	
	/**
	 * 获取今日上传数量
	 */
	private function get_today_upload_count() {
		global $wpdb;
		
		$today = date( 'Y-m-d' );
		
		return (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$wpdb->posts} 
			WHERE post_type = 'attachment' 
			AND post_status = 'inherit'
			AND DATE(post_date) = %s",
			$today
		) );
	}
	
	/**
	 * 获取被拦截的文件数量
	 */
	private function get_blocked_count() {
		global $wpdb;
		
		$log_table = $wpdb->prefix . 'wpmcs_logs';
		
		// 检查表是否存在
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$log_table}'" ) !== $log_table ) {
			return 0;
		}
		
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$log_table} 
			WHERE level = 'warning' 
			AND type = 'security'"
		);
	}
	
	/**
	 * 获取警告事件数量
	 */
	private function get_warning_count() {
		global $wpdb;
		
		$log_table = $wpdb->prefix . 'wpmcs_logs';
		
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$log_table}'" ) !== $log_table ) {
			return 0;
		}
		
		return (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$log_table} 
			WHERE level IN ('warning', 'error')"
		);
	}
	
	/**
	 * 计算安全评分
	 */
	private function calculate_security_score() {
		$score = 0;
		$max_score = 100;
		
		// 检查各项安全设置
		if ( get_option( 'wpmcs_strict_mode', '0' ) === '1' ) {
			$score += 20;
		}
		
		if ( get_option( 'wpmcs_check_real_mime', '1' ) === '1' ) {
			$score += 20;
		}
		
		if ( get_option( 'wpmcs_encrypt_sensitive_data', '1' ) === '1' ) {
			$score += 20;
		}
		
		if ( get_option( 'wpmcs_block_dangerous_extensions', '1' ) === '1' ) {
			$score += 20;
		}
		
		if ( get_option( 'wpmcs_encryption_key' ) ) {
			$score += 10;
		}
		
		// 检查是否有自定义白名单
		if ( get_option( 'wpmcs_allowed_file_types' ) ) {
			$score += 10;
		}
		
		return min( $score, $max_score );
	}
	
	/**
	 * 获取最近的安全事件
	 */
	private function get_recent_security_events() {
		global $wpdb;
		
		$log_table = $wpdb->prefix . 'wpmcs_logs';
		
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$log_table}'" ) !== $log_table ) {
			return array();
		}
		
		$results = $wpdb->get_results(
			"SELECT * FROM {$log_table} 
			WHERE type IN ('security', 'upload') 
			ORDER BY created_at DESC 
			LIMIT 10"
		);
		
		$events = array();
		if ( $results ) {
			foreach ( $results as $row ) {
				$context = maybe_unserialize( $row->context );
				
				$events[] = array(
					'time' => $row->created_at,
					'type' => $row->type,
					'file' => isset( $context['file_name'] ) ? $context['file_name'] : '-',
					'user' => '-',
					'status' => $row->level === 'info' ? 'allowed' : ( $row->level === 'warning' ? 'warning' : 'blocked' ),
					'status_label' => $row->level === 'info' ? '已允许' : ( $row->level === 'warning' ? '警告' : '已拦截' ),
				);
			}
		}
		
		return $events;
	}
}
