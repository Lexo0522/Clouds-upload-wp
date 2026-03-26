<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 存储空间统计管理器
 * 
 * 提供云端存储使用量、文件数量和流量使用情况的统计功能
 */
class WPMCS_Storage_Stats {
	
	/**
	 * 统计数据选项名称
	 */
	const STATS_OPTION = 'wpmcs_storage_stats';
	
	/**
	 * 流量记录表名
	 */
	const TRAFFIC_TABLE = 'wpmcs_traffic_log';
	
	/**
	 * @var array
	 */
	private $settings;
	
	/**
	 * 数据库表名
	 */
	private $traffic_table;
	
	public function __construct( array $settings ) {
		$this->settings = $settings;
		
		global $wpdb;
		$this->traffic_table = $wpdb->prefix . self::TRAFFIC_TABLE;
	}
	
	/**
	 * 注册钩子
	 */
	public function register_hooks() {
		add_action( 'admin_menu', array( $this, 'add_menu_page' ) );
		add_action( 'wp_ajax_wpmcs_get_storage_stats', array( $this, 'ajax_get_stats' ) );
		add_action( 'wp_ajax_wpmcs_refresh_storage_stats', array( $this, 'ajax_refresh_stats' ) );
		
		// 记录流量
		add_filter( 'wp_get_attachment_url', array( $this, 'log_traffic' ), 20, 2 );
		add_action( 'template_redirect', array( $this, 'handle_tracking_request' ), 0 );
		
		// 定时更新统计
		add_action( 'wpmcs_update_storage_stats', array( $this, 'update_stats' ) );
		
		if ( ! wp_next_scheduled( 'wpmcs_update_storage_stats' ) ) {
			wp_schedule_event( time(), 'hourly', 'wpmcs_update_storage_stats' );
		}
	}
	
	/**
	 * 添加菜单页面
	 */
	public function add_menu_page() {
		add_submenu_page(
			WPMCS_Admin_Page::MENU_SLUG,
			'存储统计',
			'云存储统计',
			'manage_options',
			'wpmcs-stats',
			array( $this, 'render_page' )
		);
	}
	
	/**
	 * 渲染统计页面
	 */
	public function render_page() {
		wp_enqueue_style(
			'wpmcs-stats',
			WPMCS_PLUGIN_URL . 'assets/css/stats.css',
			array(),
			WPMCS_VERSION
		);
		
		wp_enqueue_script(
			'wpmcs-stats',
			WPMCS_PLUGIN_URL . 'assets/js/stats.js',
			array(),
			WPMCS_VERSION,
			true
		);
		
		wp_localize_script( 'wpmcs-stats', 'wpmcsStats', array(
			'ajax_url' => admin_url( 'admin-ajax.php' ),
			'nonce' => wp_create_nonce( 'wpmcs_stats' ),
		) );
		
		include WPMCS_PLUGIN_DIR . 'admin/views/stats-page.php';
	}
	
	/**
	 * 获取完整的统计数据
	 */
	public function get_full_stats() {
		$stats = array(
			'storage' => $this->get_storage_usage(),
			'files' => $this->get_file_stats(),
			'traffic' => $this->get_traffic_stats(),
			'providers' => $this->get_provider_stats(),
			'updated_at' => current_time( 'mysql' ),
		);
		
		return $stats;
	}
	
	/**
	 * 获取存储使用量
	 */
	public function get_storage_usage() {
		global $wpdb;
		
		// 本地存储使用量
		$uploads_dir = wpmcs_get_upload_dir();
		$local_size = 0;
		$uploads_base_dir = isset( $uploads_dir['basedir'] ) ? (string) $uploads_dir['basedir'] : '';
		if ( '' !== $uploads_base_dir && is_dir( $uploads_base_dir ) ) {
			$local_size = $this->get_directory_size( $uploads_base_dir );
		}
		
		// 云端存储使用量（从缓存或实时获取）
		$cached_stats = get_option( self::STATS_OPTION, array() );
		$cloud_size = isset( $cached_stats['storage']['cloud_size'] ) ? (int) $cached_stats['storage']['cloud_size'] : 0;

		// 统计所有云端附件的总大小
		$cloud_files_size = (int) $wpdb->get_var(
			"SELECT COALESCE(SUM(CAST(meta_value AS UNSIGNED)), 0) FROM {$wpdb->postmeta} 
			WHERE meta_key = '_wpmcs_file_size'"
		);

		if ( $cloud_files_size > 0 ) {
			$cloud_size = $cloud_files_size;
		} elseif ( 0 === $cloud_size ) {
			$cloud_attachment_ids = $wpdb->get_col(
				"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_wpmcs_cloud_meta', '_wpmcs_cloud_url')"
			);

			if ( ! empty( $cloud_attachment_ids ) ) {
				foreach ( $cloud_attachment_ids as $attachment_id ) {
					$attachment_id = (int) $attachment_id;
					$file_size = (int) get_post_meta( $attachment_id, '_wpmcs_file_size', true );

					if ( $file_size <= 0 ) {
						$cloud_meta = class_exists( 'WPMCS_Attachment_Manager' )
							? WPMCS_Attachment_Manager::get_cloud_meta( $attachment_id )
							: get_post_meta( $attachment_id, '_wpmcs_cloud_meta', true );
						if ( is_array( $cloud_meta ) && ! empty( $cloud_meta['file_size'] ) ) {
							$file_size = (int) $cloud_meta['file_size'];
						}
					}

					if ( $file_size <= 0 ) {
						$file_path = get_attached_file( $attachment_id );
						if ( $file_path && file_exists( $file_path ) ) {
							$file_size = (int) filesize( $file_path );
						}
					}

					if ( $file_size > 0 ) {
						$cloud_size += $file_size;
					}
				}
			}
		}
		$cloud_size_formatted = size_format( $cloud_size );

		$saved_size = $local_size - $cloud_size;
		if ( $saved_size < 0 ) {
			$saved_size = 0;
		}
		
		return array(
			'local_size' => $local_size,
			'local_size_formatted' => size_format( $local_size ),
			'cloud_size' => $cloud_size,
			'cloud_size_formatted' => size_format( $cloud_size ),
			'saved_size' => $saved_size,
			'saved_percentage' => $local_size > 0 ? min( 100, round( ( $cloud_size / $local_size ) * 100, 2 ) ) : 0,
		);
	}
	
	/**
	 * 获取文件统计
	 */
	public function get_file_stats() {
		global $wpdb;
		
		// 总附件数
		$total_attachments = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'"
		);
		
		// 已上传到云端的附件数
		$cloud_attachments = (int) $wpdb->get_var(
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} 
			WHERE meta_key IN ('_wpmcs_cloud_meta', '_wpmcs_cloud_url')"
		);
		
		// 按文件类型统计
		$by_type = $wpdb->get_results(
			"SELECT post_mime_type, COUNT(*) as count 
			FROM {$wpdb->posts} 
			WHERE post_type = 'attachment' 
			GROUP BY post_mime_type"
		);
		
		$file_types = array();
		if ( $by_type ) {
			foreach ( $by_type as $row ) {
				$type = $row->post_mime_type ? explode( '/', $row->post_mime_type )[0] : 'other';
				if ( ! isset( $file_types[ $type ] ) ) {
					$file_types[ $type ] = 0;
				}
				$file_types[ $type ] += (int) $row->count;
			}
		}
		
		// 按月份统计
		$by_month = $wpdb->get_results(
			"SELECT DATE_FORMAT(post_date, '%Y-%m') as month, COUNT(*) as count 
			FROM {$wpdb->posts} 
			WHERE post_type = 'attachment' 
			AND post_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
			GROUP BY month 
			ORDER BY month DESC"
		);
		
		$monthly_stats = array();
		if ( $by_month ) {
			foreach ( $by_month as $row ) {
				$monthly_stats[ $row->month ] = (int) $row->count;
			}
		}
		
		return array(
			'total' => $total_attachments,
			'cloud' => $cloud_attachments,
			'local' => $total_attachments - $cloud_attachments,
			'upload_percentage' => $total_attachments > 0 ? round( ( $cloud_attachments / $total_attachments ) * 100, 2 ) : 0,
			'by_type' => $file_types,
			'by_month' => $monthly_stats,
		);
	}
	
	/**
	 * 获取流量统计
	 */
	public function get_traffic_stats() {
		global $wpdb;
		
		// 检查流量表是否存在
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$this->traffic_table}'" ) !== $this->traffic_table ) {
			return $this->get_empty_traffic_stats();
		}
		
		// 今日流量
		$now = current_time( 'timestamp' );
		$today = wp_date( 'Y-m-d', $now );
		$today_traffic = $wpdb->get_row( $wpdb->prepare(
			"SELECT COALESCE(SUM(bytes), 0) as bytes, COUNT(*) as requests 
			FROM {$this->traffic_table} 
			WHERE DATE(created_at) = %s",
			$today
		) );
		$today_traffic = $today_traffic ? $today_traffic : (object) array( 'bytes' => 0, 'requests' => 0 );
		
		// 本周流量
		$week_start = wp_date( 'Y-m-d', strtotime( 'monday this week', $now ) );
		$week_traffic = $wpdb->get_row( $wpdb->prepare(
			"SELECT COALESCE(SUM(bytes), 0) as bytes, COUNT(*) as requests 
			FROM {$this->traffic_table} 
			WHERE DATE(created_at) >= %s",
			$week_start
		) );
		$week_traffic = $week_traffic ? $week_traffic : (object) array( 'bytes' => 0, 'requests' => 0 );
		
		// 本月流量
		$month_start = wp_date( 'Y-m-01', $now );
		$thirty_days_ago = wp_date( 'Y-m-d H:i:s', strtotime( '-30 days', $now ) );
		$month_traffic = $wpdb->get_row( $wpdb->prepare(
			"SELECT COALESCE(SUM(bytes), 0) as bytes, COUNT(*) as requests 
			FROM {$this->traffic_table} 
			WHERE DATE(created_at) >= %s",
			$month_start
		) );
		$month_traffic = $month_traffic ? $month_traffic : (object) array( 'bytes' => 0, 'requests' => 0 );
		
		// 按天统计（最近 30 天）
		$daily_traffic = $wpdb->get_results( $wpdb->prepare(
			"SELECT DATE(created_at) as date, COALESCE(SUM(bytes), 0) as bytes, COUNT(*) as requests 
			FROM {$this->traffic_table} 
			WHERE created_at >= %s
			GROUP BY DATE(created_at) 
			ORDER BY date DESC",
			$thirty_days_ago
		) );
		
		$daily_stats = array();
		if ( $daily_traffic ) {
			foreach ( $daily_traffic as $row ) {
				$daily_stats[ $row->date ] = array(
					'bytes' => (int) $row->bytes,
					'bytes_formatted' => size_format( $row->bytes ),
					'requests' => (int) $row->requests,
				);
			}
		}
		
		// 按文件类型统计流量
		$by_type = $wpdb->get_results( $wpdb->prepare(
			"SELECT file_type, COALESCE(SUM(bytes), 0) as bytes, COUNT(*) as requests 
			FROM {$this->traffic_table} 
			WHERE created_at >= %s
			GROUP BY file_type 
			ORDER BY bytes DESC",
			$thirty_days_ago
		) );
		
		$type_stats = array();
		if ( $by_type ) {
			foreach ( $by_type as $row ) {
				$type_stats[ $row->file_type ] = array(
					'bytes' => (int) $row->bytes,
					'bytes_formatted' => size_format( $row->bytes ),
					'requests' => (int) $row->requests,
				);
			}
		}
		
		return array(
			'today' => array(
				'bytes' => (int) $today_traffic->bytes,
				'bytes_formatted' => size_format( $today_traffic->bytes ),
				'requests' => (int) $today_traffic->requests,
			),
			'week' => array(
				'bytes' => (int) $week_traffic->bytes,
				'bytes_formatted' => size_format( $week_traffic->bytes ),
				'requests' => (int) $week_traffic->requests,
			),
			'month' => array(
				'bytes' => (int) $month_traffic->bytes,
				'bytes_formatted' => size_format( $month_traffic->bytes ),
				'requests' => (int) $month_traffic->requests,
			),
			'daily' => $daily_stats,
			'by_type' => $type_stats,
		);
	}
	
	/**
	 * 获取服务商统计
	 */
	public function get_provider_stats() {
		global $wpdb;
		
		$provider = isset( $this->settings['provider'] ) ? $this->settings['provider'] : 'unknown';
		
		$stats = array(
			'name' => $this->get_provider_name( $provider ),
			'key' => $provider,
			'bucket' => isset( $this->settings['bucket'] ) ? $this->settings['bucket'] : '',
			'region' => isset( $this->settings['region'] ) ? $this->settings['region'] : '',
			'domain' => isset( $this->settings['domain'] ) ? $this->settings['domain'] : '',
		);
		
		return $stats;
	}
	
	/**
	 * 记录流量
	 */
	public function log_traffic( $url, $attachment_id ) {
		// 只为云端附件生成跟踪入口，真实记录在跟踪入口被访问时完成
		if ( is_admin() || empty( $this->settings['replace_url'] ) ) {
			return $url;
		}

		$cloud_meta = WPMCS_Attachment_Manager::get_cloud_meta( $attachment_id );
		if ( ! $cloud_meta || empty( $cloud_meta['url'] ) ) {
			return $url;
		}

		return WPMCS_Attachment_Manager::get_tracking_url( $attachment_id, 'full' );
	}

	/**
	 * 处理媒体访问跟踪入口。
	 */
	public function handle_tracking_request() {
		if ( empty( $_GET['wpmcs_track'] ) ) {
			return;
		}

		$attachment_id = isset( $_GET['attachment_id'] ) ? absint( $_GET['attachment_id'] ) : 0;
		$size = isset( $_GET['size'] ) ? sanitize_key( $_GET['size'] ) : 'full';

		if ( $attachment_id <= 0 ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		$cloud_meta = WPMCS_Attachment_Manager::get_cloud_meta( $attachment_id );
		$target_url = $this->get_cloud_target_url( $attachment_id, $size, $cloud_meta );

		if ( empty( $target_url ) ) {
			status_header( 404 );
			nocache_headers();
			exit;
		}

		$this->record_traffic( $attachment_id, $size, $cloud_meta );

		nocache_headers();
		wp_redirect( $target_url, 302 );
		exit;
	}

	/**
	 * 记录一次媒体访问。
	 */
	private function record_traffic( $attachment_id, $size, array $cloud_meta = array() ) {
		$this->maybe_create_traffic_table();

		$file_size = $this->get_tracking_file_size( $attachment_id, $size, $cloud_meta );
		$mime_type = get_post_mime_type( $attachment_id );
		$file_type = $mime_type ? explode( '/', $mime_type )[0] : 'other';

		global $wpdb;

		$wpdb->insert(
			$this->traffic_table,
			array(
				'attachment_id' => $attachment_id,
				'file_type' => $file_type,
				'bytes' => $file_size,
				'ip_address' => $this->get_ip_address(),
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ), 0, 255 ) : '',
				'created_at' => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%s', '%s', '%s' )
		);
		delete_option( self::STATS_OPTION );
	}

	/**
	 * 获取跟踪请求应跳转到的云端 URL。
	 */
	private function get_cloud_target_url( $attachment_id, $size, array $cloud_meta = array() ) {
		if ( empty( $cloud_meta ) ) {
			$cloud_meta = WPMCS_Attachment_Manager::get_cloud_meta( $attachment_id );
		}

		if ( empty( $cloud_meta['url'] ) ) {
			return '';
		}

		if ( 'full' !== $size && ! empty( $cloud_meta['sizes'][ $size ]['url'] ) ) {
			return $cloud_meta['sizes'][ $size ]['url'];
		}

		return $cloud_meta['url'];
	}

	/**
	 * 估算请求的文件大小。
	 */
	private function get_tracking_file_size( $attachment_id, $size, array $cloud_meta = array() ) {
		$size = sanitize_key( (string) $size );
		$file_size = (int) get_post_meta( $attachment_id, '_wpmcs_file_size', true );

		if ( 'full' !== $size ) {
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( is_array( $metadata ) && ! empty( $metadata['sizes'][ $size ]['file'] ) ) {
				$file_path = get_attached_file( $attachment_id );
				if ( $file_path ) {
					$size_path = trailingslashit( dirname( $file_path ) ) . $metadata['sizes'][ $size ]['file'];
					if ( file_exists( $size_path ) ) {
						return (int) filesize( $size_path );
					}
				}
			}
		}

		if ( $file_size <= 0 ) {
			$file_path = get_attached_file( $attachment_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$file_size = (int) filesize( $file_path );
			}
		}

		if ( $file_size <= 0 && ! empty( $cloud_meta['file_size'] ) ) {
			$file_size = (int) $cloud_meta['file_size'];
		}

		return max( 0, $file_size );
	}
	
	/**
	 * 创建流量表
	 */
	private function maybe_create_traffic_table() {
		global $wpdb;
		
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$this->traffic_table}'" ) === $this->traffic_table ) {
			return;
		}
		
		$charset_collate = $wpdb->get_charset_collate();
		
		$sql = "CREATE TABLE {$this->traffic_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			attachment_id bigint(20) unsigned NOT NULL,
			file_type varchar(50) DEFAULT 'other',
			bytes bigint(20) unsigned NOT NULL DEFAULT 0,
			ip_address varchar(45) DEFAULT '',
			user_agent varchar(255) DEFAULT '',
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY attachment_id (attachment_id),
			KEY file_type (file_type),
			KEY created_at (created_at)
		) {$charset_collate};";
		
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
	
	/**
	 * 更新统计
	 */
	public function update_stats() {
		$stats = $this->get_full_stats();
		update_option( self::STATS_OPTION, $stats );
	}
	
	/**
	 * AJAX 获取统计
	 */
	public function ajax_get_stats() {
		check_ajax_referer( 'wpmcs_stats', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		// 从缓存获取
		$stats = get_option( self::STATS_OPTION, array() );
		
		if ( empty( $stats ) ) {
			$stats = $this->get_full_stats();
			update_option( self::STATS_OPTION, $stats );
		}
		
		wp_send_json_success( $stats );
	}
	
	/**
	 * AJAX 刷新统计
	 */
	public function ajax_refresh_stats() {
		check_ajax_referer( 'wpmcs_stats', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$stats = $this->get_full_stats();
		update_option( self::STATS_OPTION, $stats );
		
		wp_send_json_success( $stats );
	}
	
	/**
	 * 获取目录大小
	 */
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
	 * 获取客户端 IP 地址
	 */
	private function get_ip_address() {
		$ip = '';
		
		if ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = sanitize_text_field( $_SERVER['HTTP_CLIENT_IP'] );
		} elseif ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$ip = sanitize_text_field( $_SERVER['HTTP_X_FORWARDED_FOR'] );
		} elseif ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) {
			$ip = sanitize_text_field( $_SERVER['REMOTE_ADDR'] );
		}
		
		return substr( $ip, 0, 45 );
	}
	
	/**
	 * 获取服务商名称
	 */
	private function get_provider_name( $provider ) {
		$names = array(
			'qiniu' => '七牛云',
			'aliyun_oss' => '阿里云 OSS',
			'tencent_cos' => '腾讯云 COS',
			'upyun' => '又拍云',
			'dogecloud' => '多吉云',
			'aws_s3' => 'AWS S3',
		);
		
		return isset( $names[ $provider ] ) ? $names[ $provider ] : '未知';
	}
	
	/**
	 * 获取空的流量统计
	 */
	private function get_empty_traffic_stats() {
		return array(
			'today' => array(
				'bytes' => 0,
				'bytes_formatted' => '0 B',
				'requests' => 0,
			),
			'week' => array(
				'bytes' => 0,
				'bytes_formatted' => '0 B',
				'requests' => 0,
			),
			'month' => array(
				'bytes' => 0,
				'bytes_formatted' => '0 B',
				'requests' => 0,
			),
			'daily' => array(),
			'by_type' => array(),
		);
	}
	
	/**
	 * 清理旧流量记录
	 */
	public function cleanup_old_traffic( $days = 90 ) {
		global $wpdb;
		
		if ( $wpdb->get_var( "SHOW TABLES LIKE '{$this->traffic_table}'" ) !== $this->traffic_table ) {
			return 0;
		}
		
		$date = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		
		return $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$this->traffic_table} WHERE created_at < %s",
			$date
		) );
	}
}
