<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 批量操作管理器
 * 
 * 处理文件的批量上传、删除、迁移等操作
 */
class WPMCS_Batch_Operations {
	
	/**
	 * @var array
	 */
	private $settings;
	
	/**
	 * 批次大小
	 */
	const BATCH_SIZE = 50;
	
	/**
	 * 构造函数
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
		$this->init_hooks();
	}
	
	/**
	 * 初始化钩子
	 */
	public function init_hooks() {
		// AJAX 处理
		add_action( 'wp_ajax_wpmcs_batch_upload', array( $this, 'ajax_batch_upload' ) );
		add_action( 'wp_ajax_wpmcs_batch_delete', array( $this, 'ajax_batch_delete' ) );
		add_action( 'wp_ajax_wpmcs_batch_migrate', array( $this, 'ajax_batch_migrate' ) );
		add_action( 'wp_ajax_wpmcs_batch_sync', array( $this, 'ajax_batch_sync' ) );
		add_action( 'wp_ajax_wpmcs_batch_status', array( $this, 'ajax_get_batch_status' ) );
		add_action( 'wp_ajax_wpmcs_batch_cancel', array( $this, 'ajax_cancel_batch' ) );
		
		// 媒体库批量操作
		add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'show_bulk_action_notice' ) );
	}
	
	/**
	 * 添加媒体库批量操作选项
	 */
	public function add_bulk_actions( $actions ) {
		$actions['wpmcs_upload_to_cloud'] = __( '上传到云存储', 'wp-multi-cloud-storage' );
		$actions['wpmcs_delete_from_cloud'] = __( '从云存储删除', 'wp-multi-cloud-storage' );
		$actions['wpmcs_sync_metadata'] = __( '同步元数据', 'wp-multi-cloud-storage' );
		
		return $actions;
	}
	
	/**
	 * 处理媒体库批量操作
	 */
	public function handle_bulk_actions( $redirect_to, $action, $ids ) {
		if ( empty( $ids ) ) {
			return $redirect_to;
		}
		
		switch ( $action ) {
			case 'wpmcs_upload_to_cloud':
				$result = $this->process_upload_batch( $ids );
				$redirect_to = add_query_arg( 'wpmcs_batch_result', urlencode( json_encode( $result ) ), $redirect_to );
				break;
				
			case 'wpmcs_delete_from_cloud':
				$result = $this->process_delete_batch( $ids );
				$redirect_to = add_query_arg( 'wpmcs_batch_result', urlencode( json_encode( $result ) ), $redirect_to );
				break;
				
			case 'wpmcs_sync_metadata':
				$result = $this->process_sync_batch( $ids );
				$redirect_to = add_query_arg( 'wpmcs_batch_result', urlencode( json_encode( $result ) ), $redirect_to );
				break;
		}
		
		return $redirect_to;
	}
	
	/**
	 * 显示批量操作结果通知
	 */
	public function show_bulk_action_notice() {
		if ( ! isset( $_GET['wpmcs_batch_result'] ) ) {
			return;
		}
		
		$result = json_decode( urldecode( $_GET['wpmcs_batch_result'] ), true );
		
		if ( ! $result ) {
			return;
		}
		
		$class = $result['success'] ? 'notice-success' : 'notice-error';
		
		echo '<div class="notice ' . esc_attr( $class ) . ' is-dismissible">';
		echo '<p>' . esc_html( $result['message'] ) . '</p>';
		
		if ( ! empty( $result['details'] ) ) {
			echo '<ul>';
			foreach ( $result['details'] as $detail ) {
				echo '<li>' . esc_html( $detail ) . '</li>';
			}
			echo '</ul>';
		}
		
		echo '</div>';
	}
	
	/**
	 * 处理上传批次
	 */
	private function process_upload_batch( $ids ) {
		$success_count = 0;
		$error_count = 0;
		$errors = array();
		
		foreach ( $ids as $id ) {
			$attachment = get_post( $id );
			
			if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
				$error_count++;
				$errors[] = sprintf( 'ID %d: 不是有效的附件', $id );
				continue;
			}
			
			$file_path = get_attached_file( $id );
			
			if ( ! file_exists( $file_path ) ) {
				$error_count++;
				$errors[] = sprintf( 'ID %d: 文件不存在', $id );
				continue;
			}
			
			$result = $this->upload_single_file( $id, $file_path );
			
			if ( $result['success'] ) {
				$success_count++;
			} else {
				$error_count++;
				$errors[] = sprintf( 'ID %d: %s', $id, $result['error'] );
			}
		}
		
		return array(
			'success' => $error_count === 0,
			'message' => sprintf( '上传完成：成功 %d 个，失败 %d 个', $success_count, $error_count ),
			'details' => $errors,
			'success_count' => $success_count,
			'error_count' => $error_count,
		);
	}
	
	/**
	 * 处理删除批次
	 */
	private function process_delete_batch( $ids ) {
		$success_count = 0;
		$error_count = 0;
		$errors = array();
		
		foreach ( $ids as $id ) {
			$result = $this->delete_single_file( $id );
			
			if ( $result['success'] ) {
				$success_count++;
			} else {
				$error_count++;
				$errors[] = sprintf( 'ID %d: %s', $id, $result['error'] );
			}
		}
		
		return array(
			'success' => $error_count === 0,
			'message' => sprintf( '删除完成：成功 %d 个，失败 %d 个', $success_count, $error_count ),
			'details' => $errors,
			'success_count' => $success_count,
			'error_count' => $error_count,
		);
	}
	
	/**
	 * 处理同步批次
	 */
	private function process_sync_batch( $ids ) {
		$success_count = 0;
		$error_count = 0;
		$errors = array();
		
		foreach ( $ids as $id ) {
			$result = $this->sync_single_file( $id );
			
			if ( $result['success'] ) {
				$success_count++;
			} else {
				$error_count++;
				$errors[] = sprintf( 'ID %d: %s', $id, $result['error'] );
			}
		}
		
		return array(
			'success' => $error_count === 0,
			'message' => sprintf( '同步完成：成功 %d 个，失败 %d 个', $success_count, $error_count ),
			'details' => $errors,
			'success_count' => $success_count,
			'error_count' => $error_count,
		);
	}
	
	/**
	 * 上传单个文件
	 */
	private function upload_single_file( $attachment_id, $file_path ) {
		try {
			$adapter = wpmcs_get_adapter( $this->settings );
			
			if ( ! $adapter ) {
				return array( 'success' => false, 'error' => '无法获取存储适配器' );
			}
			
			// 生成云存储路径
			$upload_path = isset( $this->settings['upload_path'] ) ? $this->settings['upload_path'] : '';
			$relative_path = $this->get_relative_path( $file_path );
			$cloud_path = $upload_path ? $upload_path . '/' . $relative_path : $relative_path;
			$prepared = class_exists( 'WPMCS_WebP_Converter' )
				? WPMCS_WebP_Converter::prepare_upload( $file_path, $cloud_path, $this->settings )
				: array(
					'file_path' => $file_path,
					'cloud_key' => $cloud_path,
					'mime_type' => isset( wp_check_filetype( $file_path )['type'] ) ? (string) wp_check_filetype( $file_path )['type'] : '',
					'converted' => false,
					'temp_file' => '',
				);
			
			// 上传文件
			$result = $adapter->upload( $prepared['file_path'], $prepared['cloud_key'] );
			
			if ( is_wp_error( $result ) ) {
				if ( class_exists( 'WPMCS_WebP_Converter' ) ) {
					WPMCS_WebP_Converter::cleanup( $prepared );
				}
				return array( 'success' => false, 'error' => $result->get_error_message() );
			}
			
			// 更新附件元数据
			$this->update_attachment_metadata( $attachment_id, $result, $prepared );
			if ( class_exists( 'WPMCS_WebP_Converter' ) ) {
				WPMCS_WebP_Converter::cleanup( $prepared );
			}
			
			return array( 'success' => true );
			
		} catch ( Exception $e ) {
			if ( isset( $prepared ) && class_exists( 'WPMCS_WebP_Converter' ) ) {
				WPMCS_WebP_Converter::cleanup( $prepared );
			}
			return array( 'success' => false, 'error' => $e->getMessage() );
		}
	}
	
	/**
	 * 删除单个文件
	 */
	private function delete_single_file( $attachment_id ) {
		try {
			$cloud_meta = WPMCS_Attachment_Manager::get_cloud_meta( $attachment_id );
			$cloud_url = isset( $cloud_meta['url'] ) ? $cloud_meta['url'] : '';
			
			if ( ! $cloud_url ) {
				return array( 'success' => false, 'error' => '文件未上传到云存储' );
			}
			
			$adapter = wpmcs_get_adapter( $this->settings );
			
			if ( ! $adapter ) {
				return array( 'success' => false, 'error' => '无法获取存储适配器' );
			}
			
			// 获取云存储路径
			$cloud_path = isset( $cloud_meta['key'] ) ? $cloud_meta['key'] : '';
			if ( empty( $cloud_path ) ) {
				$cloud_path = get_post_meta( $attachment_id, '_wpmcs_cloud_path', true );
			}
			
			// 删除文件
			$result = $adapter->delete( $cloud_path );
			
			if ( is_wp_error( $result ) ) {
				return array( 'success' => false, 'error' => $result->get_error_message() );
			}
			
			// 清除元数据
			WPMCS_Attachment_Manager::delete_cloud_meta( $attachment_id );
			
			return array( 'success' => true );
			
		} catch ( Exception $e ) {
			return array( 'success' => false, 'error' => $e->getMessage() );
		}
	}
	
	/**
	 * 同步单个文件元数据
	 */
	private function sync_single_file( $attachment_id ) {
		try {
			$file_path = get_attached_file( $attachment_id );
			
			if ( ! $file_path || ! file_exists( $file_path ) ) {
				return array( 'success' => false, 'error' => '文件不存在' );
			}
			
			// 检查是否已上传
			$cloud_meta = WPMCS_Attachment_Manager::get_cloud_meta( $attachment_id );
			$cloud_url = isset( $cloud_meta['url'] ) ? $cloud_meta['url'] : '';
			
			if ( $cloud_url ) {
				// 验证云端文件是否存在
				$adapter = wpmcs_get_adapter( $this->settings );
				$cloud_path = isset( $cloud_meta['key'] ) ? $cloud_meta['key'] : '';
				
				if ( $adapter && $cloud_path && $adapter->file_exists( $cloud_path ) ) {
					return array( 'success' => true );
				}
			}
			
			// 重新上传
			return $this->upload_single_file( $attachment_id, $file_path );
			
		} catch ( Exception $e ) {
			return array( 'success' => false, 'error' => $e->getMessage() );
		}
	}
	
	/**
	 * 获取相对路径
	 */
	private function get_relative_path( $file_path ) {
		$file_path = (string) $file_path;
		$upload_dir = wpmcs_get_upload_dir();
		$base_dir = isset( $upload_dir['basedir'] ) ? (string) $upload_dir['basedir'] : '';
		$file_path = ltrim( $file_path, "\\/" );
		
		if ( '' === $base_dir ) {
			return $file_path;
		}

		return str_replace( trailingslashit( $base_dir ), '', $file_path );
	}
	
	/**
	 * 更新附件元数据
	 */
	private function update_attachment_metadata( $attachment_id, $result, array $prepared = array() ) {
		$key = '';
		if ( is_array( $result ) ) {
			$key = isset( $result['key'] ) ? $result['key'] : ( isset( $result['path'] ) ? $result['path'] : '' );
		} else {
			$key = isset( $prepared['cloud_key'] ) ? $prepared['cloud_key'] : '';
		}

		$cloud_meta = array(
			'provider' => isset( $this->settings['provider'] ) ? $this->settings['provider'] : 'unknown',
			'key' => $key,
			'url' => isset( $result['url'] ) ? $result['url'] : '',
			'sizes' => array(),
			'uploaded_at' => current_time( 'mysql' ),
		);

		WPMCS_Attachment_Manager::save_cloud_meta( $attachment_id, $cloud_meta );
	}
	
	/**
	 * AJAX: 批量上传
	 */
	public function ajax_batch_upload() {
		check_ajax_referer( 'wpmcs_batch', 'nonce' );
		
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();
		
		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => '请选择要上传的文件' ) );
		}
		
		$result = $this->process_upload_batch( $ids );
		
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}
	
	/**
	 * AJAX: 批量删除
	 */
	public function ajax_batch_delete() {
		check_ajax_referer( 'wpmcs_batch', 'nonce' );
		
		if ( ! current_user_can( 'delete_posts' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();
		
		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => '请选择要删除的文件' ) );
		}
		
		$result = $this->process_delete_batch( $ids );
		
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}
	
	/**
	 * AJAX: 批量迁移
	 */
	public function ajax_batch_migrate() {
		check_ajax_referer( 'wpmcs_batch', 'nonce' );
		
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$offset = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		$batch_size = isset( $_POST['batch_size'] ) ? intval( $_POST['batch_size'] ) : self::BATCH_SIZE;
		
		// 获取未上传的附件
		$args = array(
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'posts_per_page' => $batch_size,
			'offset' => $offset,
			'meta_query' => array(
				array(
					'key' => '_wpmcs_cloud_url',
					'compare' => 'NOT EXISTS',
				),
			),
		);
		
		$query = new WP_Query( $args );
		$attachments = $query->posts;
		$total = $query->found_posts;
		
		$ids = wp_list_pluck( $attachments, 'ID' );
		$result = $this->process_upload_batch( $ids );
		
		wp_send_json_success( array(
			'processed' => count( $ids ),
			'success' => $result['success_count'],
			'errors' => $result['error_count'],
			'total' => $total,
			'has_more' => ( $offset + $batch_size ) < $total,
			'next_offset' => $offset + $batch_size,
		) );
	}
	
	/**
	 * AJAX: 批量同步
	 */
	public function ajax_batch_sync() {
		check_ajax_referer( 'wpmcs_batch', 'nonce' );
		
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$ids = isset( $_POST['ids'] ) ? array_map( 'intval', (array) $_POST['ids'] ) : array();
		
		if ( empty( $ids ) ) {
			wp_send_json_error( array( 'message' => '请选择要同步的文件' ) );
		}
		
		$result = $this->process_sync_batch( $ids );
		
		if ( $result['success'] ) {
			wp_send_json_success( $result );
		} else {
			wp_send_json_error( $result );
		}
	}
	
	/**
	 * AJAX: 获取批量操作状态
	 */
	public function ajax_get_batch_status() {
		check_ajax_referer( 'wpmcs_batch', 'nonce' );
		
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		// 获取统计信息
		$total_attachments = wp_count_attachments();
		$total_count = 0;
		
		foreach ( $total_attachments as $count ) {
			$total_count += $count;
		}
		
		// 已上传数量
		$args = array(
			'post_type' => 'attachment',
			'post_status' => 'inherit',
			'posts_per_page' => -1,
			'meta_query' => array(
				array(
					'key' => '_wpmcs_cloud_url',
					'compare' => 'EXISTS',
				),
			),
		);
		
		$query = new WP_Query( $args );
		$uploaded_count = $query->found_posts;
		
		wp_send_json_success( array(
			'total_files' => $total_count,
			'uploaded_files' => $uploaded_count,
			'pending_files' => $total_count - $uploaded_count,
			'progress_percent' => $total_count > 0 ? round( ( $uploaded_count / $total_count ) * 100, 1 ) : 0,
		) );
	}
	
	/**
	 * AJAX: 取消批量操作
	 */
	public function ajax_cancel_batch() {
		check_ajax_referer( 'wpmcs_batch', 'nonce' );
		
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		// 设置取消标志
		set_transient( 'wpmcs_batch_cancelled', true, 300 );
		
		wp_send_json_success( array( 'message' => '批量操作已取消' ) );
	}
}
