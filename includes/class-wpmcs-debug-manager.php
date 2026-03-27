<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 调试管理器
 * 
 * 提供调试模式、日志记录和开发工具
 */
class WPMCS_Debug_Manager {
	
	/**
	 * 调试模式选项名
	 */
	const DEBUG_OPTION = 'wpmcs_debug_mode';
	
	/**
	 * 调试日志选项名
	 */
	const DEBUG_LOG_OPTION = 'wpmcs_debug_log';
	
	/**
	 * @var bool
	 */
	private $debug_mode;
	
	/**
	 * @var array
	 */
	private $settings;
	
	/**
	 * @var array
	 */
	private $log_entries = array();
	
	/**
	 * @var float
	 */
	private $start_time;
	
	/**
	 * @var array
	 */
	private $timers = array();
	
	/**
	 * 构造函数
	 *
	 * @param array $settings 插件设置
	 */
	public function __construct( array $settings ) {
		$this->settings = $settings;
		$this->debug_mode = get_option( self::DEBUG_OPTION, false );
		$this->start_time = microtime( true );
		
		if ( $this->debug_mode ) {
			$this->init_hooks();
		}
	}
	
	/**
	 * 初始化钩子
	 */
	public function init_hooks() {
		// 添加调试信息到页面底部
		add_action( 'admin_footer', array( $this, 'output_debug_info' ) );
		
		// 添加调试栏
		add_action( 'admin_bar_menu', array( $this, 'add_debug_bar' ), 999 );
		
		// 性能分析
		add_action( 'wpmcs_before_upload', array( $this, 'start_timer' ), 10, 2 );
		add_action( 'wpmcs_after_upload', array( $this, 'end_timer' ), 10, 2 );
		
		// AJAX 处理
		add_action( 'wp_ajax_wpmcs_toggle_debug_mode', array( $this, 'ajax_toggle_debug_mode' ) );
		add_action( 'wp_ajax_wpmcs_clear_debug_log', array( $this, 'ajax_clear_debug_log' ) );
		add_action( 'wp_ajax_wpmcs_export_debug_log', array( $this, 'ajax_export_debug_log' ) );
	}
	
	/**
	 * 启用调试模式
	 */
	public function enable() {
		update_option( self::DEBUG_OPTION, true );
		$this->debug_mode = true;
	}
	
	/**
	 * 禁用调试模式
	 */
	public function disable() {
		update_option( self::DEBUG_OPTION, false );
		$this->debug_mode = false;
	}
	
	/**
	 * 检查调试模式是否启用
	 *
	 * @return bool
	 */
	public function is_enabled() {
		return $this->debug_mode && current_user_can( 'manage_options' );
	}
	
	/**
	 * 记录调试日志
	 *
	 * @param string $message 日志消息
	 * @param string $level   日志级别 (debug, info, warning, error)
	 * @param array  $context 上下文数据
	 */
	public function log( $message, $level = 'debug', $context = array() ) {
		if ( ! $this->is_enabled() ) {
			return;
		}
		
		$entry = array(
			'time' => current_time( 'mysql' ),
			'timestamp' => microtime( true ),
			'memory' => memory_get_usage( true ),
			'memory_peak' => memory_get_peak_usage( true ),
			'level' => $level,
			'message' => $message,
			'context' => $context,
			'backtrace' => $this->get_backtrace(),
		);
		
		$this->log_entries[] = $entry;
		
		// 保存到数据库（限制条数）
		$this->save_log_entry( $entry );
		
		// 输出到错误日志
		if ( WP_DEBUG === true ) {
			error_log( sprintf( '[WPMCS Debug] %s: %s', strtoupper( $level ), $message ) );
		}
	}
	
	/**
	 * 记录调试信息
	 *
	 * @param string $message 消息
	 * @param array  $context 上下文
	 */
	public function info( $message, $context = array() ) {
		$this->log( $message, 'info', $context );
	}
	
	/**
	 * 记录警告
	 *
	 * @param string $message 消息
	 * @param array  $context 上下文
	 */
	public function warning( $message, $context = array() ) {
		$this->log( $message, 'warning', $context );
	}
	
	/**
	 * 记录错误
	 *
	 * @param string $message 消息
	 * @param array  $context 上下文
	 */
	public function error( $message, $context = array() ) {
		$this->log( $message, 'error', $context );
	}
	
	/**
	 * 记录数据库查询
	 *
	 * @param string $query SQL 查询
	 * @param float  $time  执行时间
	 */
	public function log_query( $query, $time ) {
		if ( ! $this->is_enabled() ) {
			return;
		}
		
		$this->log( 'Database Query', 'debug', array(
			'query' => $query,
			'time' => $time,
		) );
	}
	
	/**
	 * 记录 HTTP 请求
	 *
	 * @param string $url     请求 URL
	 * @param string $method  请求方法
	 * @param array  $args    请求参数
	 * @param mixed  $response 响应
	 * @param float  $time    执行时间
	 */
	public function log_request( $url, $method, $args, $response, $time ) {
		if ( ! $this->is_enabled() ) {
			return;
		}
		
		$this->log( 'HTTP Request', 'debug', array(
			'url' => $url,
			'method' => $method,
			'args' => $this->sanitize_args( $args ),
			'response_code' => is_wp_error( $response ) ? $response->get_error_code() : wp_remote_retrieve_response_code( $response ),
			'time' => $time,
		) );
	}
	
	/**
	 * 开始计时
	 *
	 * @param string $id      计时器 ID
	 * @param array  $context 上下文
	 */
	public function start_timer( $id, $context = array() ) {
		$this->timers[ $id ] = array(
			'start' => microtime( true ),
			'context' => $context,
		);
	}
	
	/**
	 * 结束计时
	 *
	 * @param string $id 计时器 ID
	 * @return float 执行时间（秒）
	 */
	public function end_timer( $id ) {
		if ( ! isset( $this->timers[ $id ] ) ) {
			return 0;
		}
		
		$end = microtime( true );
		$time = $end - $this->timers[ $id ]['start'];
		
		$this->log( sprintf( 'Timer: %s (%.4fs)', $id, $time ), 'debug', $this->timers[ $id ]['context'] );
		
		unset( $this->timers[ $id ] );
		
		return $time;
	}
	
	/**
	 * 获取调用堆栈
	 *
	 * @param int $limit 限制层数
	 * @return array
	 */
	private function get_backtrace( $limit = 5 ) {
		$trace = debug_backtrace( DEBUG_BACKTRACE_IGNORE_ARGS, $limit + 2 );
		$trace = array_slice( $trace, 2 ); // 移除本方法和 log 方法
		
		$simplified = array();
		foreach ( $trace as $item ) {
			$simplified[] = array(
				'file' => isset( $item['file'] ) ? basename( $item['file'] ) : 'unknown',
				'line' => isset( $item['line'] ) ? $item['line'] : 0,
				'function' => isset( $item['function'] ) ? $item['function'] : 'unknown',
			);
		}
		
		return $simplified;
	}
	
	/**
	 * 清理参数中的敏感信息
	 *
	 * @param array $args 参数数组
	 * @return array
	 */
	private function sanitize_args( $args ) {
		$sensitive_keys = array( 'password', 'secret', 'key', 'token', 'api_key', 'access_key' );
		
		foreach ( $args as $key => $value ) {
			if ( is_array( $value ) ) {
				$args[ $key ] = $this->sanitize_args( $value );
			} elseif ( is_string( $key ) ) {
				foreach ( $sensitive_keys as $sensitive ) {
					if ( stripos( $key, $sensitive ) !== false ) {
						$args[ $key ] = '***REDACTED***';
						break;
					}
				}
			}
		}
		
		return $args;
	}
	
	/**
	 * 保存日志条目到数据库
	 *
	 * @param array $entry 日志条目
	 */
	private function save_log_entry( $entry ) {
		$log = get_option( self::DEBUG_LOG_OPTION, array() );
		
		// 限制日志条数
		$max_entries = 1000;
		if ( count( $log ) >= $max_entries ) {
			$log = array_slice( $log, -$max_entries + 1 );
		}
		
		$log[] = $entry;
		update_option( self::DEBUG_LOG_OPTION, $log );
	}
	
	/**
	 * 获取日志
	 *
	 * @param int $limit 限制条数
	 * @return array
	 */
	public function get_log( $limit = 100 ) {
		$log = get_option( self::DEBUG_LOG_OPTION, array() );
		
		if ( $limit > 0 ) {
			return array_slice( $log, -$limit );
		}
		
		return $log;
	}
	
	/**
	 * 清空日志
	 */
	public function clear_log() {
		delete_option( self::DEBUG_LOG_OPTION );
		$this->log_entries = array();
	}
	
	/**
	 * 导出日志
	 *
	 * @return string JSON 格式的日志
	 */
	public function export_log() {
		$log = $this->get_log( 0 );
		return wp_json_encode( $log, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE );
	}
	
	/**
	 * 获取系统信息
	 *
	 * @return array
	 */
	public function get_system_info() {
		global $wpdb;
		
		return array(
			'wordpress' => array(
				'version' => get_bloginfo( 'version' ),
				'locale' => get_locale(),
				'multisite' => is_multisite(),
				'debug_mode' => WP_DEBUG,
				'memory_limit' => WP_MEMORY_LIMIT,
			),
			'php' => array(
				'version' => phpversion(),
				'memory_limit' => ini_get( 'memory_limit' ),
				'max_execution_time' => ini_get( 'max_execution_time' ),
				'upload_max_filesize' => ini_get( 'upload_max_filesize' ),
				'post_max_size' => ini_get( 'post_max_size' ),
				'extensions' => implode( ', ', get_loaded_extensions() ),
			),
			'database' => array(
				'version' => $wpdb->db_version(),
				'charset' => $wpdb->charset,
				'collate' => $wpdb->collate,
			),
			'plugin' => array(
				'version' => WPMCS_VERSION,
				'debug_mode' => $this->debug_mode,
				'provider' => isset( $this->settings['provider'] ) ? $this->settings['provider'] : 'unknown',
				'enabled' => isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : '0',
			),
			'server' => array(
				'software' => isset( $_SERVER['SERVER_SOFTWARE'] ) ? $_SERVER['SERVER_SOFTWARE'] : 'unknown',
				'php_sapi' => php_sapi_name(),
			),
		);
	}
	
	/**
	 * 添加调试栏
	 *
	 * @param WP_Admin_Bar $admin_bar
	 */
	public function add_debug_bar( $admin_bar ) {
		if ( ! $this->is_enabled() ) {
			return;
		}
		
		$admin_bar->add_node( array(
			'id' => 'wpmcs-debug',
			'title' => '<span class="ab-icon dashicons dashicons-admin-tools"></span> WPMCS Debug',
			'href' => admin_url( 'options-general.php?page=wpmcs-debug' ),
		) );
		
		$admin_bar->add_node( array(
			'id' => 'wpmcs-debug-toggle',
			'parent' => 'wpmcs-debug',
			'title' => $this->debug_mode ? '禁用调试模式' : '启用调试模式',
			'href' => '#',
		) );
		
		$admin_bar->add_node( array(
			'id' => 'wpmcs-debug-clear',
			'parent' => 'wpmcs-debug',
			'title' => '清空调试日志',
			'href' => '#',
		) );
		
		$admin_bar->add_node( array(
			'id' => 'wpmcs-debug-export',
			'parent' => 'wpmcs-debug',
			'title' => '导出调试日志',
			'href' => '#',
		) );
	}
	
	/**
	 * 输出调试信息
	 */
	public function output_debug_info() {
		if ( ! $this->is_enabled() ) {
			return;
		}
		
		$execution_time = microtime( true ) - $this->start_time;
		$memory_usage = memory_get_peak_usage( true );
		$log_count = count( $this->get_log( 0 ) );
		
		?>
		<script type="text/javascript">
		console.log('=== WPMCS Debug Info ===');
		console.log('Execution Time: <?php echo number_format( $execution_time, 4 ); ?>s');
		console.log('Memory Peak: <?php echo size_format( $memory_usage ); ?>');
		console.log('Log Entries: <?php echo $log_count; ?>');
		console.log('Debug Mode: <?php echo $this->debug_mode ? 'Enabled' : 'Disabled'; ?>');
		<?php if ( ! empty( $this->log_entries ) ) : ?>
		console.log('Recent Logs:', <?php echo wp_json_encode( array_slice( $this->log_entries, -10 ) ); ?>);
		<?php endif; ?>
		</script>
		<?php
	}
	
	/**
	 * AJAX 切换调试模式
	 */
	public function ajax_toggle_debug_mode() {
		check_ajax_referer( 'wpmcs_debug', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		if ( $this->debug_mode ) {
			$this->disable();
			wp_send_json_success( array( 'message' => '调试模式已禁用', 'enabled' => false ) );
		} else {
			$this->enable();
			wp_send_json_success( array( 'message' => '调试模式已启用', 'enabled' => true ) );
		}
	}
	
	/**
	 * AJAX 清空调试日志
	 */
	public function ajax_clear_debug_log() {
		check_ajax_referer( 'wpmcs_debug', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$this->clear_log();
		wp_send_json_success( array( 'message' => '调试日志已清空' ) );
	}
	
	/**
	 * AJAX 导出调试日志
	 */
	public function ajax_export_debug_log() {
		check_ajax_referer( 'wpmcs_debug', 'nonce' );
		
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		$log = $this->export_log();
		
		wp_send_json_success( array(
			'log' => $log,
			'filename' => 'wpmcs-debug-' . date( 'Y-m-d-H-i-s' ) . '.json',
		) );
	}
}
