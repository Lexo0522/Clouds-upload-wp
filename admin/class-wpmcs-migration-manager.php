<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 批量迁移管理器
 * 
 * 负责将本地附件批量迁移到云存储
 */
class WPMCS_Migration_Manager {
	
	/**
	 * @var array
	 */
	private $settings;
	
	/**
	 * @var Cloud_Storage_Interface|null
	 */
	private $storage;

	/**
	 * @var WPMCS_Logger|null
	 */
	private $logger;
	
	/**
	 * 每批处理的数量
	 */
	private $batch_size = 10;
	
	/**
	 * 迁移选项名称
	 */
	private $option_name = 'wpmcs_migration_status';
	
	public function __construct( array $settings ) {
		$this->settings = $settings;
		$this->logger = class_exists( 'WPMCS_Logger' ) ? new WPMCS_Logger( $settings ) : null;
	}
	
	/**
	 * 注册钩子
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'wp_ajax_wpmcs_get_migration_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_wpmcs_start_migration', array( $this, 'ajax_start_migration' ) );
		add_action( 'wp_ajax_wpmcs_process_migration_batch', array( $this, 'ajax_process_batch' ) );
		add_action( 'wp_ajax_wpmcs_get_migration_progress', array( $this, 'ajax_get_progress' ) );
		add_action( 'wp_ajax_wpmcs_retry_failed', array( $this, 'ajax_retry_failed' ) );
		add_action( 'wp_ajax_wpmcs_cancel_migration', array( $this, 'ajax_cancel_migration' ) );
	}
	
	/**
	 * 添加菜单页面
	 */
	public function add_menu_page() {
		add_submenu_page(
			WPMCS_Admin_Page::MENU_SLUG,
			'批量迁移',
			'云存储迁移',
			'manage_options',
			'wpmcs-migration',
			array( $this, 'render_page' )
		);
	}
	
	/**
	 * 渲染迁移页面
	 */
	public function render_page() {
		// 加载资源
		wp_enqueue_style(
			'wpmcs-migration',
			WPMCS_PLUGIN_URL . 'assets/css/migration.css',
			array(),
			WPMCS_VERSION
		);
		
		wp_enqueue_script(
			'wpmcs-migration',
			WPMCS_PLUGIN_URL . 'assets/js/migration.js',
			array(),
			WPMCS_VERSION,
			true
		);
		
		wp_localize_script( 'wpmcs-migration', 'wpmcsMigration', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wpmcs_migration' ),
			'text_starting' => '正在启动迁移...',
			'text_processing' => '正在处理...',
			'text_completed' => '迁移完成',
			'text_failed' => '迁移失败',
			'text_cancelled' => '已取消',
		) );
		
		include WPMCS_PLUGIN_DIR . 'admin/views/migration-page.php';
	}
	
	/**
	 * 获取迁移统计信息
	 */
	public function get_migration_stats() {
		global $wpdb;
		
		// 总附件数
		$total_attachments = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'"
		);
		
		// 已上传到云端的附件数
		if ( class_exists( 'WPMCS_Attachment_Manager' ) ) {
			$uploaded_attachments = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM {$wpdb->posts}
				WHERE post_type = 'attachment'
				AND " . WPMCS_Attachment_Manager::get_cloud_detection_sql()
			);
		} else {
			$uploaded_attachments = (int) $wpdb->get_var(
				"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
				WHERE meta_key = '_wpmcs_cloud_meta' AND meta_value != ''"
			);
		}
		
		// 未上传的附件数
		$not_uploaded = $total_attachments - $uploaded_attachments;
		
		// 上传失败的附件数
		$failed_attachments = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
			WHERE meta_key = '_wpmcs_last_error' AND meta_value != ''"
		);
		
		// 总文件大小（估算）
		$total_size = 0;
		$uploads_dir = wpmcs_get_upload_dir();
		$uploads_base_dir = isset( $uploads_dir['basedir'] ) ? (string) $uploads_dir['basedir'] : '';
		if ( '' !== $uploads_base_dir && is_dir( $uploads_base_dir ) ) {
			$total_size = $this->get_directory_size( $uploads_base_dir );
		}
		
		return array(
			'total_attachments' => $total_attachments,
			'uploaded_attachments' => $uploaded_attachments,
			'not_uploaded' => $not_uploaded,
			'failed_attachments' => $failed_attachments,
			'total_size' => size_format( $total_size ),
			'total_size_bytes' => $total_size,
		);
	}
	
	/**
	 * 获取需要迁移的附件 ID 列表
	 * 
	 * @param bool $incremental 是否增量迁移（跳过已上传的）
	 * @param bool $retry_failed 是否包含失败的
	 * @return array
	 */
	public function get_attachments_to_migrate( $incremental = true, $retry_failed = false, $force_reupload = false ) {
		global $wpdb;
		
		// 基础查询
		$sql = "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'attachment'";
		
		if ( $incremental && ! $retry_failed && ! $force_reupload ) {
			// Keep already-migrated attachments out of the incremental queue,
			// even if their postmeta was removed during uninstall/reinstall.
			if ( class_exists( 'WPMCS_Attachment_Manager' ) ) {
				$sql .= " AND NOT " . WPMCS_Attachment_Manager::get_cloud_detection_sql();
			} else {
				// Legacy fallback for older installs.
				$sql .= " AND ID NOT IN (
					SELECT post_id FROM {$wpdb->postmeta} 
					WHERE meta_key = '_wpmcs_cloud_meta' AND meta_value != ''
				)";
			}
		} elseif ( $retry_failed ) {
			// 只获取失败的
			$sql .= " AND ID IN (
				SELECT post_id FROM {$wpdb->postmeta} 
				WHERE meta_key = '_wpmcs_last_error' AND meta_value != ''
			)";
		}
		
		$sql .= " ORDER BY ID ASC";
		
		$attachment_ids = $wpdb->get_col( $sql );
		
		return array_map( 'intval', $attachment_ids );
	}
	
	/**
	 * 开始迁移
	 */
	public function start_migration( $incremental = true, $retry_failed = false, $force_reupload = false ) {
		// 获取需要迁移的附件
		$attachment_ids = $this->get_attachments_to_migrate( $incremental, $retry_failed, $force_reupload );
		
		if ( empty( $attachment_ids ) ) {
			return new WP_Error( 'no_attachments', '没有需要迁移的附件' );
		}
		
		// 初始化迁移状态
		$status = array(
			'status' => 'running',
			'total' => count( $attachment_ids ),
			'processed' => 0,
			'success' => 0,
			'failed' => 0,
			'failed_ids' => array(),
			'current_batch' => 0,
			'attachment_ids' => $attachment_ids,
			'force_reupload' => (bool) $force_reupload,
			'started_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
			'error_message' => '',
		);
		
		update_option( $this->option_name, $status );
		
		return $status;
	}
	
	/**
	 * 处理一批迁移
	 */
	public function process_batch( $batch_size = null ) {
		$status = get_option( $this->option_name, array() );
		
		if ( empty( $status ) || $status['status'] !== 'running' ) {
			return new WP_Error( 'not_running', '迁移未在运行中' );
		}
		
		$batch_size = $batch_size ? $batch_size : $this->batch_size;
		$offset = $status['processed'];
		$attachment_ids = $status['attachment_ids'];
		
		// 获取当前批次
		$batch = array_slice( $attachment_ids, $offset, $batch_size );
		
		if ( empty( $batch ) ) {
			// 所有附件已处理完成
			$status['status'] = 'completed';
			$status['updated_at'] = current_time( 'mysql' );
			update_option( $this->option_name, $status );
			return $status;
		}
		
		// 处理每个附件
		foreach ( $batch as $attachment_id ) {
			$result = $this->migrate_attachment( $attachment_id );
			
			$status['processed']++;
			
			if ( is_wp_error( $result ) ) {
				$status['failed']++;
				$status['failed_ids'][] = array(
					'id' => $attachment_id,
					'error' => $result->get_error_message()
				);
				
				// 保存错误信息
				WPMCS_Attachment_Manager::save_upload_error( $attachment_id, $result->get_error_message() );
			} else {
				$status['success']++;
			}
			
			$status['updated_at'] = current_time( 'mysql' );
			$status['current_batch']++;
			
			// 更新进度
			update_option( $this->option_name, $status );
		}
		
		return $status;
	}
	
	/**
	 * 迁移单个附件
	 */
	private function migrate_attachment( $attachment_id ) {
		// 获取文件路径
		$file_path = get_attached_file( $attachment_id );
		$local_url = wp_get_attachment_url( $attachment_id );
		$status = get_option( $this->option_name, array() );
		$force_reupload = ! empty( $status['force_reupload'] );
		
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			$this->log_migration_error( 'file_not_found', '本地文件不存在', array(
				'attachment_id' => $attachment_id,
				'file_path' => $file_path,
			) );
			return new WP_Error( 'file_not_found', '文件不存在' );
		}
		
		// 检查是否已上传
		if ( ! $force_reupload && WPMCS_Attachment_Manager::has_cloud_meta( $attachment_id ) ) {
			$this->log_migration_info( 'migration', '附件已存在云端记录，跳过迁移', array(
				'attachment_id' => $attachment_id,
				'file_path' => $file_path,
			) );
			return true; // 已上传，跳过
		}
		
		// 构建云存储 key
		$cloud_key = $this->build_cloud_key( $file_path );
		
		// 上传到云端
		try {
			$storage = $this->get_storage();

			if ( is_wp_error( $storage ) ) {
				return $storage;
			}

			$prepared = class_exists( 'WPMCS_WebP_Converter' )
				? WPMCS_WebP_Converter::prepare_upload( $file_path, $cloud_key, $this->settings )
				: array(
					'file_path' => $file_path,
					'cloud_key' => $prepared['cloud_key'],
					'mime_type' => isset( wp_check_filetype( $file_path )['type'] ) ? (string) wp_check_filetype( $file_path )['type'] : '',
					'converted' => false,
					'temp_file' => '',
				);

			try {
				$result = $storage->upload( $prepared['file_path'], $prepared['cloud_key'] );

				if ( is_wp_error( $result ) ) {
					throw new RuntimeException( $result->get_error_message() );
				}
			} catch ( Throwable $e ) {
				if ( empty( $prepared['converted'] ) ) {
					$this->log_migration_error( 'upload_failed', 'Upload failed', array(
						'attachment_id' => $attachment_id,
						'file_path' => $file_path,
						'cloud_key' => $prepared['cloud_key'],
						'error' => $e->getMessage(),
					) );
					if ( class_exists( 'WPMCS_WebP_Converter' ) ) {
						WPMCS_WebP_Converter::cleanup( $prepared );
					}
					return new WP_Error( 'upload_failed', $e->getMessage() );
				}

				$result = $storage->upload(
					$prepared['original_path'],
					isset( $prepared['original_cloud_key'] ) ? (string) $prepared['original_cloud_key'] : $cloud_key
				);

				if ( is_wp_error( $result ) ) {
					$this->log_migration_error( 'upload_failed', 'Upload failed', array(
						'attachment_id' => $attachment_id,
						'file_path' => $file_path,
						'cloud_key' => $prepared['cloud_key'],
						'error' => $result->get_error_message(),
					) );
					if ( class_exists( 'WPMCS_WebP_Converter' ) ) {
						WPMCS_WebP_Converter::cleanup( $prepared );
					}
					return $result;
				}
			}

			
			// 获取云端 URL
			$cloud_url = is_array( $result ) ? $result['url'] : $result;
			$cloud_key = is_array( $result ) ? $result['key'] : $prepared['cloud_key'];
			
			// 保存云端元数据
			$cloud_meta = array(
				'provider' => $this->settings['provider'] ?? 'unknown',
				'key' => $cloud_key,
				'url' => $cloud_url,
				'sizes' => array(),
				'uploaded_at' => current_time( 'mysql' )
			);
			
			// 处理缩略图
			// 只上传原图，不同步各个缩略图尺寸。
			
			if ( class_exists( 'WPMCS_WebP_Converter' ) ) {
				WPMCS_WebP_Converter::cleanup( $prepared );
			}

			WPMCS_Attachment_Manager::save_cloud_meta( $attachment_id, $cloud_meta );
			WPMCS_Attachment_Manager::delete_local_files( $attachment_id, $this->settings );
			$this->log_migration_info( 'migration', '附件迁移成功', array(
				'attachment_id' => $attachment_id,
				'file_path' => $file_path,
				'cloud_key' => $cloud_key,
				'cloud_url' => $cloud_url,
			) );

			if ( ! empty( $this->settings['replace_url'] ) ) {
				$replacement_url = $cloud_url;
				if ( ! empty( $this->settings['enable_logging'] ) ) {
					$replacement_url = WPMCS_Attachment_Manager::get_tracking_url( $attachment_id, 'full' );
				}
				$this->replace_attachment_urls_in_content( $local_url, $replacement_url );
			}
			
			return true;
			
		} catch ( Exception $e ) {
			$this->log_migration_error( 'upload_failed', '迁移过程发生异常', array(
				'attachment_id' => $attachment_id,
				'file_path' => $file_path,
				'error' => $e->getMessage(),
			) );
			if ( isset( $prepared ) && class_exists( 'WPMCS_WebP_Converter' ) ) {
				WPMCS_WebP_Converter::cleanup( $prepared );
			}
			return new WP_Error( 'upload_failed', $e->getMessage() );
		}
	}
	
	/**
	 * 获取迁移进度
	 */
	public function get_progress() {
		$status = get_option( $this->option_name, array() );
		
		if ( empty( $status ) ) {
			return array(
				'status' => 'idle',
				'total' => 0,
				'processed' => 0,
				'success' => 0,
				'failed' => 0,
			);
		}
		
		return $status;
	}
	
	/**
	 * 重试失败的迁移
	 */
	public function retry_failed() {
		$status = get_option( $this->option_name, array() );
		
		if ( empty( $status ) || empty( $status['failed_ids'] ) ) {
			return new WP_Error( 'no_failed', '没有失败的迁移项' );
		}
		
		// 获取失败的附件 ID
		$failed_ids = array_column( $status['failed_ids'], 'id' );
		
		// 重新开始迁移，只处理失败的
		$new_status = array(
			'status' => 'running',
			'total' => count( $failed_ids ),
			'processed' => 0,
			'success' => 0,
			'failed' => 0,
			'failed_ids' => array(),
			'current_batch' => 0,
			'attachment_ids' => $failed_ids,
			'started_at' => current_time( 'mysql' ),
			'updated_at' => current_time( 'mysql' ),
			'error_message' => '',
		);
		
		update_option( $this->option_name, $new_status );
		
		return $new_status;
	}
	
	/**
	 * 取消迁移
	 */
	public function cancel_migration() {
		$status = get_option( $this->option_name, array() );
		
		if ( empty( $status ) ) {
			return false;
		}
		
		$status['status'] = 'cancelled';
		$status['updated_at'] = current_time( 'mysql' );
		
		update_option( $this->option_name, $status );
		
		return true;
	}
	
	/**
	 * 构建云存储 key
	 */
	private function build_cloud_key( $file_path ) {
		$file_path = (string) $file_path;

		if ( '' === trim( $file_path ) ) {
			return '';
		}

		$uploads = wpmcs_get_upload_dir();
		$base_dir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
		$normalized_path = wp_normalize_path( $file_path );
		$normalized_base = '' !== $base_dir ? wp_normalize_path( $base_dir ) : '';
		$relative = '' !== $normalized_base ? ltrim( str_replace( $normalized_base, '', $normalized_path ), '/' ) : ltrim( $normalized_path, '/' );
		$prefix = '';
		
		if ( ! empty( $this->settings['upload_path'] ) ) {
			$prefix = trim( (string) $this->settings['upload_path'], '/' ) . '/';
		}
		
		if ( '' === $relative ) {
			$relative = basename( $file_path );
		}
		
		return $prefix . str_replace( '\\', '/', $relative );
	}
	
	/**
	 * 获取目录大小
	 */
	/**
	 * 将文章内容中的本地附件 URL 替换为云端 URL。
	 *
	 * @param string $local_url 本地 URL。
	 * @param string $cloud_url 云端 URL。
	 * @return int
	 */
	private function replace_attachment_urls_in_content( $local_url, $replacement_url ) {
		global $wpdb;

		$local_url = trim( (string) $local_url );
		$replacement_url = trim( (string) $replacement_url );

		if ( '' === $local_url || '' === $replacement_url || $local_url === $replacement_url ) {
			return 0;
		}

		$like = '%' . $wpdb->esc_like( $local_url ) . '%';
		$posts = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT ID, post_content, post_excerpt
				FROM {$wpdb->posts}
				WHERE post_status <> 'auto-draft'
				AND post_type <> 'attachment'
				AND (
					post_content LIKE %s
					OR post_excerpt LIKE %s
				)",
				$like,
				$like
			)
		);

		if ( empty( $posts ) ) {
			return 0;
		}

		$updated = 0;

		foreach ( $posts as $post ) {
			$new_content = str_replace( $local_url, $replacement_url, $post->post_content );
			$new_excerpt = str_replace( $local_url, $replacement_url, $post->post_excerpt );

			if ( $new_content === $post->post_content && $new_excerpt === $post->post_excerpt ) {
				continue;
			}

			$result = wp_update_post(
				wp_slash(
					array(
						'ID'          => (int) $post->ID,
						'post_content' => $new_content,
						'post_excerpt' => $new_excerpt,
					)
				),
				true
			);

			if ( ! is_wp_error( $result ) ) {
				$updated++;
			}
		}

		if ( $updated > 0 ) {
			error_log( sprintf( 'WPMCS: replaced attachment URLs in %d posts.', $updated ) );
		}

		return $updated;
	}

	private function get_directory_size( $path ) {
		$total_size = 0;
		
		if ( ! is_dir( $path ) ) {
			return $total_size;
		}
		
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $path, FilesystemIterator::SKIP_DOTS )
		);
		
		foreach ( $iterator as $file ) {
			if ( $file->isFile() ) {
				$total_size += $file->getSize();
			}
		}
		
		return $total_size;
	}
	
	/**
	 * AJAX: 获取统计信息
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'wpmcs_migration', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$stats = $this->get_migration_stats();
		
		wp_send_json_success( $stats );
	}
	
	/**
	 * AJAX: 开始迁移
	 */
	public function ajax_start_migration() {
		check_ajax_referer( 'wpmcs_migration', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$incremental = isset( $_POST['incremental'] ) && $_POST['incremental'] === 'true';
		$retry_failed = isset( $_POST['retry_failed'] ) && $_POST['retry_failed'] === 'true';
		$force_reupload = isset( $_POST['force_reupload'] ) && $_POST['force_reupload'] === 'true';
		
		$status = $this->start_migration( $incremental, $retry_failed, $force_reupload );
		
		if ( is_wp_error( $status ) ) {
			wp_send_json_error( array( 'message' => $status->get_error_message() ) );
		}
		
		wp_send_json_success( $status );
	}
	
	/**
	 * AJAX: 处理批次
	 */
	public function ajax_process_batch() {
		check_ajax_referer( 'wpmcs_migration', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : $this->batch_size;
		
		$status = $this->process_batch( $batch_size );
		
		if ( is_wp_error( $status ) ) {
			wp_send_json_error( array( 'message' => $status->get_error_message() ) );
		}
		
		wp_send_json_success( $status );
	}
	
	/**
	 * AJAX: 获取进度
	 */
	public function ajax_get_progress() {
		check_ajax_referer( 'wpmcs_migration', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$progress = $this->get_progress();
		
		wp_send_json_success( $progress );
	}
	
	/**
	 * AJAX: 重试失败项
	 */
	public function ajax_retry_failed() {
		check_ajax_referer( 'wpmcs_migration', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$status = $this->retry_failed();
		
		if ( is_wp_error( $status ) ) {
			wp_send_json_error( array( 'message' => $status->get_error_message() ) );
		}
		
		wp_send_json_success( $status );
	}
	
	/**
	 * AJAX: 取消迁移
	 */
	public function ajax_cancel_migration() {
		check_ajax_referer( 'wpmcs_migration', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$result = $this->cancel_migration();
		
		wp_send_json_success( array( 'cancelled' => $result ) );
	}

	/**
	 * 仅在迁移任务真正执行时才延迟创建存储驱动。
	 *
	 * @return Cloud_Storage_Interface|WP_Error
	 */
	/**
	 * @param string $type
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	private function log_migration_info( $type, $message, array $context = array() ) {
		if ( $this->logger && method_exists( $this->logger, 'info' ) ) {
			$this->logger->info( $type, $message, $context );
		}
	}

	/**
	 * @param string $type
	 * @param string $message
	 * @param array $context
	 * @return void
	 */
	private function log_migration_error( $type, $message, array $context = array() ) {
		if ( $this->logger && method_exists( $this->logger, 'error' ) ) {
			$this->logger->error( $type, $message, $context );
		}
	}

	private function get_storage() {
		if ( is_object( $this->storage ) ) {
			return $this->storage;
		}

		if ( is_wp_error( $this->storage ) ) {
			return $this->storage;
		}

		$this->storage = wpmcs_create_storage_driver( $this->settings );
		return $this->storage;
	}
}
