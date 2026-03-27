<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPMCS_Quick_Setup {
	private $settings;

	private $presets = array(
		'basic' => array(
			'name' => '基础方案',
			'description' => '适合大多数站点，优先保证稳定和最少配置。',
			'icon' => 'dashicons-admin-users',
			'settings' => array(
				'enabled' => '1',
				'replace_url' => '1',
				'keep_local_file' => '1',
				'convert_to_webp' => '0',
				'auto_rename' => '1',
				'async_upload' => '0',
				'enable_cache' => '1',
				'enable_logging' => '0',
			),
		),
		'performance' => array(
			'name' => '性能优先',
			'description' => '适合有缓存插件或流量较高的站点。',
			'icon' => 'dashicons-performance',
			'settings' => array(
				'enabled' => '1',
				'replace_url' => '1',
				'keep_local_file' => '1',
				'convert_to_webp' => '0',
				'auto_rename' => '1',
				'async_upload' => '1',
				'enable_cache' => '1',
				'enable_logging' => '0',
			),
		),
		'developer' => array(
			'name' => '开发调试',
			'description' => '开启更多日志，方便排查上传和连接问题。',
			'icon' => 'dashicons-editor-code',
			'settings' => array(
				'enabled' => '1',
				'replace_url' => '1',
				'keep_local_file' => '1',
				'convert_to_webp' => '0',
				'auto_rename' => '1',
				'async_upload' => '1',
				'enable_cache' => '1',
				'enable_logging' => '1',
				'debug_mode' => '1',
			),
		),
		'minimal' => array(
			'name' => '极简模式',
			'description' => '只开启最基础的功能，适合测试环境。',
			'icon' => 'dashicons-minus',
			'settings' => array(
				'enabled' => '1',
				'replace_url' => '0',
				'keep_local_file' => '1',
				'convert_to_webp' => '0',
				'auto_rename' => '0',
				'async_upload' => '0',
				'enable_cache' => '0',
				'enable_logging' => '0',
			),
		),
	);

	public function __construct() {
		$this->init_hooks();
	}

	public function init_hooks() {
		add_action( 'wp_ajax_wpmcs_quick_setup_preset', array( $this, 'ajax_apply_preset' ) );
		add_action( 'wp_ajax_wpmcs_quick_setup_provider', array( $this, 'ajax_quick_setup_provider' ) );
		add_action( 'wp_ajax_wpmcs_get_presets', array( $this, 'ajax_get_presets' ) );
		add_action( 'admin_menu', array( $this, 'add_quick_setup_page' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function add_quick_setup_page() {
		add_submenu_page(
			WPMCS_Admin_Page::MENU_SLUG,
			'快速设置',
			'快速设置',
			'manage_options',
			'wpmcs-quick-setup',
			array( $this, 'render_quick_setup_page' )
		);
	}

	public function enqueue_assets( $hook_suffix ) {
		if ( false === strpos( $hook_suffix, 'wpmcs-quick-setup' ) ) {
			return;
		}

		wp_enqueue_style( 'wpmcs-wizard', WPMCS_PLUGIN_URL . 'assets/css/wizard.css', array(), WPMCS_VERSION );
		wp_enqueue_style( 'wpmcs-quick-setup', WPMCS_PLUGIN_URL . 'assets/css/quick-setup.css', array( 'wpmcs-wizard' ), WPMCS_VERSION );
		wp_enqueue_style( 'wpmcs-provider-icons', WPMCS_PLUGIN_URL . 'assets/css/provider-icons.css', array(), WPMCS_VERSION );
		wp_enqueue_script( 'wpmcs-quick-setup', WPMCS_PLUGIN_URL . 'assets/js/quick-setup.js', array( 'jquery' ), WPMCS_VERSION, true );

		wp_localize_script(
			'wpmcs-quick-setup',
			'wpmcsQuickSetup',
			array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'wpmcs_quick_setup' ),
				'provider_nonce' => wp_create_nonce( 'wpmcs_provider_fields' ),
				'presets'        => $this->get_all_presets(),
			)
		);
	}

	public function render_quick_setup_page() {
		include WPMCS_PLUGIN_DIR . 'admin/views/quick-setup-page.php';
	}

	public function get_all_presets() {
		return apply_filters( 'wpmcs_quick_setup_presets', $this->presets );
	}

	public function get_preset( $preset_id ) {
		$presets = $this->get_all_presets();
		return isset( $presets[ $preset_id ] ) ? $presets[ $preset_id ] : null;
	}

	public function apply_preset( $preset_id ) {
		$preset = $this->get_preset( $preset_id );

		if ( ! $preset ) {
			return new WP_Error( 'invalid_preset', '无效的预设。' );
		}

		$settings = wpmcs_get_settings();
		foreach ( $preset['settings'] as $key => $value ) {
			$settings[ $key ] = $value;
		}

		wpmcs_save_settings( $settings );

		do_action( 'wpmcs_quick_setup_preset_applied', $preset_id, $settings );

		return true;
	}

	public function quick_setup_provider( $provider, $credentials ) {
		$providers = WPMCS_Provider_Icons::get_all_providers();

		if ( ! isset( $providers[ $provider ] ) ) {
			return new WP_Error( 'invalid_provider', '无效的服务商。' );
		}

		$settings = wpmcs_get_settings();
		$settings['provider'] = $provider;

		$sensitive_fields = array( 'access_key', 'secret_key', 'secret_id', 'password' );
		$security_manager = new WPMCS_Security_Manager( $settings );

		foreach ( (array) $credentials as $key => $value ) {
			$key = sanitize_key( $key );
			$value = sanitize_text_field( $value );

			if ( in_array( $key, $sensitive_fields, true ) ) {
				$value = $security_manager->encrypt( $value );
			}

			$settings[ $key ] = $value;
		}

		wpmcs_save_settings( $settings );

		$tester = new WPMCS_Connection_Tester( $settings );
		$result = $tester->run_full_test();

		if ( ! $result['success'] ) {
			return new WP_Error( 'connection_failed', isset( $result['message'] ) ? $result['message'] : '连接测试失败。' );
		}

		do_action( 'wpmcs_quick_setup_provider_configured', $provider, $settings );

		return $result;
	}

	public function ajax_apply_preset() {
		check_ajax_referer( 'wpmcs_quick_setup', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '没有权限执行此操作。' ) );
		}

		$preset_id = isset( $_POST['preset'] ) ? sanitize_key( $_POST['preset'] ) : '';
		if ( empty( $preset_id ) ) {
			wp_send_json_error( array( 'message' => '请选择一个预设。' ) );
		}

		$result = $this->apply_preset( $preset_id );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'  => '预设已保存。',
				'redirect' => admin_url( 'admin.php?page=' . WPMCS_Admin_Page::MENU_SLUG ),
			)
		);
	}

	public function ajax_quick_setup_provider() {
		check_ajax_referer( 'wpmcs_quick_setup', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '没有权限执行此操作。' ) );
		}

		$provider = isset( $_POST['provider'] ) ? sanitize_key( $_POST['provider'] ) : '';
		$credentials = isset( $_POST['credentials'] ) ? (array) $_POST['credentials'] : array();

		if ( empty( $provider ) ) {
			wp_send_json_error( array( 'message' => '请选择一个云服务商。' ) );
		}

		$result = $this->quick_setup_provider( $provider, $credentials );
		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success(
			array(
				'message'      => '配置保存成功。',
				'test_result'  => $result,
				'redirect'     => admin_url( 'admin.php?page=' . WPMCS_Admin_Page::MENU_SLUG ),
			)
		);
	}

	public function ajax_get_presets() {
		check_ajax_referer( 'wpmcs_quick_setup', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '没有权限执行此操作。' ) );
		}

		wp_send_json_success( array( 'presets' => $this->get_all_presets() ) );
	}

	public function get_recommended_preset() {
		$plugins = get_option( 'active_plugins', array() );
		$cache_plugins = array( 'wp-super-cache', 'w3-total-cache', 'wp-rocket' );

		foreach ( $cache_plugins as $cache_plugin ) {
			if ( in_array( $cache_plugin . '/' . $cache_plugin . '.php', $plugins, true ) ) {
				return 'performance';
			}
		}

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			return 'developer';
		}

		return 'basic';
	}
}
