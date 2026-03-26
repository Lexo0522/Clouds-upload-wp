<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 异步上传队列管理器
 * 
 * 使用 WordPress Cron 实现后台异步上传
 */
class WPMCS_Async_Queue {
	
	/**
	 * 队列选项名称
	 */
	const QUEUE_OPTION = 'wpmcs_upload_queue';
	
	/**
	 * 正在处理的选项名称
	 */
	const PROCESSING_OPTION = 'wpmcs_processing_queue';
	
	/**
	 * 锁定选项名称（防止并发处理）
	 */
	const LOCK_OPTION = 'wpmcs_queue_lock';
	
	/**
	 * 锁定过期时间（秒）
	 */
	const LOCK_EXPIRE = 300; // 5分钟
	
	/**
	 * 每次处理的任务数量
	 */
	const BATCH_SIZE = 5;
	
	/**
	 * @var array
	 */
	private $settings;
	
	/**
	 * @var Cloud_Storage_Interface|null
	 */
	private $storage;
	
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}
	
	/**
	 * 注册钩子
	 */
	public function register_hooks() {
		// 注册定时任务
		add_action( 'init', array( $this, 'schedule_events' ) );
		
		// 定时任务回调
		add_action( 'wpmcs_process_queue', array( $this, 'process_queue' ) );
		
		// 清理过期锁定
		add_action( 'wpmcs_cleanup_locks', array( $this, 'cleanup_expired_locks' ) );
		
		// 插件停用时清理
		register_deactivation_hook( WPMCS_PLUGIN_FILE, array( $this, 'deactivate' ) );
	}
	
	/**
	 * 调度定时任务
	 */
	public function schedule_events() {
		if ( ! wp_next_scheduled( 'wpmcs_process_queue' ) ) {
			wp_schedule_event( time(), 'every_minute', 'wpmcs_process_queue' );
		}
		
		if ( ! wp_next_scheduled( 'wpmcs_cleanup_locks' ) ) {
			wp_schedule_event( time(), 'hourly', 'wpmcs_cleanup_locks' );
		}
	}
	
	/**
	 * 插件停用时清理
	 */
	public function deactivate() {
		wp_clear_scheduled_hook( 'wpmcs_process_queue' );
		wp_clear_scheduled_hook( 'wpmcs_cleanup_locks' );
	}
	
	/**
	 * 添加任务到队列
	 * 
	 * @param int $attachment_id 附件 ID
	 * @param int $priority 优先级（越小越优先）
	 * @return bool
	 */
	public function add_to_queue( $attachment_id, $priority = 10 ) {
		$queue = $this->get_queue();
		
		// 检查是否已在队列中
		foreach ( $queue as $item ) {
			if ( $item['attachment_id'] == $attachment_id ) {
				return true; // 已存在
			}
		}
		
		// 添加到队列
		$task = array(
			'attachment_id' => $attachment_id,
			'priority' => $priority,
			'added_at' => time(),
			'attempts' => 0,
			'max_attempts' => 3,
			'last_error' => '',
		);
		
		$queue[] = $task;
		
		// 按优先级排序
		usort( $queue, function( $a, $b ) {
			return $a['priority'] - $b['priority'];
		});
		
		return update_option( self::QUEUE_OPTION, $queue );
	}
	
	/**
	 * 批量添加任务到队列
	 * 
	 * @param array $attachment_ids 附件 ID 数组
	 * @param int $priority 优先级
	 * @return int 成功添加的数量
	 */
	public function add_batch_to_queue( $attachment_ids, $priority = 10 ) {
		$added = 0;
		
		foreach ( $attachment_ids as $attachment_id ) {
			if ( $this->add_to_queue( $attachment_id, $priority ) ) {
				$added++;
			}
		}
		
		return $added;
	}
	
	/**
	 * 从队列中移除任务
	 * 
	 * @param int $attachment_id 附件 ID
	 * @return bool
	 */
	public function remove_from_queue( $attachment_id ) {
		$queue = $this->get_queue();
		
		foreach ( $queue as $index => $item ) {
			if ( $item['attachment_id'] == $attachment_id ) {
				unset( $queue[ $index ] );
				break;
			}
		}
		
		return update_option( self::QUEUE_OPTION, array_values( $queue ) );
	}
	
	/**
	 * 获取队列
	 * 
	 * @return array
	 */
	public function get_queue() {
		$queue = get_option( self::QUEUE_OPTION, array() );
		return is_array( $queue ) ? $queue : array();
	}
	
	/**
	 * 获取队列统计
	 * 
	 * @return array
	 */
	public function get_queue_stats() {
		$queue = $this->get_queue();
		
		$stats = array(
			'total' => count( $queue ),
			'pending' => 0,
			'processing' => 0,
			'failed' => 0,
			'high_priority' => 0,
		);
		
		foreach ( $queue as $item ) {
			if ( $item['attempts'] > 0 ) {
				if ( $item['attempts'] >= $item['max_attempts'] ) {
					$stats['failed']++;
				} else {
					$stats['processing']++;
				}
			} else {
				$stats['pending']++;
			}
			
			if ( $item['priority'] < 10 ) {
				$stats['high_priority']++;
			}
		}
		
		return $stats;
	}
	
	/**
	 * 处理队列
	 */
	public function process_queue() {
		// 检查是否启用异步上传
		if ( empty( $this->settings['async_upload'] ) ) {
			return;
		}
		
		// 尝试获取锁定
		if ( ! $this->acquire_lock() ) {
			return; // 已有其他进程在处理
		}
		
		try {
			$queue = $this->get_queue();
			
			if ( empty( $queue ) ) {
				return;
			}
			
			// 获取一批任务
			$batch = array_slice( $queue, 0, self::BATCH_SIZE );
			
			foreach ( $batch as $index => $task ) {
				// 处理单个任务
				$result = $this->process_task( $task );
				
				// 更新队列
				if ( is_wp_error( $result ) ) {
					// 增加尝试次数
					$task['attempts']++;
					$task['last_error'] = $result->get_error_message();
					
					// 如果未超过最大尝试次数，重新加入队列
					if ( $task['attempts'] < $task['max_attempts'] ) {
						$queue[ $index ] = $task;
					} else {
						// 超过最大尝试次数，从队列移除并记录错误
						unset( $queue[ $index ] );
						
						// 保存错误信息
						WPMCS_Attachment_Manager::save_upload_error(
							$task['attachment_id'],
							$result->get_error_message()
						);
					}
				} else {
					// 成功，从队列移除
					unset( $queue[ $index ] );
				}
			}
			
			// 保存更新后的队列
			update_option( self::QUEUE_OPTION, array_values( $queue ) );
			
		} finally {
			// 释放锁定
			$this->release_lock();
		}
	}
	
	/**
	 * 处理单个任务
	 * 
	 * @param array $task 任务数据
	 * @return true|WP_Error
	 */
	private function process_task( $task ) {
		$attachment_id = $task['attachment_id'];
		
		// 检查附件是否存在
		$file_path = get_attached_file( $attachment_id );
		
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return new WP_Error( 'file_not_found', '文件不存在' );
		}
		
		// 检查是否已上传
		if ( WPMCS_Attachment_Manager::has_cloud_meta( $attachment_id ) ) {
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
					'cloud_key' => $cloud_key,
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
				'uploaded_at' => current_time( 'mysql' ),
				'async' => true, // 标记为异步上传
			);
			
			// 处理缩略图
			// 只上传原图，不同步各个缩略图尺寸。
			
			if ( class_exists( 'WPMCS_WebP_Converter' ) ) {
				WPMCS_WebP_Converter::cleanup( $prepared );
			}

			WPMCS_Attachment_Manager::save_cloud_meta( $attachment_id, $cloud_meta );
			
			// 清除错误信息
			delete_post_meta( $attachment_id, '_wpmcs_last_error' );
			WPMCS_Attachment_Manager::delete_local_files( $attachment_id, $this->settings );
			
			return true;
			
		} catch ( Exception $e ) {
			if ( isset( $prepared ) && class_exists( 'WPMCS_WebP_Converter' ) ) {
				WPMCS_WebP_Converter::cleanup( $prepared );
			}
			return new WP_Error( 'upload_failed', $e->getMessage() );
		}
	}
	
	/**
	 * 构建云存储 key
	 * 
	 * @param string $file_path 文件路径
	 * @return string
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
	 * 获取锁定
	 * 
	 * @return bool
	 */
	private function acquire_lock() {
		$lock = get_option( self::LOCK_OPTION );
		
		if ( $lock && isset( $lock['expires'] ) && $lock['expires'] > time() ) {
			return false; // 已被锁定
		}
		
		$lock_data = array(
			'expires' => time() + self::LOCK_EXPIRE,
			'process_id' => getmypid(),
			'time' => current_time( 'mysql' ),
		);
		
		return update_option( self::LOCK_OPTION, $lock_data );
	}
	
	/**
	 * 释放锁定
	 */
	private function release_lock() {
		delete_option( self::LOCK_OPTION );
	}
	
	/**
	 * 清理过期锁定
	 */
	public function cleanup_expired_locks() {
		$lock = get_option( self::LOCK_OPTION );
		
		if ( $lock && isset( $lock['expires'] ) && $lock['expires'] <= time() ) {
			delete_option( self::LOCK_OPTION );
		}
	}
	
	/**
	 * 清空队列
	 */
	public function clear_queue() {
		delete_option( self::QUEUE_OPTION );
		delete_option( self::PROCESSING_OPTION );
	}
	
	/**
	 * 重试失败的任务
	 */
	public function retry_failed_tasks() {
		$queue = $this->get_queue();
		
		foreach ( $queue as $index => $task ) {
			if ( $task['attempts'] >= $task['max_attempts'] ) {
				// 重置尝试次数
				$task['attempts'] = 0;
				$task['last_error'] = '';
				$queue[ $index ] = $task;
			}
		}
		
		update_option( self::QUEUE_OPTION, $queue );
	}

	/**
	 * 仅在队列处理时延迟创建存储驱动。
	 *
	 * @return Cloud_Storage_Interface|WP_Error
	 */
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
