<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$quick_setup = new WPMCS_Quick_Setup();
$presets = $quick_setup->get_all_presets();
$recommended = $quick_setup->get_recommended_preset();
$providers = WPMCS_Provider_Icons::get_all_providers();
$current_settings = wpmcs_get_settings();
$current_provider = isset( $current_settings['provider'] ) ? $current_settings['provider'] : 'qiniu';
?>
<div class="wrap wpmcs-wizard-wrap">
	<div class="wpmcs-wizard-container">
		<div class="wpmcs-quick-header">
			<h1>快速设置</h1>
			<p>选择推荐方案或直接填写云服务商配置，快速完成基础接入。</p>
		</div>

		<div class="wpmcs-quick-section">
			<h2>
				<span class="dashicons dashicons-admin-settings"></span>
				推荐预设
			</h2>

			<div class="wpmcs-presets-grid">
				<?php foreach ( $presets as $preset_id => $preset ) : ?>
					<div class="wpmcs-preset-card <?php echo $recommended === $preset_id ? 'recommended' : ''; ?>" data-preset="<?php echo esc_attr( $preset_id ); ?>">
						<?php if ( $recommended === $preset_id ) : ?>
							<span class="wpmcs-preset-badge">推荐</span>
						<?php endif; ?>

						<div class="wpmcs-preset-icon">
							<span class="dashicons <?php echo esc_attr( $preset['icon'] ); ?>"></span>
						</div>

						<h3><?php echo esc_html( $preset['name'] ); ?></h3>
						<p><?php echo esc_html( $preset['description'] ); ?></p>

						<button type="button" class="button button-primary wpmcs-apply-preset">
							应用预设
						</button>
					</div>
				<?php endforeach; ?>
			</div>
		</div>

		<div class="wpmcs-quick-section">
			<h2>
				<span class="dashicons dashicons-cloud"></span>
				云服务商配置
			</h2>

			<div class="wpmcs-quick-provider-form">
				<div class="wpmcs-form-row">
					<label for="wpmcs-quick-provider">云服务商</label>
					<div class="wpmcs-provider-preview" id="wpmcs-quick-provider-preview">
						<?php echo WPMCS_Provider_Icons::render_icon( $current_provider, 48 ); ?>
					</div>
					<select id="wpmcs-quick-provider" name="provider">
						<?php foreach ( $providers as $key => $provider ) : ?>
							<option value="<?php echo esc_attr( $key ); ?>" data-icon="<?php echo esc_url( WPMCS_Provider_Icons::get_icon_url( $key ) ); ?>" <?php selected( $current_provider, $key ); ?>>
								<?php echo esc_html( $provider['name'] ); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div id="wpmcs-quick-provider-fields">
				</div>

				<div class="wpmcs-form-actions">
					<button type="button" class="button button-primary" id="wpmcs-quick-setup-submit">
						<span class="dashicons dashicons-yes"></span>
						保存并测试
					</button>

					<span class="spinner" id="wpmcs-quick-setup-spinner"></span>
				</div>

				<div id="wpmcs-quick-setup-result" style="display: none;"></div>
			</div>
		</div>

		<div class="wpmcs-quick-section">
			<h2>
				<span class="dashicons dashicons-info"></span>
				当前状态
			</h2>

			<div class="wpmcs-status-grid">
				<div class="wpmcs-status-item <?php echo ! empty( $current_settings['enabled'] ) ? 'active' : ''; ?>">
					<span class="dashicons <?php echo ! empty( $current_settings['enabled'] ) ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
					<span>启用状态</span>
					<strong><?php echo ! empty( $current_settings['enabled'] ) ? '已启用' : '未启用'; ?></strong>
				</div>

				<div class="wpmcs-status-item <?php echo ! empty( $current_settings['replace_url'] ) ? 'active' : ''; ?>">
					<span class="dashicons <?php echo ! empty( $current_settings['replace_url'] ) ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
					<span>URL 替换</span>
					<strong><?php echo ! empty( $current_settings['replace_url'] ) ? '已开启' : '未开启'; ?></strong>
				</div>

				<div class="wpmcs-status-item <?php echo ! empty( $current_settings['bucket'] ) ? 'active' : ''; ?>">
					<span class="dashicons <?php echo ! empty( $current_settings['bucket'] ) ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
					<span>Bucket</span>
					<strong><?php echo ! empty( $current_settings['bucket'] ) ? esc_html( $current_settings['bucket'] ) : '未配置'; ?></strong>
				</div>

				<div class="wpmcs-status-item <?php echo ! empty( $current_settings['domain'] ) ? 'active' : ''; ?>">
					<span class="dashicons <?php echo ! empty( $current_settings['domain'] ) ? 'dashicons-yes-alt' : 'dashicons-warning'; ?>"></span>
					<span>访问域名</span>
					<strong><?php echo ! empty( $current_settings['domain'] ) ? esc_html( $current_settings['domain'] ) : '未配置'; ?></strong>
				</div>
			</div>
		</div>

		<div class="wpmcs-quick-footer">
			<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . WPMCS_Admin_Page::MENU_SLUG ) ); ?>">
				<span class="dashicons dashicons-admin-generic"></span>
				进入完整设置
			</a>

			<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmcs-setup-wizard' ) ); ?>">
				<span class="dashicons dashicons-admin-customizer"></span>
				打开配置向导
			</a>
		</div>
	</div>
</div>
