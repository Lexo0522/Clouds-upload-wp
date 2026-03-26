<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 日志查看页面
 */
class WPMCS_Logs_Page {
	
	private $logger;
	
	public function __construct() {
		$this->logger = new WPMCS_Logger();
	}
	
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'wp_ajax_wpmcs_get_logs', array( $this, 'ajax_get_logs' ) );
		add_action( 'wp_ajax_wpmcs_clear_logs', array( $this, 'ajax_clear_logs' ) );
		add_action( 'wp_ajax_wpmcs_export_logs', array( $this, 'ajax_export_logs' ) );
		add_action( 'wp_ajax_wpmcs_get_log_analysis', array( $this, 'ajax_get_analysis' ) );
	}
	
	public function add_menu_page() {
		add_submenu_page(
			WPMCS_Admin_Page::MENU_SLUG,
			'日志查看',
			'云存储日志',
			'manage_options',
			'wpmcs-logs',
			array( $this, 'render_page' )
		);
	}
	
	public function render_page() {
		// 加载资源
		wp_enqueue_style(
			'wpmcs-logs',
			WPMCS_PLUGIN_URL . 'assets/css/logs.css',
			array(),
			WPMCS_VERSION
		);
		
		wp_enqueue_script(
			'wpmcs-logs',
			WPMCS_PLUGIN_URL . 'assets/js/logs.js',
			array(),
			WPMCS_VERSION,
			true
		);
		
		wp_localize_script( 'wpmcs-logs', 'wpmcsLogs', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wpmcs_logs' ),
		) );
		
		include WPMCS_PLUGIN_DIR . 'admin/views/logs-page.php';
	}
	
	public function ajax_get_logs() {
		check_ajax_referer( 'wpmcs_logs', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$args = array(
			'level' => isset( $_POST['level'] ) ? sanitize_text_field( $_POST['level'] ) : '',
			'type' => isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '',
			'search' => isset( $_POST['search'] ) ? sanitize_text_field( $_POST['search'] ) : '',
			'date_from' => isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : '',
			'date_to' => isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : '',
			'per_page' => isset( $_POST['per_page'] ) ? intval( $_POST['per_page'] ) : 50,
			'page' => isset( $_POST['page'] ) ? intval( $_POST['page'] ) : 1,
		);
		
		$result = $this->logger->get_logs( $args );
		
		wp_send_json_success( $result );
	}
	
	public function ajax_clear_logs() {
		check_ajax_referer( 'wpmcs_logs', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$result = $this->logger->clear_all();
		
		if ( $result ) {
			wp_send_json_success( array( 'message' => '日志已清空' ) );
		} else {
			wp_send_json_error( array( 'message' => '清空失败' ) );
		}
	}
	
	public function ajax_export_logs() {
		check_ajax_referer( 'wpmcs_logs', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$args = array(
			'level' => isset( $_POST['level'] ) ? sanitize_text_field( $_POST['level'] ) : '',
			'type' => isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '',
			'date_from' => isset( $_POST['date_from'] ) ? sanitize_text_field( $_POST['date_from'] ) : '',
			'date_to' => isset( $_POST['date_to'] ) ? sanitize_text_field( $_POST['date_to'] ) : '',
		);
		
		$csv = $this->logger->export_logs( $args );
		
		wp_send_json_success( array( 'csv' => $csv ) );
	}
	
	public function ajax_get_analysis() {
		check_ajax_referer( 'wpmcs_logs', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$stats = $this->logger->get_stats( 7 );
		$analysis = $this->logger->analyze_errors();
		
		wp_send_json_success( array(
			'stats' => $stats,
			'analysis' => $analysis,
		) );
	}
}
