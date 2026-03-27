<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WP Multi Cloud Storage 自动加载器
 *
 * 实现按需加载类，减少内存占用和文件 I/O
 */
class WPMCS_Autoloader {

	/**
	 * 注册自动加载器
	 */
	public static function register() {
		spl_autoload_register( array( __CLASS__, 'autoload' ) );
	}

	/**
	 * 自动加载方法
	 *
	 * @param string $class 完整类名
	 */
	public static function autoload( $class ) {
		// 类名映射到文件路径
		$mapping = array(
			// 核心类
			'WPMCS_Encryption'         => 'class-wpmcs-encryption.php',
			'WPMCS_Cache_Manager'     => 'class-wpmcs-cache-manager.php',
			'WPMCS_Temp_File_Manager' => 'class-wpmcs-temp-file-manager.php',
			'WPMCS_Logger'             => 'class-wpmcs-logger.php',
			'WPMCS_Security_Manager'   => 'class-wpmcs-security-manager.php',
			'WPMCS_Debug_Manager'      => 'class-wpmcs-debug-manager.php',
			'WPMCS_Storage_Stats'     => 'class-wpmcs-storage-stats.php',
			'WPMCS_WebP_Converter'    => 'class-wpmcs-webp-converter.php',
			'WPMCS_REST_API'           => 'class-wpmcs-rest-api.php',
			'WPMCS_Webhook_Manager'    => 'class-wpmcs-webhook-manager.php',
			'WPMCS_Batch_Operations'   => 'class-wpmcs-batch-operations.php',
			'WPMCS_Quick_Setup'        => 'class-wpmcs-quick-setup.php',
			'WPMCS_Setup_Wizard'       => 'class-wpmcs-setup-wizard.php',
			'WPMCS_Provider_Icons'     => 'class-wpmcs-provider-icons.php',

			// 上传相关
			'Cloud_Uploader'              => 'class-cloud-uploader.php',
			'WPMCS_Upload_Manager'        => 'class-wpmcs-upload-manager.php',
			'WPMCS_Upload_Interceptor'   => 'class-wpmcs-upload-interceptor.php',
			'WPMCS_Async_Queue'          => 'class-wpmcs-async-queue.php',
			'WPMCS_Attachment_Manager'    => 'class-wpmcs-attachment-manager.php',
			'WPMCS_Connection_Tester'     => 'class-wpmcs-connection-tester.php',

			// Adapter 类
			'WPMCS_Cloud_Adapter'           => 'class-wpmcs-cloud-adapter.php',
			'WPMCS_Qiniu_Adapter'           => 'class-wpmcs-qiniu-adapter.php',
			'WPMCS_Aliyun_OSS_Adapter'      => 'class-wpmcs-aliyun-oss-adapter.php',
			'WPMCS_Tencent_COS_Adapter'      => 'class-wpmcs-tencent-cos-adapter.php',
			'WPMCS_Upyun_Adapter'            => 'class-wpmcs-upyun-adapter.php',
			'WPMCS_Dogecloud_Adapter'        => 'class-wpmcs-dogecloud-adapter.php',
			'WPMCS_AWS_S3_Adapter'           => 'class-wpmcs-aws-s3-adapter.php',

			// Storage Driver 类
			'Qiniu_Storage'               => 'class-qiniu-storage.php',
			'Aliyun_OSS_Storage'           => 'class-aliyun-oss-storage.php',
			'Tencent_COS_Storage'          => 'class-tencent-cos-storage.php',
			'Upyun_Storage'                => 'class-upyun-storage.php',
			'Dogecloud_Storage'            => 'class-dogecloud-storage.php',
			'AWS_S3_Storage'              => 'class-aws-s3-storage.php',

			// 管理后台类
			'WPMCS_Admin_Page'             => 'class-wpmcs-admin-page.php',
			'WPMCS_Media_Library_Enhancer' => 'class-wpmcs-media-library-enhancer.php',
			'WPMCS_Migration_Manager'       => 'class-wpmcs-migration-manager.php',
			'WPMCS_Logs_Page'              => 'class-wpmcs-logs-page.php',
			'WPMCS_Security_Page'           => 'class-wpmcs-security-page.php',
		);

		// 检查是否在映射中
		if ( ! isset( $mapping[ $class ] ) ) {
			return;
		}

		// 构建文件路径
		$file = WPMCS_PLUGIN_DIR . 'includes/' . $mapping[ $class ];

		// 检查文件是否存在
		if ( ! file_exists( $file ) ) {
			// 尝试从 admin 目录加载
			$file = WPMCS_PLUGIN_DIR . 'admin/' . $mapping[ $class ];
		}

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
}

// 注册自动加载器
WPMCS_Autoloader::register();
