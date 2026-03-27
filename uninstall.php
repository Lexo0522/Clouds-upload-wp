<?php
/**
 * 插件卸载处理
 * 
 * 清理所有插件数据和配置
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

global $wpdb;

// 1. 删除数据库表
$table_name = $wpdb->prefix . 'wpmcs_logs';
$wpdb->query( "DROP TABLE IF EXISTS {$table_name}" );

// 2. 删除插件选项
$options_to_delete = array(
    'wpmcs_settings',
    'wpmcs_debug_mode',
    'wpmcs_queue_lock',
    'wpmcs_version',
    'wpmcs_storage_stats',
);

foreach ( $options_to_delete as $option ) {
    delete_option( $option );
}

// 3. 删除所有附件的云端元数据
// 注意：这不会删除附件本身，只删除我们的元数据
$wpdb->query( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE '_wpmcs_%'" );

// 3.1 删除用户元数据
$wpdb->query( "DELETE FROM {$wpdb->usermeta} WHERE meta_key LIKE 'wpmcs_%'" );

// 3.2 删除注释元数据（如果有的话）
$wpdb->query( "DELETE FROM {$wpdb->commentmeta} WHERE meta_key LIKE 'wpmcs_%'" );

// 4. 删除定时任务
$scheduled_hooks = array(
	'wpmcs_cleanup_logs',           // 清理日志（每日）
	'wpmcs_cleanup_temp_files',     // 清理临时文件（每日）
	'wpmcs_process_queue',          // 处理队列（每分钟）
	'wpmcs_cleanup_locks',          // 清理队列锁（每小时）
	'wpmcs_update_storage_stats',   // 更新存储统计（每小时）
);

foreach ( $scheduled_hooks as $hook ) {
    wp_clear_scheduled_hook( $hook );
    // 也要清理下一个计划（兼容 WordPress < 5.1）
    $next = wp_next_scheduled( $hook );
    if ( $next ) {
        wp_unschedule_event( $next, $hook );
    }
}

// 5. 清理对象缓存
if ( function_exists( 'wp_cache_flush' ) ) {
    wp_cache_flush();
}

// 6. 清理上传目录中的临时文件（如果有）
$upload_dir = wp_upload_dir();
$temp_dir = $upload_dir['basedir'] . '/wpmcs-temp';

// 安全检查：确保目录确实是临时目录
if ( file_exists( $temp_dir ) && is_dir( $temp_dir ) ) {
	// 检查目录名是否匹配预期（防止误删）
	$dir_name = basename( $temp_dir );
	if ( 'wpmcs-temp' === $dir_name ) {
		// 递归删除目录及所有内容
		$deleted_count = wpmcs_recursive_delete_dir( $temp_dir );

		// 记录到日志（如果还可用）
		if ( function_exists( 'error_log' ) ) {
			error_log( sprintf(
				'WPMCS Uninstall: Cleaned %d files from %s',
				$deleted_count,
				$temp_dir
			) );
		}
	}
}

/**
 * 递归删除目录
 *
 * @param string $dir 目录路径
 * @return int 删除的文件数量
 */
function wpmcs_recursive_delete_dir( $dir ) {
	$count = 0;

	if ( ! is_dir( $dir ) ) {
		return $count;
	}

	$files = scandir( $dir );

	foreach ( $files as $file ) {
		// 跳过当前目录和上级目录
		if ( '.' === $file || '..' === $file ) {
			continue;
		}

		$path = $dir . DIRECTORY_SEPARATOR . $file;

		if ( is_dir( $path ) ) {
			// 递归删除子目录
			$count += wpmcs_recursive_delete_dir( $path );
		} elseif ( is_file( $path ) ) {
			// 删除文件
			if ( unlink( $path ) ) {
				$count++;
			}
		}
	}

	// 删除空目录
	rmdir( $dir );

	return $count;
}
