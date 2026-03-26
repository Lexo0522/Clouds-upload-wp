<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPMCS_Setup_Wizard {
	private $steps = array(
		'welcome' => array(
			'name' => '欢迎',
			'icon' => 'dashicons-admin-site-alt',
		),
		'provider' => array(
			'name' => '选择服务商',
			'icon' => 'dashicons-cloud',
		),
		'configure' => array(
			'name' => '填写配置',
			'icon' => 'dashicons-admin-settings',
		),
		'test' => array(
			'name' => '连接测试',
			'icon' => 'dashicons-admin-links',
		),
		'options' => array(
			'name' => '功能选项',
			'icon' => 'dashicons-admin-generic',
		),
		'complete' => array(
			'name' => '完成',
			'icon' => 'dashicons-yes-alt',
		),
	);

	private $current_step = 'welcome';
	private $settings;

	public function __construct() {
		$this->current_step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : 'welcome';

		if ( ! isset( $this->steps[ $this->current_step ] ) ) {
			$this->current_step = 'welcome';
		}

		$this->init_hooks();
	}

	private function get_settings() {
		if ( ! is_array( $this->settings ) ) {
			$this->settings = wpmcs_get_settings();
		}

		return $this->settings;
	}

	public function init_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'wp_ajax_wpmcs_wizard_save_step', array( $this, 'ajax_save_step' ) );
		add_action( 'wp_ajax_wpmcs_wizard_test_connection', array( $this, 'ajax_test_connection' ) );
		add_action( 'wp_ajax_wpmcs_wizard_skip', array( $this, 'ajax_skip_wizard' ) );
	}

	public function add_menu_page() {
		if ( ! $this->is_wizard_completed() ) {
			add_submenu_page(
				WPMCS_Admin_Page::MENU_SLUG,
				'云存储配置向导',
				'云存储配置向导',
				'manage_options',
				'wpmcs-setup-wizard',
				array( $this, 'render_page' )
			);
		}
	}

	public function is_wizard_completed() {
		return (bool) get_option( 'wpmcs_wizard_completed', false );
	}

	public function mark_completed() {
		update_option( 'wpmcs_wizard_completed', true );
	}

	public function get_steps() {
		return $this->steps;
	}

	public function get_current_step() {
		return $this->current_step;
	}

	public function set_current_step( $step ) {
		if ( isset( $this->steps[ $step ] ) ) {
			$this->current_step = $step;
		}
	}

	public function render_page() {
		$this->current_step = isset( $_GET['step'] ) ? sanitize_key( $_GET['step'] ) : 'welcome';
		$current_settings   = $this->get_settings();
		$current_provider   = isset( $current_settings['provider'] ) ? $current_settings['provider'] : 'qiniu';

		if ( ! isset( $this->steps[ $this->current_step ] ) ) {
			$this->current_step = 'welcome';
		}

		wp_enqueue_style( 'wpmcs-wizard', WPMCS_PLUGIN_URL . 'assets/css/wizard.css', array(), WPMCS_VERSION );
		wp_enqueue_script( 'wpmcs-wizard', WPMCS_PLUGIN_URL . 'assets/js/wizard.js', array( 'jquery' ), WPMCS_VERSION, true );

		wp_localize_script(
			'wpmcs-wizard',
			'wpmcsWizard',
			array(
				'ajax_url'       => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( 'wpmcs_wizard' ),
				'provider_nonce' => wp_create_nonce( 'wpmcs_provider_fields' ),
				'current_step'   => $this->current_step,
				'current_provider' => $current_provider,
				'providers'      => $this->get_providers_data(),
			)
		);

		include WPMCS_PLUGIN_DIR . 'admin/views/wizard-page.php';
	}

	private function get_providers_data() {
		$providers = WPMCS_Provider_Icons::get_all_providers();
		$data = array();

		foreach ( $providers as $key => $provider ) {
			$data[ $key ] = array(
				'name'    => $provider['name'],
				'icon'    => WPMCS_Provider_Icons::get_icon_url( $key ),
				'color'   => $provider['color'],
				'website' => $provider['website'],
			);
		}

		return $data;
	}

	public function render_step_content( $step ) {
		$method = 'render_step_' . $step;

		if ( method_exists( $this, $method ) ) {
			return $this->$method();
		}

		return '';
	}

	private function render_step_welcome() {
		ob_start();
		?>
		<div class="wpmcs-wizard-welcome">
			<div class="wpmcs-welcome-icon">
				<img src="<?php echo esc_url( WPMCS_PLUGIN_URL . 'assets/images/providers/Keji.svg' ); ?>" alt="Keji">
			</div>

			<h2>欢迎使用 WP 多云存储插件！</h2>

			<p class="wpmcs-welcome-text">
				这是一套把 WordPress 媒体文件接入云存储的工具。你可以在几步之内完成服务商选择、参数填写、连接测试和常用选项配置。
			</p>

			<div class="wpmcs-features-grid">
				<div class="wpmcs-feature">
					<span class="dashicons dashicons-cloud"></span>
					<h3>支持多家厂商</h3>
					<p>支持七牛云、阿里云 OSS、腾讯云 COS、又拍云、多吉云和 AWS S3。</p>
				</div>

				<div class="wpmcs-feature">
					<span class="dashicons dashicons-upload"></span>
					<h3>上传自动化</h3>
					<p>支持自动替换地址、自动重命名以及异步上传等能力。</p>
				</div>

				<div class="wpmcs-feature">
					<span class="dashicons dashicons-admin-links"></span>
					<h3>CDN 兼容</h3>
					<p>可配合访问域名和缓存策略一起使用，提高媒体访问效率。</p>
				</div>

				<div class="wpmcs-feature">
					<span class="dashicons dashicons-image-rotate"></span>
					<h3>迁移友好</h3>
					<p>支持批量迁移和后台维护，适合已有站点逐步切换。</p>
				</div>
			</div>

			<div class="wpmcs-wizard-actions">
				<button type="button" class="button button-primary button-hero wpmcs-wizard-start">
					开始配置
				</button>

				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . WPMCS_Admin_Page::MENU_SLUG ) ); ?>" class="wpmcs-wizard-skip">
					进入完整设置
				</a>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_step_provider() {
		ob_start();
		$providers = WPMCS_Provider_Icons::get_all_providers();
		$settings = $this->get_settings();
		$current_provider = isset( $settings['provider'] ) ? $settings['provider'] : 'qiniu';
		?>
		<div class="wpmcs-wizard-providers">
			<h2>选择云服务商</h2>
			<p>先选择你要接入的云服务商，随后再填写对应的访问密钥和 Bucket 信息。</p>

			<div class="wpmcs-provider-grid">
				<?php foreach ( $providers as $key => $provider ) : ?>
					<label class="wpmcs-provider-card <?php echo $current_provider === $key ? 'selected' : ''; ?>">
						<input type="radio" name="provider" value="<?php echo esc_attr( $key ); ?>" <?php checked( $current_provider, $key ); ?>>

						<div class="wpmcs-provider-icon">
							<img src="<?php echo esc_url( WPMCS_Provider_Icons::get_icon_url( $key ) ); ?>" alt="<?php echo esc_attr( $provider['name'] ); ?>">
						</div>

						<div class="wpmcs-provider-info">
							<span class="wpmcs-provider-name"><?php echo esc_html( $provider['name'] ); ?></span>
							<a href="<?php echo esc_url( $provider['website'] ); ?>" target="_blank" class="wpmcs-provider-link" rel="noreferrer">
								<span class="dashicons dashicons-external"></span> 官方文档
							</a>
						</div>

						<span class="wpmcs-provider-check dashicons dashicons-yes-alt"></span>
					</label>
				<?php endforeach; ?>
			</div>

			<p class="description">选好服务商后，点击下一步继续填写配置。</p>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_step_configure() {
		ob_start();
		$settings = $this->get_settings();
		$provider = isset( $settings['provider'] ) ? $settings['provider'] : 'qiniu';
		$admin_page = new WPMCS_Admin_Page();
		?>
		<div class="wpmcs-wizard-configure">
			<h2>填写云存储参数</h2>
			<p>根据你选择的服务商填写对应信息，填写完成后可以直接进行连接测试。</p>

			<div id="wpmcs-wizard-provider-fields">
				<?php $admin_page->render_provider_fields_html( $provider, $settings ); ?>
			</div>

			<div class="wpmcs-config-help">
				<h3>填写说明</h3>
				<div id="wpmcs-provider-help">
					<?php echo $this->get_provider_help( $provider ); ?>
				</div>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_step_test() {
		ob_start();
		?>
		<div class="wpmcs-wizard-test">
			<h2>连接测试</h2>
			<p>保存配置后，先测试当前账号是否可以正常连接到云存储。</p>

			<div class="wpmcs-test-status">
				<div class="wpmcs-test-pending">
					<span class="dashicons dashicons-update"></span>
					<p>等待开始测试</p>
				</div>

				<div class="wpmcs-test-running" style="display: none;">
					<span class="dashicons dashicons-update is-active"></span>
					<p>正在测试连接...</p>
				</div>

				<div class="wpmcs-test-success" style="display: none;">
					<span class="dashicons dashicons-yes-alt"></span>
					<h3>测试成功</h3>
					<p>连接正常，可以继续下一步。</p>
				</div>

				<div class="wpmcs-test-error" style="display: none;">
					<span class="dashicons dashicons-warning"></span>
					<h3>测试失败</h3>
					<p class="wpmcs-test-error-message"></p>
				</div>
			</div>

			<div class="wpmcs-test-actions">
				<button type="button" class="button button-primary wpmcs-test-connection">
					<span class="dashicons dashicons-admin-links"></span> 测试连接
				</button>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_step_options() {
		ob_start();
		?>
		<div class="wpmcs-wizard-options">
			<h2>功能选项</h2>
			<p>按需开启或关闭下列功能，保存后即可生效。</p>

			<table class="form-table">
				<tr>
					<th>转换为 WebP</th>
					<td>
						<label class="wpmcs-switch">
							<input type="checkbox" name="convert_to_webp" value="1">
							<span class="wpmcs-switch-slider"></span>
						</label>
						<p class="description">上传图片时先尝试转成 WebP，失败后自动回退原格式。</p>
					</td>
				</tr>

				<tr>
					<th>WebP 质量</th>
					<td>
						<input type="number" name="webp_quality" value="82" min="1" max="100" step="1">
						<p class="description">数值越大，画质越好、文件越大。建议范围 70-85。</p>
					</td>
				</tr>

				<tr>
					<th>启用插件</th>
					<td>
						<label class="wpmcs-switch">
							<input type="checkbox" name="enabled" value="1" checked>
							<span class="wpmcs-switch-slider"></span>
						</label>
						<p class="description">关闭后插件不会处理上传流程。</p>
					</td>
				</tr>

				<tr>
					<th>保留本地文件</th>
					<td>
						<label class="wpmcs-switch">
							<input type="checkbox" name="keep_local_file" value="1" checked>
							<span class="wpmcs-switch-slider"></span>
						</label>
						<p class="description">关闭后，上传成功且已启用替换链接时会删除本地文件和缩略图。</p>
					</td>
				</tr>

				<tr>
					<th>替换媒体 URL</th>
					<td>
						<label class="wpmcs-switch">
							<input type="checkbox" name="replace_url" value="1" checked>
							<span class="wpmcs-switch-slider"></span>
						</label>
						<p class="description">开启后会把媒体地址替换为云存储地址。</p>
					</td>
				</tr>

				<tr>
					<th>自动重命名</th>
					<td>
						<label class="wpmcs-switch">
							<input type="checkbox" name="auto_rename" value="1" checked>
							<span class="wpmcs-switch-slider"></span>
						</label>
						<p class="description">上传时自动生成更安全的文件名。</p>
					</td>
				</tr>

				<tr>
					<th>异步上传</th>
					<td>
						<label class="wpmcs-switch">
							<input type="checkbox" name="async_upload" value="1">
							<span class="wpmcs-switch-slider"></span>
						</label>
						<p class="description">开启后上传任务会进入异步队列。</p>
					</td>
				</tr>

				<tr>
					<th>启用缓存</th>
					<td>
						<label class="wpmcs-switch">
							<input type="checkbox" name="enable_cache" value="1" checked>
							<span class="wpmcs-switch-slider"></span>
						</label>
						<p class="description">缓存有助于减少重复请求。</p>
					</td>
				</tr>

				<tr>
					<th>启用日志</th>
					<td>
						<label class="wpmcs-switch">
							<input type="checkbox" name="enable_logging" value="1" checked>
							<span class="wpmcs-switch-slider"></span>
						</label>
						<p class="description">记录上传和同步过程中的日志。</p>
					</td>
				</tr>
			</table>
		</div>
		<?php
		return ob_get_clean();
	}

	private function render_step_complete() {
		ob_start();
		?>
		<div class="wpmcs-wizard-complete">
			<div class="wpmcs-complete-icon">
				<span class="dashicons dashicons-yes-alt"></span>
			</div>

			<h2>配置完成</h2>

			<p>你已经完成了基础配置，可以继续使用云存储功能。</p>

			<div class="wpmcs-complete-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . WPMCS_Admin_Page::MENU_SLUG ) ); ?>" class="button button-primary">进入完整设置</a>
				<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>" class="button">打开媒体库</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmcs-migration' ) ); ?>" class="button">批量迁移</a>
			</div>

			<div class="wpmcs-complete-tips">
				<h3>下一步建议</h3>
				<ul>
					<li><span class="dashicons dashicons-upload"></span> 先上传一张图片验证配置是否正确。</li>
					<li><span class="dashicons dashicons-image-rotate"></span> 如需迁移历史附件，可以使用批量迁移功能。</li>
					<li><span class="dashicons dashicons-chart-pie"></span> 如果开启了日志，建议观察一次完整上传过程。</li>
				</ul>
			</div>
		</div>
		<?php
		return ob_get_clean();
	}

	private function get_provider_help( $provider ) {
		$help = array(
			'qiniu' => '<ol><li>在七牛云控制台创建 AccessKey 和 SecretKey。</li><li>创建或选择一个 Bucket。</li><li>如需自定义上传域名，请填写上传端点。</li></ol>',
			'aliyun_oss' => '<ol><li>在阿里云 OSS 控制台获取 AccessKey ID 和 AccessKey Secret。</li><li>创建或选择 Bucket。</li><li>填写 Endpoint，例如 oss-cn-hangzhou.aliyuncs.com。</li></ol>',
			'tencent_cos' => '<ol><li>在腾讯云 COS 控制台获取 SecretId 和 SecretKey。</li><li>创建或选择 Bucket。</li><li>填写对应 Region，例如 ap-beijing。</li></ol>',
			'upyun' => '<ol><li>在又拍云控制台获取操作员账号和密码。</li><li>创建或选择 Bucket。</li><li>填写 API 接入地址。</li></ol>',
			'dogecloud' => '<ol><li>在多吉云控制台创建 AccessKey 和 SecretKey。</li><li>创建或选择 Bucket。</li><li>填写对应的上传参数。</li></ol>',
			'aws_s3' => '<ol><li>在 AWS 控制台创建 Access Key ID 和 Secret Access Key。</li><li>创建或选择 S3 Bucket。</li><li>填写对应 Region 和 Endpoint。</li></ol>',
		);

		return isset( $help[ $provider ] ) ? $help[ $provider ] : '';
	}

	public function ajax_save_step() {
		check_ajax_referer( 'wpmcs_wizard', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '没有权限执行此操作。' ) );
		}

		$step = isset( $_POST['step'] ) ? sanitize_key( $_POST['step'] ) : '';
		$data = isset( $_POST['data'] ) ? (array) $_POST['data'] : array();
		$settings = wpmcs_get_settings();

		switch ( $step ) {
			case 'provider':
				if ( isset( $data['provider'] ) ) {
					$settings['provider'] = sanitize_key( $data['provider'] );
				}
				break;

			case 'configure':
				$sensitive_fields = array( 'access_key', 'secret_key', 'secret_id', 'password' );
				foreach ( $data as $key => $value ) {
					$key = sanitize_key( $key );
					$value = sanitize_text_field( $value );
					if ( in_array( $key, $sensitive_fields, true ) ) {
						$security_manager = new WPMCS_Security_Manager( $settings );
						$value = $security_manager->encrypt( $value );
					}
					$settings[ $key ] = $value;
				}
				break;

			case 'options':
				$options = array( 'enabled', 'replace_url', 'keep_local_file', 'convert_to_webp', 'auto_rename', 'async_upload', 'enable_cache', 'enable_logging' );
				foreach ( $options as $option ) {
					$settings[ $option ] = isset( $data[ $option ] ) ? '1' : '0';
				}
				$settings['webp_quality'] = isset( $data['webp_quality'] ) ? max( 1, min( 100, absint( $data['webp_quality'] ) ) ) : 82;
				break;
		}

		update_option( 'wpmcs_settings', $settings );

		wp_send_json_success(
			array(
				'message'   => '已保存设置。',
				'next_step' => $this->get_next_step( $step ),
			)
		);
	}

	public function ajax_test_connection() {
		check_ajax_referer( 'wpmcs_wizard', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '没有权限执行此操作。' ) );
		}

		$tester = new WPMCS_Connection_Tester( wpmcs_get_settings() );
		$results = $tester->run_full_test();

		if ( $results['success'] ) {
			wp_send_json_success( $results );
		}

		wp_send_json_error( $results );
	}

	public function ajax_skip_wizard() {
		check_ajax_referer( 'wpmcs_wizard', 'nonce' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '没有权限执行此操作。' ) );
		}

		$this->mark_completed();

		wp_send_json_success(
			array(
				'message'  => '已跳过向导。',
				'redirect' => admin_url( 'admin.php?page=' . WPMCS_Admin_Page::MENU_SLUG ),
			)
		);
	}

	private function get_next_step( $current_step ) {
		$step_keys = array_keys( $this->steps );
		$current_index = array_search( $current_step, $step_keys, true );

		if ( false !== $current_index && isset( $step_keys[ $current_index + 1 ] ) ) {
			return $step_keys[ $current_index + 1 ];
		}

		return 'complete';
	}
}
