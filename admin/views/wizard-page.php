<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$wizard = new WPMCS_Setup_Wizard();
$steps = $wizard->get_steps();
$current_step = $wizard->get_current_step();
$step_keys = array_keys( $steps );
$current_index = array_search( $current_step, $step_keys, true );
$settings = wpmcs_get_settings();
$current_provider = isset( $settings['provider'] ) ? $settings['provider'] : 'qiniu';
$provider_name = WPMCS_Provider_Icons::get_name( $current_provider );
$provider_icon = WPMCS_Provider_Icons::get_icon_url( $current_provider );
$provider_website = WPMCS_Provider_Icons::get_website( $current_provider );
$is_enabled = ! empty( $settings['enabled'] ) && '1' === (string) $settings['enabled'];
$is_replace_url = ! empty( $settings['replace_url'] ) && '1' === (string) $settings['replace_url'];
?>
<div class="wrap wpmcs-wizard-wrap" data-step="<?php echo esc_attr( $current_step ); ?>" data-provider="<?php echo esc_attr( $current_provider ); ?>">
	<div class="wpmcs-wizard-shell">
		<header class="wpmcs-wizard-hero">
			<div class="wpmcs-wizard-hero-copy">
				<span class="wpmcs-wizard-kicker">首次配置向导</span>
				<h1>把 WordPress 媒体库接入云存储</h1>
				<p>
					按步骤选择云服务商、填写连接信息、测试连通性，最后开启图片自动上传。
					完成后，新上传的媒体会自动进入你配置好的云端存储。
				</p>

				<div class="wpmcs-wizard-highlights">
					<div class="wpmcs-highlight-item">
						<span class="dashicons dashicons-cloud"></span>
						<strong>支持多云厂商</strong>
						<em>七牛云、阿里云 OSS、腾讯云 COS、又拍云、多吉云、AWS S3</em>
					</div>
					<div class="wpmcs-highlight-item">
						<span class="dashicons dashicons-admin-links"></span>
						<strong>上传后自动同步</strong>
						<em>新图片上传后自动推送到云端，减少手动迁移</em>
					</div>
					<div class="wpmcs-highlight-item">
						<span class="dashicons dashicons-image-rotate"></span>
						<strong>可选 URL 替换</strong>
						<em>开启后，文章和媒体链接会切换为云端地址</em>
					</div>
				</div>
			</div>

			<div class="wpmcs-wizard-hero-card">
				<div class="wpmcs-wizard-hero-card-title">当前状态</div>
				<div class="wpmcs-wizard-provider-summary">
					<?php if ( $provider_icon ) : ?>
						<img src="<?php echo esc_url( $provider_icon ); ?>" alt="<?php echo esc_attr( $provider_name ); ?>">
					<?php endif; ?>
					<div>
						<strong><?php echo esc_html( $provider_name ); ?></strong>
						<span><?php echo $is_enabled ? '已启用云存储' : '尚未启用云存储'; ?></span>
					</div>
				</div>

				<ul class="wpmcs-wizard-status-list">
					<li>
						<span>URL 替换</span>
						<strong><?php echo $is_replace_url ? '已开启' : '未开启'; ?></strong>
					</li>
					<li>
						<span>Bucket</span>
						<strong><?php echo ! empty( $settings['bucket'] ) ? esc_html( $settings['bucket'] ) : '未填写'; ?></strong>
					</li>
					<li>
						<span>Region / Endpoint</span>
						<strong><?php echo ! empty( $settings['region'] ) ? esc_html( $settings['region'] ) : ( ! empty( $settings['endpoint'] ) ? esc_html( $settings['endpoint'] ) : '未填写' ); ?></strong>
					</li>
				</ul>

				<a class="wpmcs-wizard-provider-link" href="<?php echo esc_url( $provider_website ); ?>" target="_blank" rel="noreferrer">
					查看 <?php echo esc_html( $provider_name ); ?> 官方文档
				</a>
			</div>
		</header>

		<div class="wpmcs-wizard-layout">
			<main class="wpmcs-wizard-main">
				<div class="wpmcs-wizard-container">
					<div class="wpmcs-wizard-steps">
						<?php foreach ( $steps as $key => $step ) : ?>
							<?php
							$step_index = array_search( $key, $step_keys, true );
							$is_active = $key === $current_step;
							$is_completed = false !== $current_index && $step_index < $current_index;
							?>
							<div class="wpmcs-step-item <?php echo $is_active ? 'active' : ''; ?> <?php echo $is_completed ? 'completed' : ''; ?>">
								<div class="wpmcs-step-number">
									<?php if ( $is_completed ) : ?>
										<span class="dashicons dashicons-yes-alt"></span>
									<?php else : ?>
										<span><?php echo (int) $step_index + 1; ?></span>
									<?php endif; ?>
								</div>
								<div class="wpmcs-step-info">
									<span class="wpmcs-step-name"><?php echo esc_html( $step['name'] ); ?></span>
								</div>
							</div>
						<?php endforeach; ?>
					</div>

					<div class="wpmcs-wizard-content">
						<div class="wpmcs-wizard-message notice" style="display:none"></div>

						<form id="wpmcs-wizard-form" method="post">
							<div class="wpmcs-step-content" data-step="<?php echo esc_attr( $current_step ); ?>">
								<?php echo $wizard->render_step_content( $current_step ); ?>
							</div>

							<div class="wpmcs-wizard-nav">
								<?php if ( $current_step !== 'welcome' && $current_step !== 'complete' ) : ?>
									<button type="button" class="button wpmcs-wizard-prev">
										<span class="dashicons dashicons-arrow-left-alt2"></span> 上一步
									</button>
								<?php else : ?>
									<span></span>
								<?php endif; ?>

								<?php if ( $current_step === 'provider' ) : ?>
									<button type="button" class="button button-primary wpmcs-wizard-next">
										下一步 <span class="dashicons dashicons-arrow-right-alt2"></span>
									</button>
								<?php elseif ( $current_step === 'configure' ) : ?>
									<button type="button" class="button button-primary wpmcs-wizard-next">
										下一步 <span class="dashicons dashicons-arrow-right-alt2"></span>
									</button>
								<?php elseif ( $current_step === 'test' ) : ?>
									<button type="button" class="button button-primary wpmcs-wizard-next" id="wpmcs-wizard-next-after-test" disabled>
										下一步 <span class="dashicons dashicons-arrow-right-alt2"></span>
									</button>
								<?php elseif ( $current_step === 'options' ) : ?>
									<button type="button" class="button button-primary wpmcs-wizard-complete">
										完成向导 <span class="dashicons dashicons-yes"></span>
									</button>
								<?php endif; ?>
							</div>
						</form>
					</div>
				</div>

				<div class="wpmcs-wizard-footer">
					<?php if ( $current_step !== 'complete' ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . WPMCS_Admin_Page::MENU_SLUG ) ); ?>" class="wpmcs-wizard-skip-link">
							跳过向导，直接进入后台设置
						</a>
					<?php endif; ?>
				</div>
			</main>

			<aside class="wpmcs-wizard-aside">
				<div class="wpmcs-aside-card">
					<h3>你会完成什么</h3>
					<ol class="wpmcs-wizard-checklist">
						<li>选择最适合你的云厂商。</li>
						<li>填写 Bucket、密钥和 Region / Endpoint。</li>
						<li>执行测试连接，确认上传链路可用。</li>
						<li>按需开启 URL 替换和自动重命名。</li>
					</ol>
				</div>

				<div class="wpmcs-aside-card">
					<h3>配置建议</h3>
					<ul class="wpmcs-side-notes">
						<li>首次配置建议先完成测试连接，再去上传图片。</li>
						<li>腾讯云 COS 私有桶在“文件访问”测试里返回 403 是正常的。</li>
						<li>如果只想先试用，可先保持 URL 替换关闭。</li>
					</ul>
				</div>

				<div class="wpmcs-aside-card">
					<h3>快捷入口</h3>
					<div class="wpmcs-side-links">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . WPMCS_Admin_Page::MENU_SLUG ) ); ?>">
							<span class="dashicons dashicons-admin-generic"></span>
							主设置页
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=wpmcs-quick-setup' ) ); ?>">
							<span class="dashicons dashicons-schedule"></span>
							快速配置
						</a>
						<a href="<?php echo esc_url( admin_url( 'upload.php' ) ); ?>">
							<span class="dashicons dashicons-upload"></span>
							媒体库
						</a>
					</div>
				</div>
			</aside>
		</div>
	</div>
</div>
