<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPMCS_Admin_Page {
	public const MENU_SLUG = 'wpmcs-settings';

	private $option_name = 'wpmcs_settings';

	private $page_slug = 'wpmcs-settings';

	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
	}

	public function register_menu() {
		add_menu_page(
			'多云存储设置', // 页面标题 (Page Title)
			'多云存储',     // 菜单标题 (Menu Title)
			'manage_options',
			$this->page_slug,
			array( $this, 'render_page' ),
			'dashicons-cloud-upload'
		);
	}

	public function register_settings() {
		register_setting(
			'wpmcs_settings_group',
			$this->option_name,
			array( $this, 'sanitize_settings' )
		);

		add_settings_section(
			'wpmcs_general_section',
			'通用设置', // General Settings
			array( $this, 'render_general_section' ),
			$this->page_slug
		);

		$fields = array(
			'enabled'  => '启用云存储', // Enable Cloud Storage
			'provider' => '云服务商',   // Cloud Provider
		);

		foreach ( $fields as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				array( $this, 'render_field' ),
				$this->page_slug,
				'wpmcs_general_section',
				array(
					'key'   => $key,
					'label' => $label,
				)
			);
		}

		add_settings_section(
			'wpmcs_provider_section',
			'服务商设置', // Provider Settings
			array( $this, 'render_provider_section' ),
			$this->page_slug
		);

		$common_fields = array(
			'domain'         => 'CDN 域名',
			'upload_path'    => '上传路径',
			'auto_rename'    => '自动重命名',
			'replace_url'    => '替换 URL',
			'keep_local_file'=> '保留本地文件',
		);

		foreach ( $common_fields as $key => $label ) {
			add_settings_field(
				$key,
				$label,
				array( $this, 'render_field' ),
				$this->page_slug,
				'wpmcs_provider_section',
				array(
					'key'   => $key,
					'label' => $label,
				)
			);
		}

		add_settings_field(
			'convert_to_webp',
			'转换为 WebP',
			array( $this, 'render_field' ),
			$this->page_slug,
			'wpmcs_provider_section',
			array(
				'key'   => 'convert_to_webp',
				'label' => '转换为 WebP',
			)
		);

		add_settings_field(
			'webp_quality',
			'WebP 质量',
			array( $this, 'render_field' ),
			$this->page_slug,
			'wpmcs_provider_section',
			array(
				'key'   => 'webp_quality',
				'label' => 'WebP 质量',
			)
		);
	}

	public function sanitize_settings( $input ) {
		$output   = wpmcs_get_default_settings();
		$settings = is_array( $input ) ? $input : array();

		$output['enabled']         = empty( $settings['enabled'] ) ? '0' : '1';
		$output['provider']        = isset( $settings['provider'] ) ? sanitize_key( $settings['provider'] ) : 'qiniu';
		$output['access_key']      = isset( $settings['access_key'] ) ? trim( sanitize_text_field( $settings['access_key'] ) ) : '';
		$output['secret_key']      = isset( $settings['secret_key'] ) ? trim( sanitize_text_field( $settings['secret_key'] ) ) : '';
		$output['secret_id']       = isset( $settings['secret_id'] ) ? trim( sanitize_text_field( $settings['secret_id'] ) ) : '';
		$output['username']        = isset( $settings['username'] ) ? trim( sanitize_text_field( $settings['username'] ) ) : '';
		$output['password']        = isset( $settings['password'] ) ? trim( sanitize_text_field( $settings['password'] ) ) : '';
		$output['bucket']          = isset( $settings['bucket'] ) ? trim( sanitize_text_field( $settings['bucket'] ) ) : '';
		$output['domain']          = isset( $settings['domain'] ) ? trim( esc_url_raw( $settings['domain'] ) ) : '';
		$output['upload_endpoint'] = isset( $settings['upload_endpoint'] ) ? trim( esc_url_raw( $settings['upload_endpoint'] ) ) : '';
		$output['endpoint']        = isset( $settings['endpoint'] ) ? trim( sanitize_text_field( $settings['endpoint'] ) ) : '';
		$output['region']          = isset( $settings['region'] ) ? sanitize_text_field( $settings['region'] ) : '';
		$output['upload_path']     = isset( $settings['upload_path'] ) ? trim( sanitize_text_field( $settings['upload_path'] ) ) : '';
		$output['auto_rename']     = empty( $settings['auto_rename'] ) ? '0' : '1';
		$output['replace_url']     = empty( $settings['replace_url'] ) ? '0' : '1';
		$output['keep_local_file'] = empty( $settings['keep_local_file'] ) ? '0' : '1';
		$output['convert_to_webp'] = empty( $settings['convert_to_webp'] ) ? '0' : '1';
		$output['webp_quality']    = isset( $settings['webp_quality'] ) ? max( 1, min( 100, (int) $settings['webp_quality'] ) ) : 82;

		return $output;
	}

	public function enqueue_assets( $hook_suffix ) {
		if ( 'toplevel_page_' . $this->page_slug !== $hook_suffix && 'settings_page_' . $this->page_slug !== $hook_suffix ) {
			return;
		}

		wp_enqueue_style(
			'wpmcs-admin',
			WPMCS_PLUGIN_URL . 'assets/css/admin.css',
			array(),
			WPMCS_VERSION
		);

		wp_enqueue_script(
			'wpmcs-admin',
			WPMCS_PLUGIN_URL . 'assets/js/admin.js',
			array(),
			WPMCS_VERSION,
			true
		);

		wp_enqueue_script(
			'wpmcs-provider-switch',
			WPMCS_PLUGIN_URL . 'assets/js/provider-switch.js',
			array(),
			WPMCS_VERSION,
			true
		);

		wp_localize_script(
			'wpmcs-provider-switch',
			'wpmcsProviderData',
			array(
				'pluginUrl' => WPMCS_PLUGIN_URL,
				'nonce'     => wp_create_nonce( 'wpmcs_provider_fields' ),
			)
		);
	}

	public function render_general_section() {
		echo '<p>启用后，上传图片时可以自动同步到云端存储。</p>';
	}

	public function render_provider_section() {
		echo '<p>先选择云服务商，再填写该服务商对应的专属配置项。</p>';
	}

	public function render_field( $args ) {
		$settings = wpmcs_get_settings();
		$key      = $args['key'];
		$value    = isset( $settings[ $key ] ) ? $settings[ $key ] : '';
		$name     = $this->option_name . '[' . $key . ']';
		$provider = isset( $settings['provider'] ) ? $settings['provider'] : 'qiniu';

		switch ( $key ) {
			case 'enabled':
			case 'auto_rename':
			case 'replace_url':
			case 'convert_to_webp':
				printf(
					'<label><input type="checkbox" name="%1$s" value="1" %2$s /> %3$s</label>',
					esc_attr( $name ),
					checked( '1', (string) $value, false ),
                    esc_html( $args['label'] ) // 使用传入的标签
				);
				break;

			case 'webp_quality':
				printf(
					'<input type="number" class="small-text" name="%1$s" value="%2$s" min="1" max="100" step="1" />',
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			case 'provider':
                // 注意：此处假设 WPMCS_Provider_Icons 类存在且支持中文或图标显示
				echo WPMCS_Provider_Icons::render_provider_selector( $provider, $name, 'wpmcs-provider-select' );
				echo '<div id="wpmcs-provider-fields" style="margin-top: 20px;">';
				$this->render_provider_fields( $provider, $settings );
				echo '</div>';
				break;

			case 'secret_key':
			case 'password':
				printf(
					'<input type="password" class="regular-text" name="%1$s" value="%2$s" autocomplete="off" />',
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;

			default:
				printf(
					'<input type="text" class="regular-text" name="%1$s" value="%2$s" />',
					esc_attr( $name ),
					esc_attr( $value )
				);
				break;
		}

		$this->render_field_description( $key );
	}

	private function render_provider_fields( $provider, $settings ) {
		$this->render_provider_fields_html( $provider, $settings );
	}

	public function render_provider_fields_html( $provider, $settings ) {
		$fields_config = $this->get_provider_fields_config();

		if ( ! isset( $fields_config[ $provider ] ) ) {
			echo '<p class="description">当前服务商无专属设置项。</p>';
			return;
		}

		echo '<table class="form-table">';

		foreach ( $fields_config[ $provider ] as $field_key => $field_config ) {
			$value = isset( $settings[ $field_key ] ) ? $settings[ $field_key ] : '';
			$name  = $this->option_name . '[' . $field_key . ']';

			echo '<tr>';
			echo '<th scope="row">' . esc_html( $field_config['label'] ) . '</th>';
			echo '<td>';

			$input_type = isset( $field_config['type'] ) ? $field_config['type'] : 'text';

			if ( 'password' === $input_type ) {
				printf(
					'<input type="password" class="regular-text" name="%1$s" value="%2$s" autocomplete="off" />',
					esc_attr( $name ),
					esc_attr( $value )
				);
			} elseif ( 'select' === $input_type && isset( $field_config['options'] ) ) {
				echo '<select name="' . esc_attr( $name ) . '" style="min-width: 250px;">';
				foreach ( $field_config['options'] as $opt_value => $opt_label ) {
					echo '<option value="' . esc_attr( $opt_value ) . '" ' . selected( $value, $opt_value, false ) . '>' . esc_html( $opt_label ) . '</option>';
				}
				echo '</select>';
			} else {
				printf(
					'<input type="text" class="regular-text" name="%1$s" value="%2$s" />',
					esc_attr( $name ),
					esc_attr( $value )
				);
			}

			if ( isset( $field_config['description'] ) ) {
				echo '<p class="description">' . esc_html( $field_config['description'] ) . '</p>';
			}

			echo '</td>';
			echo '</tr>';
		}

		echo '</table>';
	}

	private function get_provider_fields_config() {
		return array(
			'qiniu' => array(
				'access_key' => array(
					'label'       => 'Access Key',
					'type'        => 'text',
					'description' => '七牛云 Access Key',
				),
				'secret_key' => array(
					'label'       => 'Secret Key',
					'type'        => 'password',
					'description' => '七牛云 Secret Key',
				),
				'bucket' => array(
					'label'       => '存储空间 (Bucket)',
					'type'        => 'text',
					'description' => '七牛云存储空间名称',
				),
				'upload_endpoint' => array(
					'label'       => '上传域名',
					'type'        => 'text',
					'description' => '七牛云上传域名，例如 https://upload.qiniup.com',
				),
			),
			'aliyun_oss' => array(
				'access_key' => array(
					'label'       => 'AccessKey ID',
					'type'        => 'text',
					'description' => '阿里云 AccessKey ID',
				),
				'secret_key' => array(
					'label'       => 'AccessKey Secret',
					'type'        => 'password',
					'description' => '阿里云 AccessKey Secret',
				),
				'bucket' => array(
					'label'       => '存储空间 (Bucket)',
					'type'        => 'text',
					'description' => 'OSS 存储空间名称',
				),
				'endpoint' => array(
					'label'       => 'Endpoint',
					'type'        => 'text',
					'description' => 'OSS 访问域名，例如 oss-cn-hangzhou.aliyuncs.com',
				),
			),
			'tencent_cos' => array(
				'secret_id' => array(
					'label'       => 'SecretId',
					'type'        => 'text',
					'description' => '腾讯云 SecretId',
				),
				'secret_key' => array(
					'label'       => 'SecretKey',
					'type'        => 'password',
					'description' => '腾讯云 SecretKey',
				),
				'bucket' => array(
					'label'       => '存储空间 (Bucket)',
					'type'        => 'text',
					'description' => 'COS 存储空间名称',
				),
				'region' => array(
					'label'       => '所属地域',
					'type'        => 'select',
					'description' => '腾讯云存储桶所在地域',
					'options'     => $this->get_tencent_region_options(),
				),
			),
			'upyun' => array(
				'username' => array(
					'label'       => '操作员账号',
					'type'        => 'text',
					'description' => '又拍云操作员账号',
				),
				'password' => array(
					'label'       => '操作员密码',
					'type'        => 'password',
					'description' => '又拍云操作员密码',
				),
				'bucket' => array(
					'label'       => '服务名称',
					'type'        => 'text',
					'description' => '又拍云服务名称 (Bucket)',
				),
				'endpoint' => array(
					'label'       => 'API 接口地址',
					'type'        => 'text',
					'description' => '又拍云 API 接口地址，例如 v0.api.upyun.com',
				),
			),
			'dogecloud' => array(
				'access_key' => array(
					'label'       => 'Access Key',
					'type'        => 'text',
					'description' => '多吉云 Access Key',
				),
				'secret_key' => array(
					'label'       => 'Secret Key',
					'type'        => 'password',
					'description' => '多吉云 Secret Key',
				),
				'bucket' => array(
					'label'       => '存储空间 (Bucket)',
					'type'        => 'text',
					'description' => '多吉云存储空间名称',
				),
			),
			'aws_s3' => array(
				'access_key' => array(
					'label'       => 'Access Key ID',
					'type'        => 'text',
					'description' => 'AWS Access Key ID',
				),
				'secret_key' => array(
					'label'       => 'Secret Access Key',
					'type'        => 'password',
					'description' => 'AWS Secret Access Key',
				),
				'bucket' => array(
					'label'       => '存储空间 (Bucket)',
					'type'        => 'text',
					'description' => 'S3 存储空间名称',
				),
				'region' => array(
					'label'       => '区域 (Region)',
					'type'        => 'select',
					'description' => 'AWS 存储桶所在区域',
					'options'     => $this->get_aws_region_options(),
				),
			),
		);
	}

	private function get_tencent_region_options() {
		return array(
			'ap-beijing'    => '华北地区 (北京)',
			'ap-shanghai'   => '华东地区 (上海)',
			'ap-guangzhou'  => '华南地区 (广州)',
			'ap-chengdu'    => '西南地区 (成都)',
			'ap-chongqing'  => '西南地区 (重庆)',
			'ap-hongkong'   => '港澳台地区 (中国香港)',
			'ap-singapore'  => '亚太东南 (新加坡)',
		);
	}

	private function get_aws_region_options() {
		return array(
			'us-east-1'      => '美国东部 (弗吉尼亚北部)',
			'us-east-2'      => '美国东部 (俄亥俄州)',
			'us-west-1'      => '美国西部 (北加州)',
			'us-west-2'      => '美国西部 (俄勒冈州)',
			'eu-west-1'      => '欧洲 (爱尔兰)',
			'eu-west-2'      => '欧洲 (伦敦)',
			'eu-west-3'      => '欧洲 (巴黎)',
			'eu-central-1'   => '欧洲 (法兰克福)',
			'ap-northeast-1' => '亚太东北 (东京)',
			'ap-northeast-2' => '亚太东北 (首尔)',
			'ap-southeast-1' => '亚太东南 (新加坡)',
			'ap-southeast-2' => '亚太东南 (悉尼)',
			'ap-south-1'     => '亚太南部 (孟买)',
			'sa-east-1'      => '南美洲 (圣保罗)',
			'ca-central-1'   => '加拿大 (中部)',
		);
	}

	public function render_page() {
		WPMCS_Provider_Icons::output_icon_css();
		$current_settings = wpmcs_get_settings();
		$keep_local_file  = empty( $current_settings['keep_local_file'] ) ? '0' : '1';
		?>
		<div class="wrap wpmcs-settings">
			<h1>多云存储设置</h1>

			<div class="wpmcs-panel">
				<h2>连接测试</h2>
				<p class="description">保存前请测试服务商凭证及 endpoint 连通性。</p>
				<p>
					<button type="button" id="wpmcs-test-connection" class="button button-secondary">
						<span class="dashicons dashicons-admin-site" style="margin-top: 3px; margin-right: 5px;"></span>
						测试连接
					</button>
					<span id="wpmcs-test-loading" style="display: none; margin-left: 10px;">
						<span class="spinner is-active" style="float: none; margin: 0;"></span>
						测试中...
					</span>
				</p>
				<div id="wpmcs-test-result" style="display: none; margin-top: 15px;"></div>
			</div>

			<div class="wpmcs-panel">
				<h2>通用设置</h2>
				<form method="post" action="options.php">
					<?php
					settings_fields( 'wpmcs_settings_group' );
					do_settings_sections( $this->page_slug );
					?>
					<table class="form-table" style="margin-top: 24px;">
						<tr>
							<th scope="row">保留本地文件</th>
							<td>
								<label>
									<input type="checkbox" name="wpmcs_settings[keep_local_file]" value="1" <?php checked( '1', $keep_local_file ); ?> />
									上传后在服务器保留本地副本
								</label>
								<p class="description">如果启用，上传到云端后文件仍会保留在本地服务器。</p>
							</td>
						</tr>
					</table>
					<?php submit_button( '保存设置' ); ?>
				</form>
			</div>
		</div>

		<script type="text/javascript">
		jQuery(document).ready(function($) {
			window.wpmcsNonce = '<?php echo wp_create_nonce( 'wpmcs_test_connection' ); ?>';
			window.wpmcsProviderNonce = '<?php echo wp_create_nonce( 'wpmcs_provider_fields' ); ?>';
		});
		</script>
		<?php
	}

	private function render_field_description( $key ) {
		$descriptions = array(
			'enabled'         => '启用或禁用云存储上传功能。',
			'provider'        => '选择云存储服务商。',
			'domain'          => '用于生成文件链接的公共 CDN 域名。',
			'upload_path'     => '存储空间内的可选子目录路径。',
			'auto_rename'     => '上传前自动重命名文件以避免冲突。',
			'replace_url'     => '将内容中的本地文件 URL 替换为云端 URL。',
			'keep_local_file' => '上传到云端后在本地保留副本。',
			'convert_to_webp' => '上传前将 JPEG 和 PNG 图片转换为 WebP 格式。如果转换失败则回退到原始格式。',
			'webp_quality'    => '数值越高画质越好但文件越大。推荐范围：70-85。',
		);

		if ( isset( $descriptions[ $key ] ) ) {
			echo '<p class="description">' . esc_html( $descriptions[ $key ] ) . '</p>';
		}
	}
}