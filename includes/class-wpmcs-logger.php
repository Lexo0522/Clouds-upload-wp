<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 日志管理器
 * 
 * 提供完整的日志记录、查询和分析功能
 */
class WPMCS_Logger {
	
	/**
	 * 日志表名
	 */
	const TABLE_NAME = 'wpmcs_logs';
	
	/**
	 * 日志级别
	 */
	const LEVEL_DEBUG = 'debug';
	const LEVEL_INFO = 'info';
	const LEVEL_WARNING = 'warning';
	const LEVEL_ERROR = 'error';
	const LEVEL_CRITICAL = 'critical';
	
	/**
	 * 日志类型
	 */
	const TYPE_UPLOAD = 'upload';
	const TYPE_DELETE = 'delete';
	const TYPE_MIGRATION = 'migration';
	const TYPE_QUEUE = 'queue';
	const TYPE_CACHE = 'cache';
	const TYPE_SYSTEM = 'system';
	
	/**
	 * 是否启用日志
	 */
	private $enabled = true;
	
	/**
	 * 日志保留天数
	 */
	private $retention_days = 30;
	
	/**
	 * 数据库表名
	 */
	private $table_name;
	
	public function __construct( ?array $settings = null ) {
		global $wpdb;
		$this->table_name = $wpdb->prefix . self::TABLE_NAME;

		if ( null === $settings ) {
			$settings = function_exists( 'wpmcs_get_settings' ) ? wpmcs_get_settings() : array();
		}

		$this->enabled = ! empty( $settings['enable_logging'] );

		if ( ! $this->table_exists() ) {
			$this->create_table();
		}
	}

	/**
	 * @param bool $enabled
	 * @return void
	 */
	public function set_enabled( $enabled ) {
		$this->enabled = (bool) $enabled;
	}
	
	/**
	 * 创建日志表
	 */
	public function create_table() {
		global $wpdb;
		
		$charset_collate = $wpdb->get_charset_collate();
		
		$sql = "CREATE TABLE {$this->table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			level varchar(20) NOT NULL DEFAULT 'info',
			type varchar(50) NOT NULL DEFAULT 'system',
			message text NOT NULL,
			context longtext,
			attachment_id bigint(20) unsigned DEFAULT NULL,
			user_id bigint(20) unsigned DEFAULT NULL,
			ip_address varchar(45) DEFAULT NULL,
			user_agent varchar(255) DEFAULT NULL,
			request_uri text,
			created_at datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY level (level),
			KEY type (type),
			KEY attachment_id (attachment_id),
			KEY created_at (created_at)
		) {$charset_collate};";
		
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}
	
	/**
	 * 记录日志
	 *
	 * @param string $level 日志级别
	 * @param string $type 日志类型
	 * @param string $message 日志消息
	 * @param array $context 上下文数据
	 * @param int|null $attachment_id 附件 ID
	 * @return bool
	 */
	public function log( $level, $type, $message, $context = array(), $attachment_id = null ) {
		if ( ! $this->enabled ) {
			return false;
		}

		global $wpdb;

		// 过滤敏感信息
		$context = $this->sanitize_context( $context );

		$data = array(
			'level'      => sanitize_text_field( $level ),
			'type'       => sanitize_text_field( $type ),
			'message'    => sanitize_textarea_field( $message ),
			'context'    => wp_json_encode( $context ),
			'attachment_id' => $attachment_id ? intval( $attachment_id ) : null,
			'user_id'    => get_current_user_id(),
			'ip_address' => $this->get_ip_address(),
			'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] ) ? substr( sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ), 0, 255 ) : '',
			'request_uri' => isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( $_SERVER['REQUEST_URI'] ) : '',
			'created_at' => current_time( 'mysql' ),
		);

		$result = $wpdb->insert( $this->table_name, $data );

		// 如果是错误或更高级别，触发通知
		if ( $result && in_array( $level, array( self::LEVEL_ERROR, self::LEVEL_CRITICAL ) ) ) {
			$this->trigger_error_notification( $level, $type, $message, $context );
		}

		return false !== $result;
	}

	/**
	 * 清理上下文中的敏感信息
	 *
	 * @param array $context 原始上下文
	 * @return array 清理后的上下文
	 */
	private function sanitize_context( $context ) {
		if ( ! is_array( $context ) ) {
			return $context;
		}

		$sensitive_keys = array(
			'access_key',
			'secret_key',
			'secret_id',
			'password',
			'token',
			'authorization',
			'api_key',
			'private_key',
		);

		foreach ( $context as $key => $value ) {
			// 检查是否是敏感字段
			$key_lower = strtolower( $key );
			foreach ( $sensitive_keys as $sensitive ) {
				if ( strpos( $key_lower, $sensitive ) !== false ) {
					// 隐藏敏感值
					if ( is_string( $value ) && strlen( $value ) > 8 ) {
						$context[ $key ] = substr( $value, 0, 4 ) . '***' . substr( $value, -4 );
					} else {
						$context[ $key ] = '***HIDDEN***';
					}
					break;
				}
			}

			// 递归处理嵌套数组
			if ( is_array( $context[ $key ] ) ) {
				$context[ $key ] = $this->sanitize_context( $context[ $key ] );
			}
		}

		return $context;
	}
	
	/**
	 * 记录调试日志
	 */
	public function debug( $type, $message, $context = array(), $attachment_id = null ) {
		return $this->log( self::LEVEL_DEBUG, $type, $message, $context, $attachment_id );
	}
	
	/**
	 * 记录信息日志
	 */
	public function info( $type, $message, $context = array(), $attachment_id = null ) {
		return $this->log( self::LEVEL_INFO, $type, $message, $context, $attachment_id );
	}
	
	/**
	 * 记录警告日志
	 */
	public function warning( $type, $message, $context = array(), $attachment_id = null ) {
		return $this->log( self::LEVEL_WARNING, $type, $message, $context, $attachment_id );
	}
	
	/**
	 * 记录错误日志
	 */
	public function error( $type, $message, $context = array(), $attachment_id = null ) {
		return $this->log( self::LEVEL_ERROR, $type, $message, $context, $attachment_id );
	}
	
	/**
	 * 记录严重错误日志
	 */
	public function critical( $type, $message, $context = array(), $attachment_id = null ) {
		return $this->log( self::LEVEL_CRITICAL, $type, $message, $context, $attachment_id );
	}
	
	/**
	 * 查询日志
	 * 
	 * @param array $args 查询参数
	 * @return array
	 */
	public function get_logs( $args = array() ) {
		global $wpdb;
		if ( ! $this->table_exists() ) {
			return array(
				'logs' => array(),
				'total' => 0,
				'pages' => 0,
				'page' => 1,
			);
		}
		
		$defaults = array(
			'level' => '',
			'type' => '',
			'attachment_id' => '',
			'user_id' => '',
			'date_from' => '',
			'date_to' => '',
			'search' => '',
			'orderby' => 'created_at',
			'order' => 'DESC',
			'per_page' => 50,
			'page' => 1,
		);
		
		$args = wp_parse_args( $args, $defaults );
		
		$where = array( '1=1' );
		$prepare_values = array();
		
		// 级别筛选
		if ( ! empty( $args['level'] ) ) {
			$where[] = 'level = %s';
			$prepare_values[] = $args['level'];
		}
		
		// 类型筛选
		if ( ! empty( $args['type'] ) ) {
			$where[] = 'type = %s';
			$prepare_values[] = $args['type'];
		}
		
		// 附件 ID
		if ( ! empty( $args['attachment_id'] ) ) {
			$where[] = 'attachment_id = %d';
			$prepare_values[] = intval( $args['attachment_id'] );
		}
		
		// 用户 ID
		if ( ! empty( $args['user_id'] ) ) {
			$where[] = 'user_id = %d';
			$prepare_values[] = intval( $args['user_id'] );
		}
		
		// 日期范围
		if ( ! empty( $args['date_from'] ) ) {
			$where[] = 'created_at >= %s';
			$prepare_values[] = $args['date_from'] . ' 00:00:00';
		}
		
		if ( ! empty( $args['date_to'] ) ) {
			$where[] = 'created_at <= %s';
			$prepare_values[] = $args['date_to'] . ' 23:59:59';
		}
		
		// 搜索
		if ( ! empty( $args['search'] ) ) {
			$where[] = '(message LIKE %s OR context LIKE %s)';
			$search_term = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$prepare_values[] = $search_term;
			$prepare_values[] = $search_term;
		}
		
		// WHERE 子句
		$where_clause = implode( ' AND ', $where );
		
		// 排序
		$orderby = in_array( $args['orderby'], array( 'id', 'level', 'type', 'created_at' ) ) ? $args['orderby'] : 'created_at';
		$order = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';
		
		// 分页
		$per_page = max( 1, intval( $args['per_page'] ) );
		$page = max( 1, intval( $args['page'] ) );
		$offset = ( $page - 1 ) * $per_page;
		
		// 查询总数
		$count_sql = "SELECT COUNT(*) FROM {$this->table_name} WHERE {$where_clause}";
		if ( ! empty( $prepare_values ) ) {
			$count_sql = $wpdb->prepare( $count_sql, $prepare_values );
		}
		$total = $wpdb->get_var( $count_sql );
		
		// 查询数据
		$sql = "SELECT * FROM {$this->table_name} WHERE {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";
		$prepare_values[] = $per_page;
		$prepare_values[] = $offset;
		
		$logs = $wpdb->get_results( $wpdb->prepare( $sql, $prepare_values ) );
		
		// 解析上下文
		if ( $logs ) {
			foreach ( $logs as &$log ) {
				$log->context = json_decode( $log->context, true );
			}
		}
		
		return array(
			'logs' => $logs ? $logs : array(),
			'total' => intval( $total ),
			'pages' => ceil( $total / $per_page ),
			'page' => $page,
		);
	}
	
	/**
	 * 获取日志统计
	 */
	public function get_stats( $days = 7 ) {
		global $wpdb;
		if ( ! $this->table_exists() ) {
			return array(
				'total' => 0,
				'by_level' => array(),
				'by_type' => array(),
				'recent_errors' => 0,
			);
		}
		
		$date_from = date( 'Y-m-d H:i:s', strtotime( "-{$days} days" ) );
		
		$stats = array(
			'total' => 0,
			'by_level' => array(),
			'by_type' => array(),
			'recent_errors' => 0,
		);
		
		// 总数
		$stats['total'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE created_at >= %s",
			$date_from
		) );
		
		// 按级别统计
		$by_level = $wpdb->get_results( $wpdb->prepare(
			"SELECT level, COUNT(*) as count FROM {$this->table_name} WHERE created_at >= %s GROUP BY level",
			$date_from
		) );
		
		if ( $by_level ) {
			foreach ( $by_level as $row ) {
				$stats['by_level'][ $row->level ] = intval( $row->count );
			}
		}
		
		// 按类型统计
		$by_type = $wpdb->get_results( $wpdb->prepare(
			"SELECT type, COUNT(*) as count FROM {$this->table_name} WHERE created_at >= %s GROUP BY type",
			$date_from
		) );
		
		if ( $by_type ) {
			foreach ( $by_type as $row ) {
				$stats['by_type'][ $row->type ] = intval( $row->count );
			}
		}
		
		// 最近错误数（24小时内）
		$stats['recent_errors'] = (int) $wpdb->get_var( $wpdb->prepare(
			"SELECT COUNT(*) FROM {$this->table_name} WHERE level IN ('error', 'critical') AND created_at >= %s",
			date( 'Y-m-d H:i:s', strtotime( '-24 hours' ) )
		) );
		
		return $stats;
	}
	
	/**
	 * 清理过期日志
	 */
	public function cleanup_old_logs() {
		global $wpdb;
		
		$date = date( 'Y-m-d H:i:s', strtotime( "-{$this->retention_days} days" ) );
		
		$deleted = $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$this->table_name} WHERE created_at < %s",
			$date
		) );
		
		return $deleted;
	}
	
	/**
	 * 清空所有日志
	 */
	public function clear_all() {
		global $wpdb;
		if ( ! $this->table_exists() ) {
			return true;
		}
		
		return $wpdb->query( "TRUNCATE TABLE {$this->table_name}" );
	}

	/**
	 * Check whether the log table exists.
	 *
	 * @return bool
	 */
	private function table_exists() {
		global $wpdb;
		$found = $wpdb->get_var( $wpdb->prepare(
			'SHOW TABLES LIKE %s',
			$wpdb->esc_like( $this->table_name )
		) );
		return $found === $this->table_name;
	}
	
	/**
	 * 错误分析和建议
	 * 
	 * @return array
	 */
	public function analyze_errors() {
		$analysis = array(
			'summary' => '',
			'issues' => array(),
			'recommendations' => array(),
		);
		
		// 获取最近 7 天的错误统计
		$stats = $this->get_stats( 7 );
		
		// 分析错误模式
		$error_count = isset( $stats['by_level']['error'] ) ? $stats['by_level']['error'] : 0;
		$critical_count = isset( $stats['by_level']['critical'] ) ? $stats['by_level']['critical'] : 0;
		
		// 总结
		if ( $critical_count > 0 ) {
			$analysis['summary'] = sprintf(
				'发现 %d 个严重错误和 %d 个普通错误，需要立即处理。',
				$critical_count,
				$error_count
			);
		} elseif ( $error_count > 0 ) {
			$analysis['summary'] = sprintf(
				'过去 7 天共记录 %d 个错误，建议检查并修复。',
				$error_count
			);
		} else {
			$analysis['summary'] = '系统运行正常，未发现错误。';
		}
		
		// 分析具体问题
		if ( isset( $stats['by_type']['upload'] ) && $stats['by_type']['upload'] > 10 ) {
			$analysis['issues'][] = array(
				'type' => 'upload',
				'severity' => 'high',
				'message' => '上传失败次数较多，可能存在配置问题',
			);
			$analysis['recommendations'][] = '检查云存储配置是否正确';
			$analysis['recommendations'][] = '验证网络连接和防火墙设置';
			$analysis['recommendations'][] = '确认云服务商 API 密钥有效';
		}
		
		if ( isset( $stats['by_type']['queue'] ) && $stats['by_type']['queue'] > 5 ) {
			$analysis['issues'][] = array(
				'type' => 'queue',
				'severity' => 'medium',
				'message' => '队列处理失败，异步上传可能受影响',
			);
			$analysis['recommendations'][] = '检查 WordPress Cron 是否正常工作';
			$analysis['recommendations'][] = '增加 PHP 内存限制';
			$analysis['recommendations'][] = '检查队列锁定状态';
		}
		
		if ( $stats['recent_errors'] > 5 ) {
			$analysis['issues'][] = array(
				'type' => 'recent',
				'severity' => 'high',
				'message' => '最近 24 小时错误较多，系统可能不稳定',
			);
			$analysis['recommendations'][] = '立即查看详细日志';
			$analysis['recommendations'][] = '考虑暂时禁用异步上传';
			$analysis['recommendations'][] = '联系技术支持';
		}
		
		return $analysis;
	}
	
	/**
	 * 触发错误通知
	 */
	private function trigger_error_notification( $level, $type, $message, $context ) {
		// 检查是否启用了通知
		$settings = wpmcs_get_settings();
		
		if ( empty( $settings['error_notification'] ) ) {
			return;
		}
		
		// 发送邮件通知
		$subject = sprintf( '[%s] WPMCS 错误通知: %s', get_bloginfo( 'name' ), $type );
		
		$body = "检测到以下错误:\n\n";
		$body .= "级别: {$level}\n";
		$body .= "类型: {$type}\n";
		$body .= "时间: " . current_time( 'mysql' ) . "\n";
		$body .= "消息: {$message}\n";
		
		if ( ! empty( $context ) ) {
			$body .= "\n上下文:\n";
			$body .= print_r( $context, true );
		}
		
		$body .= "\n\n---\n";
		$body .= "此邮件由 WP Multi Cloud Storage 插件自动发送。\n";
		$body .= "网站: " . get_bloginfo( 'url' ) . "\n";
		
		// 发送到管理员邮箱
		$admin_email = get_option( 'admin_email' );
		wp_mail( $admin_email, $subject, $body );
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
	 * 导出日志
	 * 
	 * @param array $args 查询参数
	 * @return string CSV 内容
	 */
	public function export_logs( $args = array() ) {
		$args['per_page'] = 1000;
		$result = $this->get_logs( $args );
		$logs = $result['logs'];
		
		$csv = "ID,级别,类型,消息,附件ID,用户ID,IP地址,时间\n";
		
		foreach ( $logs as $log ) {
			$csv .= sprintf(
				"%d,%s,%s,%s,%s,%s,%s,%s\n",
				$log->id,
				$log->level,
				$log->type,
				str_replace( '"', '""', (string) $log->message ),
				$log->attachment_id ? $log->attachment_id : '',
				$log->user_id ? $log->user_id : '',
				$log->ip_address,
				$log->created_at
			);
		}
		
		return $csv;
	}
}
