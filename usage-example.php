<?php
/**
 * WordPress 多云存储插件 - 使用示例
 * 
 * 这个文件展示了如何测试和使用上传拦截功能
 * 请确保插件已激活并配置了七牛云存储
 */

// 防止直接访问
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 测试上传拦截功能
function wpmcs_test_upload_interceptor() {
	// 获取插件实例
	$plugin = WPMCS_Plugin::instance();
	
	// 获取上传拦截器
	$interceptor = $plugin->get_upload_interceptor();
	
	if ( ! $interceptor ) {
		echo "<p style='color: red;'>错误: 上传拦截器未初始化，请检查插件配置。</p>";
		return;
	}
	
	// 获取云上传器
	$uploader = $plugin->get_cloud_uploader();
	
	if ( ! $uploader ) {
		echo "<p style='color: red;'>错误: 云上传器未初始化，请检查云存储配置。</p>";
		return;
	}
	
	echo "<h2>WordPress 多云存储插件 - 功能测试</h2>";
	echo "<h3>1. 插件状态检查</h3>";
	
	// 检查插件配置
	$settings = wpmcs_get_settings();
	echo "<p><strong>插件状态:</strong> " . ( $settings['enabled'] ? '已启用' : '已禁用' ) . "</p>";
	echo "<p><strong>云存储服务:</strong> " . $settings['provider'] . "</p>";
	echo "<p><strong>Bucket:</strong> " . $settings['bucket'] . "</p>";
	echo "<p><strong>域名:</strong> " . $settings['domain'] . "</p>";
	echo "<p><strong>URL 替换:</strong> " . ( $settings['replace_url'] ? '已启用' : '已禁用' ) . "</p>";
	echo "<p><strong>自动重命名:</strong> " . ( $settings['auto_rename'] ? '已启用' : '已禁用' ) . "</p>";
	
	echo "<h3>2. 存储驱动信息</h3>";
	$storage_info = $uploader->get_storage_info();
	echo "<p><strong>存储服务:</strong> " . $storage_info['provider'] . "</p>";
	echo "<p><strong>版本:</strong> " . $storage_info['version'] . "</p>";
	echo "<p><strong>支持功能:</strong> " . implode( ', ', $storage_info['features'] ) . "</p>";
	
	echo "<h3>3. 附件统计</h3>";
	$stats = WPMCS_Attachment_Manager::get_cloud_stats();
	echo "<p><strong>总附件数:</strong> " . $stats['total_attachments'] . "</p>";
	echo "<p><strong>云端附件数:</strong> " . $stats['cloud_attachments'] . "</p>";
	echo "<p><strong>错误附件数:</strong> " . $stats['error_attachments'] . "</p>";
	echo "<p><strong>云端比例:</strong> " . $stats['cloud_percentage'] . "%</p>";
	
	echo "<h3>4. 测试功能</h3>";
	
	// 测试文件名生成
	test_filename_generation();
	
	// 测试附件管理
	test_attachment_management();
	
	echo "<h3>5. 使用说明</h3>";
	echo "<p><strong>上传文件:</strong> 在 WordPress 媒体库中上传文件时，插件会自动拦截上传流程，将文件上传到云端存储。</p>";
	echo "<p><strong>URL 替换:</strong> 上传成功后，插件会自动将本地 URL 替换为云端 URL。</p>";
	echo "<p><strong>删除同步:</strong> 删除附件时，云端文件也会被同步删除。</p>";
	echo "<p><strong>缩略图支持:</strong> 支持所有 WordPress 生成的缩略图尺寸。</p>";
	echo "<p><strong>编辑器集成:</strong> 在编辑器中插入图片时，也会使用云端 URL。</p>";
}

// 测试文件名生成功能
function test_filename_generation() {
	echo "<h4>文件名生成测试</h4>";
	
	$test_files = array(
		"test-image.jpg",
		"my document.pdf", 
		"photo with spaces.png",
		"中文文件名测试.txt"
	);
	
	foreach ( $test_files as $filename ) {
		$unique_name = wpmcs_generate_unique_filename( $filename );
		echo "<p><strong>原始文件名:</strong> {$filename} → <strong>生成文件名:</strong> {$unique_name}</p>";
	}
}

// 测试附件管理功能
function test_attachment_management() {
	echo "<h4>附件管理测试</h4>";
	
	// 获取最近的一个附件用于测试
	$recent_attachments = get_posts( array(
		'post_type' => 'attachment',
		'numberposts' => 1,
		'orderby' => 'date',
		'order' => 'DESC'
	) );
	
	if ( empty( $recent_attachments ) ) {
		echo "<p style='color: orange;'>警告: 没有找到附件用于测试。</p>";
		return;
	}
	
	$attachment = $recent_attachments[0];
	echo "<p><strong>测试附件:</strong> {$attachment->post_title} (ID: {$attachment->ID})</p>";
	
	// 检查云端元数据
	$has_cloud = WPMCS_Attachment_Manager::has_cloud_meta( $attachment->ID );
	echo "<p><strong>云端状态:</strong> " . ( $has_cloud ? '已上传到云端' : '未上传到云端' ) . "</p>";
	
	if ( $has_cloud ) {
		$cloud_meta = WPMCS_Attachment_Manager::get_cloud_meta( $attachment->ID );
		echo "<p><strong>云存储服务:</strong> " . $cloud_meta['provider'] . "</p>";
		echo "<p><strong>云端 Key:</strong> " . $cloud_meta['key'] . "</p>";
		echo "<p><strong>云端 URL:</strong> <a href=\"" . $cloud_meta['url'] . "\" target=\"_blank\">" . $cloud_meta['url'] . "</a></p>";
	}
	
	// 检查错误信息
	$error = WPMCS_Attachment_Manager::get_upload_error( $attachment->ID );
	if ( $error ) {
		echo "<p style='color: red;'><strong>上传错误:</strong> {$error}</p>";
	}
}

// 仅在后端显示测试信息
if ( is_admin() ) {
	add_action( 'admin_notices', 'wpmcs_test_upload_interceptor' );
}