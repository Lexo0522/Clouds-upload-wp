<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Webhook 管理器
 * 
 * 提供事件通知和 Webhook 支持
 */
class WPMCS_Webhook_Manager {
	
	/**
	 * @var array
	 */
	private $settings;
	
	/**
	 * 支持的事件列表
	 */
	private $supported_events = array(
		'file.uploaded' => '文件上传成功',
		'file.deleted' => '文件删除',
		'file.error' => '文件上传错误',
		'migration.started' => '迁移开始',
		'migration.completed' => '迁移完成',
		'migration.failed' => '迁移失败',
		'connection.success' => '连接测试成功',
		'connection.failed' => '连接测试失败',
		'storage.near_limit' => '存储空间接近限制',
		'error.critical' => '严重错误',
	);
	
	/**
	 * 构造函数
	 *
	 * @param array $settings 插件设置
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
		$this->init_hooks();
	}
	
	/**
	 * 初始化钩子
	 */
	public function init_hooks() {
		// 文件上传事件
		add_action( 'wpmcs_file_uploaded', array( $this, 'trigger_file_uploaded' ), 10, 3 );
		add_action( 'wpmcs_file_deleted', array( $this, 'trigger_file_deleted' ), 10, 2 );
		add_action( 'wpmcs_upload_error', array( $this, 'trigger_upload_error' ), 10, 3 );
		
		// 迁移事件
		add_action( 'wpmcs_migration_started', array( $this, 'trigger_migration_started' ) );
		add_action( 'wpmcs_migration_completed', array( $this, 'trigger_migration_completed' ) );
		add_action( 'wpmcs_migration_failed', array( $this, 'trigger_migration_failed' ), 10, 2 );
		
		// 连接测试事件
		add_action( 'wpmcs_connection_success', array( $this, 'trigger_connection_success' ) );
		add_action( 'wpmcs_connection_failed', array( $this, 'trigger_connection_failed' ), 10, 2 );
		
		// 错误事件
		add_action( 'wpmcs_critical_error', array( $this, 'trigger_critical_error' ), 10, 3 );
	}
	
	/**
	 * 获取支持的事件列表
	 *
	 * @return array
	 */
	public function get_supported_events() {
		return $this->supported_events;
	}
	
	/**
	 * 触发事件
	 *
	 * @param string $event   事件名称
	 * @param array  $payload 事件数据
	 */
	public function trigger( $event, $payload = array() ) {
		// 获取所有 Webhooks
		$webhooks = get_option( 'wpmcs_webhooks', array() );
		
		if ( empty( $webhooks ) ) {
			return;
		}
		
		// 构建事件数据
		$event_data = array(
			'event' => $event,
			'timestamp' => current_time( 'mysql' ),
			'timestamp_unix' => time(),
			'site_url' => get_site_url(),
			'site_name' => get_bloginfo( 'name' ),
			'payload' => $payload,
		);
		
		// 发送到匹配的 Webhooks
		foreach ( $webhooks as $webhook ) {
			if ( ! isset( $webhook['active'] ) || ! $webhook['active'] ) {
				continue;
			}
			
			// 检查事件是否匹配
			if ( in_array( 'all', $webhook['events'] ) || in_array( $event, $webhook['events'] ) ) {
				$this->send_webhook( $webhook, $event_data );
			}
		}
	}
	
	/**
	 * 发送 Webhook
	 *
	 * @param array $webhook    Webhook 配置
	 * @param array $event_data 事件数据
	 */
	private function send_webhook( $webhook, $event_data ) {
		$url = $webhook['url'];
		$secret = isset( $webhook['secret'] ) ? $webhook['secret'] : '';
		
		// 生成签名
		$signature = '';
		if ( ! empty( $secret ) ) {
			$signature = hash_hmac( 'sha256', wp_json_encode( $event_data ), $secret );
		}
		
		// 构建请求头
		$headers = array(
			'Content-Type' => 'application/json',
			'User-Agent' => 'WPMCS-Webhook/1.0',
			'X-WPMCS-Event' => $event_data['event'],
			'X-WPMCS-Timestamp' => $event_data['timestamp_unix'],
		);
		
		if ( ! empty( $signature ) ) {
			$headers['X-WPMCS-Signature'] = 'sha256=' . $signature;
		}
		
		// 发送请求
		$response = wp_remote_post( $url, array(
			'timeout' => 30,
			'headers' => $headers,
			'body' => wp_json_encode( $event_data ),
		) );
		
		// 记录日志
		$this->log_webhook( $webhook, $event_data, $response );
	}
	
	/**
	 * 记录 Webhook 日志
	 *
	 * @param array $webhook    Webhook 配置
	 * @param array $event_data 事件数据
	 * @param mixed $response   响应
	 */
	private function log_webhook( $webhook, $event_data, $response ) {
		$log = get_option( 'wpmcs_webhook_logs', array() );
		
		// 限制日志条数
		$max_entries = 500;
		if ( count( $log ) >= $max_entries ) {
			$log = array_slice( $log, -$max_entries + 1 );
		}
		
		$success = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) >= 200 && wp_remote_retrieve_response_code( $response ) < 300;
		
		$log[] = array(
			'id' => time() . rand( 1000, 9999 ),
			'webhook_id' => $webhook['id'],
			'url' => $webhook['url'],
			'event' => $event_data['event'],
			'payload' => $event_data,
			'response_code' => is_wp_error( $response ) ? 0 : wp_remote_retrieve_response_code( $response ),
			'response_body' => is_wp_error( $response ) ? $response->get_error_message() : wp_remote_retrieve_body( $response ),
			'success' => $success,
			'created_at' => current_time( 'mysql' ),
		);
		
		update_option( 'wpmcs_webhook_logs', $log );
	}
	
	/**
	 * 获取 Webhook 日志
	 *
	 * @param int $limit 限制条数
	 * @return array
	 */
	public function get_logs( $limit = 50 ) {
		$log = get_option( 'wpmcs_webhook_logs', array() );
		
		if ( $limit > 0 ) {
			return array_slice( array_reverse( $log ), 0, $limit );
		}
		
		return array_reverse( $log );
	}
	
	/**
	 * 清空日志
	 */
	public function clear_logs() {
		delete_option( 'wpmcs_webhook_logs' );
	}
	
	/**
	 * 测试 Webhook
	 *
	 * @param array $webhook Webhook 配置
	 * @return array
	 */
	public function test_webhook( $webhook ) {
		$test_data = array(
			'event' => 'test',
			'timestamp' => current_time( 'mysql' ),
			'timestamp_unix' => time(),
			'site_url' => get_site_url(),
			'site_name' => get_bloginfo( 'name' ),
			'payload' => array(
				'message' => 'This is a test webhook',
				'test' => true,
			),
		);
		
		$this->send_webhook( $webhook, $test_data );
		
		return array(
			'success' => true,
			'message' => 'Test webhook sent',
		);
	}
	
	// ========================================
	// 事件触发方法
	// ========================================
	
	/**
	 * 触发文件上传成功事件
	 *
	 * @param int   $attachment_id 附件 ID
	 * @param array $cloud_meta    云端元数据
	 * @param array $metadata      附件元数据
	 */
	public function trigger_file_uploaded( $attachment_id, $cloud_meta, $metadata ) {
		$this->trigger( 'file.uploaded', array(
			'attachment_id' => $attachment_id,
			'filename' => get_the_title( $attachment_id ),
			'mime_type' => get_post_mime_type( $attachment_id ),
			'cloud_url' => isset( $cloud_meta['url'] ) ? $cloud_meta['url'] : '',
			'provider' => isset( $cloud_meta['provider'] ) ? $cloud_meta['provider'] : '',
			'file_size' => isset( $metadata['filesize'] ) ? $metadata['filesize'] : 0,
		) );
	}
	
	/**
	 * 触发文件删除事件
	 *
	 * @param int    $attachment_id 附件 ID
	 * @param string $cloud_key     云端文件路径
	 */
	public function trigger_file_deleted( $attachment_id, $cloud_key ) {
		$this->trigger( 'file.deleted', array(
			'attachment_id' => $attachment_id,
			'cloud_key' => $cloud_key,
		) );
	}
	
	/**
	 * 触发上传错误事件
	 *
	 * @param int    $attachment_id 附件 ID
	 * @param string $error_code    错误代码
	 * @param string $error_message 错误消息
	 */
	public function trigger_upload_error( $attachment_id, $error_code, $error_message ) {
		$this->trigger( 'file.error', array(
			'attachment_id' => $attachment_id,
			'error_code' => $error_code,
			'error_message' => $error_message,
		) );
	}
	
	/**
	 * 触发迁移开始事件
	 */
	public function trigger_migration_started() {
		$this->trigger( 'migration.started', array(
			'started_at' => current_time( 'mysql' ),
		) );
	}
	
	/**
	 * 触发迁移完成事件
	 */
	public function trigger_migration_completed() {
		$status = get_option( 'wpmcs_migration_status', array() );
		
		$this->trigger( 'migration.completed', array(
			'total' => isset( $status['total'] ) ? $status['total'] : 0,
			'success' => isset( $status['success'] ) ? $status['success'] : 0,
			'failed' => isset( $status['failed'] ) ? $status['failed'] : 0,
			'completed_at' => current_time( 'mysql' ),
		) );
	}
	
	/**
	 * 触发迁移失败事件
	 *
	 * @param string $reason 失败原因
	 * @param array  $errors 错误列表
	 */
	public function trigger_migration_failed( $reason, $errors = array() ) {
		$this->trigger( 'migration.failed', array(
			'reason' => $reason,
			'errors' => $errors,
			'failed_at' => current_time( 'mysql' ),
		) );
	}
	
	/**
	 * 触发连接成功事件
	 */
	public function trigger_connection_success() {
		$this->trigger( 'connection.success', array(
			'provider' => isset( $this->settings['provider'] ) ? $this->settings['provider'] : '',
		) );
	}
	
	/**
	 * 触发连接失败事件
	 *
	 * @param string $error_code    错误代码
	 * @param string $error_message 错误消息
	 */
	public function trigger_connection_failed( $error_code, $error_message ) {
		$this->trigger( 'connection.failed', array(
			'provider' => isset( $this->settings['provider'] ) ? $this->settings['provider'] : '',
			'error_code' => $error_code,
			'error_message' => $error_message,
		) );
	}
	
	/**
	 * 触发严重错误事件
	 *
	 * @param string $error_code    错误代码
	 * @param string $error_message 错误消息
	 * @param array  $context       错误上下文
	 */
	public function trigger_critical_error( $error_code, $error_message, $context = array() ) {
		$this->trigger( 'error.critical', array(
			'error_code' => $error_code,
			'error_message' => $error_message,
			'context' => $context,
		) );
	}
	
	/**
	 * 触发存储空间接近限制事件
	 *
	 * @param int $used  已使用空间
	 * @param int $limit 限制空间
	 */
	public function trigger_storage_near_limit( $used, $limit ) {
		$this->trigger( 'storage.near_limit', array(
			'used' => $used,
			'limit' => $limit,
			'percentage' => round( ( $used / $limit ) * 100, 2 ),
		) );
	}
}
