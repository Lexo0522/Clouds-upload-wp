<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * REST API 管理器
 * 
 * 提供外部 API 接口用于集成和扩展
 */
class WPMCS_REST_API {
	
	/**
	 * API 命名空间
	 */
	const API_NAMESPACE = 'wpmcs/v1';
	
	/**
	 * @var array
	 */
	private $settings;
	
	/**
	 * 构造函数
	 *
	 * @param array $settings 插件设置
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}
	
	/**
	 * 注册路由
	 */
	public function register_routes() {
		// 文件操作
		register_rest_route( self::API_NAMESPACE, '/files', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_files' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args' => $this->get_files_args(),
			),
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'upload_file' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args' => $this->upload_file_args(),
			),
		) );
		
		register_rest_route( self::API_NAMESPACE, '/files/(?P<id>\d+)', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_file' ),
				'permission_callback' => array( $this, 'check_permission' ),
				'args' => array(
					'id' => array(
						'required' => true,
						'type' => 'integer',
						'description' => '附件 ID',
					),
				),
			),
			array(
				'methods' => WP_REST_Server::DELETABLE,
				'callback' => array( $this, 'delete_file' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
		) );
		
		// 统计信息
		register_rest_route( self::API_NAMESPACE, '/stats', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_stats' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );
		
		// 服务商信息
		register_rest_route( self::API_NAMESPACE, '/providers', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_providers' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );
		
		// 连接测试
		register_rest_route( self::API_NAMESPACE, '/test-connection', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'test_connection' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );
		
		// 批量操作
		register_rest_route( self::API_NAMESPACE, '/batch/upload', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'batch_upload' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );
		
		register_rest_route( self::API_NAMESPACE, '/batch/migrate', array(
			'methods' => WP_REST_Server::CREATABLE,
			'callback' => array( $this, 'batch_migrate' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );
		
		// Webhook 管理
		register_rest_route( self::API_NAMESPACE, '/webhooks', array(
			array(
				'methods' => WP_REST_Server::READABLE,
				'callback' => array( $this, 'get_webhooks' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
			array(
				'methods' => WP_REST_Server::CREATABLE,
				'callback' => array( $this, 'create_webhook' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
		) );
		
		register_rest_route( self::API_NAMESPACE, '/webhooks/(?P<id>\d+)', array(
			array(
				'methods' => WP_REST_Server::DELETABLE,
				'callback' => array( $this, 'delete_webhook' ),
				'permission_callback' => array( $this, 'check_permission' ),
			),
		) );
		
		// 系统信息
		register_rest_route( self::API_NAMESPACE, '/system/info', array(
			'methods' => WP_REST_Server::READABLE,
			'callback' => array( $this, 'get_system_info' ),
			'permission_callback' => array( $this, 'check_permission' ),
		) );
	}
	
	/**
	 * 检查权限
	 *
	 * @return bool|WP_Error
	 */
	public function check_permission() {
		// 检查 API 是否启用
		$api_enabled = get_option( 'wpmcs_api_enabled', false );
		if ( ! $api_enabled ) {
			return new WP_Error(
				'api_disabled',
				'API 接口未启用',
				array( 'status' => 403 )
			);
		}
		
		// 检查认证
		$auth_method = get_option( 'wpmcs_api_auth_method', 'api_key' );
		
		if ( $auth_method === 'api_key' ) {
			return $this->check_api_key_auth();
		} elseif ( $auth_method === 'jwt' ) {
			return $this->check_jwt_auth();
		} elseif ( $auth_method === 'basic' ) {
			return current_user_can( 'upload_files' );
		}
		
		return new WP_Error(
			'invalid_auth_method',
			'无效的认证方式',
			array( 'status' => 401 )
		);
	}
	
	/**
	 * 检查 API Key 认证
	 *
	 * @return bool|WP_Error
	 */
	private function check_api_key_auth() {
		$api_key = (string) get_option( 'wpmcs_api_key', '' );
		$provided_key = '';

		if ( '' === $api_key ) {
			return new WP_Error(
				'api_key_not_configured',
				'API Key 未配置',
				array( 'status' => 500 )
			);
		}
		
		// 从 Header 获取
		if ( isset( $_SERVER['HTTP_X_WPMCS_API_KEY'] ) ) {
			$provided_key = sanitize_text_field( $_SERVER['HTTP_X_WPMCS_API_KEY'] );
		}
		
		// 从查询参数获取
		if ( empty( $provided_key ) && isset( $_GET['api_key'] ) ) {
			$provided_key = sanitize_text_field( $_GET['api_key'] );
		}
		
		if ( empty( $provided_key ) ) {
			return new WP_Error(
				'missing_api_key',
				'缺少 API Key',
				array( 'status' => 401 )
			);
		}
		
		if ( ! hash_equals( $api_key, $provided_key ) ) {
			return new WP_Error(
				'invalid_api_key',
				'无效的 API Key',
				array( 'status' => 401 )
			);
		}
		
		return true;
	}
	
	/**
	 * 检查 JWT 认证
	 *
	 * @return bool|WP_Error
	 */
	private function check_jwt_auth() {
		// 需要 JWT 插件支持
		if ( ! class_exists( 'JWT_AUTH' ) ) {
			return new WP_Error(
				'jwt_not_available',
				'JWT 认证不可用',
				array( 'status' => 500 )
			);
		}
		
		// JWT 认证逻辑
		return current_user_can( 'upload_files' );
	}
	
	/**
	 * 获取文件列表参数
	 *
	 * @return array
	 */
	private function get_files_args() {
		return array(
			'page' => array(
				'type' => 'integer',
				'default' => 1,
				'description' => '页码',
			),
			'per_page' => array(
				'type' => 'integer',
				'default' => 20,
				'maximum' => 100,
				'description' => '每页数量',
			),
			'status' => array(
				'type' => 'string',
				'enum' => array( 'all', 'uploaded', 'not_uploaded' ),
				'default' => 'all',
				'description' => '上传状态',
			),
			'mime_type' => array(
				'type' => 'string',
				'description' => 'MIME 类型',
			),
			'search' => array(
				'type' => 'string',
				'description' => '搜索关键词',
			),
		);
	}
	
	/**
	 * 上传文件参数
	 *
	 * @return array
	 */
	private function upload_file_args() {
		return array(
			'file' => array(
				'required' => true,
				'type' => 'string',
				'description' => 'Base64 编码的文件内容或文件 URL',
			),
			'filename' => array(
				'type' => 'string',
				'description' => '文件名',
			),
			'mime_type' => array(
				'type' => 'string',
				'description' => 'MIME 类型',
			),
			'async' => array(
				'type' => 'boolean',
				'default' => false,
				'description' => '是否异步上传',
			),
		);
	}
	
	/**
	 * 获取文件列表
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_files( $request ) {
		global $wpdb;
		
		$page = $request->get_param( 'page' );
		$per_page = $request->get_param( 'per_page' );
		$status = $request->get_param( 'status' );
		$mime_type = $request->get_param( 'mime_type' );
		$search = $request->get_param( 'search' );
		
		$offset = ( $page - 1 ) * $per_page;
		
		// 构建查询
		$where = "WHERE post_type = 'attachment' AND post_status = 'inherit'";
		
		if ( $mime_type ) {
			$where .= $wpdb->prepare( " AND post_mime_type LIKE %s", $mime_type . '%' );
		}
		
		if ( $search ) {
			$where .= $wpdb->prepare( " AND post_title LIKE %s", '%' . $search . '%' );
		}
		
		// 状态过滤
		if ( $status === 'uploaded' ) {
			$where .= " AND ID IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_wpmcs_cloud_meta', '_wpmcs_cloud_url'))";
		} elseif ( $status === 'not_uploaded' ) {
			$where .= " AND ID NOT IN (SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_wpmcs_cloud_meta', '_wpmcs_cloud_url'))";
		}
		
		// 获取总数
		$total = $wpdb->get_var( "SELECT COUNT(*) FROM {$wpdb->posts} {$where}" );
		
		// 获取文件
		$files = $wpdb->get_results( $wpdb->prepare(
			"SELECT ID, post_title, post_name, post_mime_type, post_date, guid 
			FROM {$wpdb->posts} 
			{$where}
			ORDER BY post_date DESC
			LIMIT %d OFFSET %d",
			$per_page,
			$offset
		) );
		
		// 格式化结果
		$formatted = array();
		foreach ( $files as $file ) {
			$cloud_meta = get_post_meta( $file->ID, '_wpmcs_cloud_meta', true );
			
			$formatted[] = array(
				'id' => $file->ID,
				'title' => $file->post_title,
				'slug' => $file->post_name,
				'mime_type' => $file->post_mime_type,
				'uploaded_at' => $file->post_date,
				'local_url' => $file->guid,
				'cloud_url' => isset( $cloud_meta['url'] ) ? $cloud_meta['url'] : null,
				'is_uploaded' => ! empty( $cloud_meta ),
				'provider' => isset( $cloud_meta['provider'] ) ? $cloud_meta['provider'] : null,
			);
		}
		
		return new WP_REST_Response( array(
			'success' => true,
			'data' => $formatted,
			'meta' => array(
				'total' => (int) $total,
				'page' => (int) $page,
				'per_page' => (int) $per_page,
				'total_pages' => ceil( $total / $per_page ),
			),
		), 200 );
	}
	
	/**
	 * 获取单个文件
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_file( $request ) {
		$id = $request->get_param( 'id' );
		$attachment = get_post( $id );
		
		if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
			return new WP_Error(
				'file_not_found',
				'文件不存在',
				array( 'status' => 404 )
			);
		}
		
		$cloud_meta = get_post_meta( $id, '_wpmcs_cloud_meta', true );
		$metadata = wp_get_attachment_metadata( $id );
		
		return new WP_REST_Response( array(
			'success' => true,
			'data' => array(
				'id' => $attachment->ID,
				'title' => $attachment->post_title,
				'slug' => $attachment->post_name,
				'mime_type' => $attachment->post_mime_type,
				'uploaded_at' => $attachment->post_date,
				'local_url' => wp_get_attachment_url( $id ),
				'cloud_url' => isset( $cloud_meta['url'] ) ? $cloud_meta['url'] : null,
				'is_uploaded' => ! empty( $cloud_meta ),
				'provider' => isset( $cloud_meta['provider'] ) ? $cloud_meta['provider'] : null,
				'metadata' => $metadata,
				'cloud_meta' => $cloud_meta,
			),
		), 200 );
	}
	
	/**
	 * 上传文件
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function upload_file( $request ) {
		$file_data = $request->get_param( 'file' );
		$filename = $request->get_param( 'filename' );
		$mime_type = $request->get_param( 'mime_type' );
		$async = $request->get_param( 'async' );
		
		// 检查是否是 URL
		if ( filter_var( $file_data, FILTER_VALIDATE_URL ) ) {
			return $this->upload_from_url( $file_data, $filename, $async );
		}
		
		// Base64 上传
		return $this->upload_from_base64( $file_data, $filename, $mime_type, $async );
	}
	
	/**
	 * 从 URL 上传文件
	 *
	 * @param string $url      文件 URL
	 * @param string $filename 文件名
	 * @param bool   $async    是否异步
	 * @return WP_REST_Response|WP_Error
	 */
	private function upload_from_url( $url, $filename, $async ) {
		// 下载文件
		$response = wp_remote_get( $url );
		
		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'download_failed',
				'文件下载失败: ' . $response->get_error_message(),
				array( 'status' => 500 )
			);
		}
		
		$file_content = wp_remote_retrieve_body( $response );
		$content_type = wp_remote_retrieve_header( $response, 'content-type' );
		
		if ( empty( $filename ) ) {
			$parsed_path = parse_url( (string) $url, PHP_URL_PATH );
			$filename = basename( (string) $parsed_path );
		}
		
		// 保存到临时文件
		$temp_file = wp_tempnam( $filename );
		file_put_contents( $temp_file, $file_content );
		
		// WordPress 媒体上传
		$file_array = array(
			'name' => $filename,
			'tmp_name' => $temp_file,
			'type' => $content_type,
			'size' => strlen( $file_content ),
		);
		
		$attachment_id = media_handle_sideload( $file_array, 0 );
		
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $temp_file );
			return new WP_Error(
				'upload_failed',
				'上传失败: ' . $attachment_id->get_error_message(),
				array( 'status' => 500 )
			);
		}
		
		return new WP_REST_Response( array(
			'success' => true,
			'data' => array(
				'attachment_id' => $attachment_id,
				'url' => wp_get_attachment_url( $attachment_id ),
				'async' => $async,
			),
			'message' => '文件上传成功',
		), 201 );
	}
	
	/**
	 * 从 Base64 上传文件
	 *
	 * @param string $base64    Base64 数据
	 * @param string $filename  文件名
	 * @param string $mime_type MIME 类型
	 * @param bool   $async     是否异步
	 * @return WP_REST_Response|WP_Error
	 */
	private function upload_from_base64( $base64, $filename, $mime_type, $async ) {
		// 解码 Base64
		$file_content = base64_decode( $base64 );
		
		if ( $file_content === false ) {
			return new WP_Error(
				'invalid_base64',
				'无效的 Base64 数据',
				array( 'status' => 400 )
			);
		}
		
		if ( empty( $filename ) ) {
			$filename = 'upload-' . date( 'Y-m-d-H-i-s' );
		}
		
		// 保存到临时文件
		$temp_file = wp_tempnam( $filename );
		file_put_contents( $temp_file, $file_content );
		
		$file_array = array(
			'name' => $filename,
			'tmp_name' => $temp_file,
			'type' => $mime_type,
			'size' => strlen( $file_content ),
		);
		
		$attachment_id = media_handle_sideload( $file_array, 0 );
		
		if ( is_wp_error( $attachment_id ) ) {
			@unlink( $temp_file );
			return new WP_Error(
				'upload_failed',
				'上传失败: ' . $attachment_id->get_error_message(),
				array( 'status' => 500 )
			);
		}
		
		return new WP_REST_Response( array(
			'success' => true,
			'data' => array(
				'attachment_id' => $attachment_id,
				'url' => wp_get_attachment_url( $attachment_id ),
				'async' => $async,
			),
			'message' => '文件上传成功',
		), 201 );
	}
	
	/**
	 * 删除文件
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_file( $request ) {
		$id = $request->get_param( 'id' );
		
		$attachment = get_post( $id );
		
		if ( ! $attachment || $attachment->post_type !== 'attachment' ) {
			return new WP_Error(
				'file_not_found',
				'文件不存在',
				array( 'status' => 404 )
			);
		}
		
		$deleted = wp_delete_attachment( $id, true );
		
		if ( ! $deleted ) {
			return new WP_Error(
				'delete_failed',
				'删除失败',
				array( 'status' => 500 )
			);
		}
		
		return new WP_REST_Response( array(
			'success' => true,
			'message' => '文件已删除',
		), 200 );
	}
	
	/**
	 * 获取统计信息
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_stats( $request ) {
		$stats_manager = new WPMCS_Storage_Stats( $this->settings );
		$stats = $stats_manager->get_full_stats();
		
		return new WP_REST_Response( array(
			'success' => true,
			'data' => $stats,
		), 200 );
	}
	
	/**
	 * 获取服务商列表
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_providers( $request ) {
		$providers = WPMCS_Provider_Icons::get_all_providers();
		
		return new WP_REST_Response( array(
			'success' => true,
			'data' => $providers,
		), 200 );
	}
	
	/**
	 * 测试连接
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function test_connection( $request ) {
		$tester = new WPMCS_Connection_Tester( $this->settings );
		$results = $tester->run_full_test();
		
		return new WP_REST_Response( array(
			'success' => $results['success'],
			'data' => $results,
		), 200 );
	}
	
	/**
	 * 批量上传
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function batch_upload( $request ) {
		$attachment_ids = $request->get_param( 'attachment_ids' );
		
		if ( ! is_array( $attachment_ids ) || empty( $attachment_ids ) ) {
			return new WP_Error(
				'invalid_ids',
				'请提供有效的附件 ID 数组',
				array( 'status' => 400 )
			);
		}
		
		// 添加到异步队列
		$async_queue = new WPMCS_Async_Queue( $this->settings );
		$added = 0;
		
		foreach ( $attachment_ids as $id ) {
			$result = $async_queue->add_to_queue( intval( $id ), 5 );
			if ( ! is_wp_error( $result ) ) {
				$added++;
			}
		}
		
		return new WP_REST_Response( array(
			'success' => true,
			'message' => sprintf( '已添加 %d 个文件到上传队列', $added ),
			'data' => array(
				'added' => $added,
				'total' => count( $attachment_ids ),
			),
		), 200 );
	}
	
	/**
	 * 批量迁移
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function batch_migrate( $request ) {
		$migration_manager = new WPMCS_Migration_Manager( $this->settings );
		
		// 启动迁移
		$result = $migration_manager->start_migration( true, false );
		
		return new WP_REST_Response( array(
			'success' => true,
			'message' => '迁移已启动',
			'data' => $result,
		), 200 );
	}
	
	/**
	 * 获取 Webhooks
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_webhooks( $request ) {
		$webhooks = get_option( 'wpmcs_webhooks', array() );
		
		return new WP_REST_Response( array(
			'success' => true,
			'data' => $webhooks,
		), 200 );
	}
	
	/**
	 * 创建 Webhook
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function create_webhook( $request ) {
		$url = $request->get_param( 'url' );
		$events = $request->get_param( 'events' );
		$secret = $request->get_param( 'secret' );
		
		if ( empty( $url ) || ! filter_var( $url, FILTER_VALIDATE_URL ) ) {
			return new WP_Error(
				'invalid_url',
				'请提供有效的 URL',
				array( 'status' => 400 )
			);
		}
		
		$webhooks = get_option( 'wpmcs_webhooks', array() );
		
		$webhook = array(
			'id' => time(),
			'url' => $url,
			'events' => $events ? explode( ',', $events ) : array( 'all' ),
			'secret' => $secret,
			'created_at' => current_time( 'mysql' ),
			'active' => true,
		);
		
		$webhooks[] = $webhook;
		update_option( 'wpmcs_webhooks', $webhooks );
		
		return new WP_REST_Response( array(
			'success' => true,
			'message' => 'Webhook 创建成功',
			'data' => $webhook,
		), 201 );
	}
	
	/**
	 * 删除 Webhook
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response|WP_Error
	 */
	public function delete_webhook( $request ) {
		$id = $request->get_param( 'id' );
		
		$webhooks = get_option( 'wpmcs_webhooks', array() );
		
		$found = false;
		$webhooks = array_filter( $webhooks, function( $webhook ) use ( $id, &$found ) {
			if ( $webhook['id'] == $id ) {
				$found = true;
				return false;
			}
			return true;
		} );
		
		if ( ! $found ) {
			return new WP_Error(
				'webhook_not_found',
				'Webhook 不存在',
				array( 'status' => 404 )
			);
		}
		
		update_option( 'wpmcs_webhooks', array_values( $webhooks ) );
		
		return new WP_REST_Response( array(
			'success' => true,
			'message' => 'Webhook 已删除',
		), 200 );
	}
	
	/**
	 * 获取系统信息
	 *
	 * @param WP_REST_Request $request
	 * @return WP_REST_Response
	 */
	public function get_system_info( $request ) {
		$debug_manager = new WPMCS_Debug_Manager( $this->settings );
		$info = $debug_manager->get_system_info();
		
		return new WP_REST_Response( array(
			'success' => true,
			'data' => $info,
		), 200 );
	}
}
