<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 临时文件管理器
 *
 * 安全地管理插件临时文件的创建和清理
 */
class WPMCS_Temp_File_Manager {

	/**
	 * 临时目录名称
	 */
	const TEMP_DIR_NAME = 'wpmcs-temp';

	/**
	 * 临时文件最大存活时间（秒）
	 * 默认 24 小时
	 */
	const TEMP_FILE_MAX_AGE = 86400;

	/**
	 * 获取临时目录路径
	 *
	 * @return string 目录路径
	 */
	public static function get_temp_dir() {
		$upload_dir = wpmcs_get_upload_dir();
		$base_dir   = isset( $upload_dir['basedir'] ) ? (string) $upload_dir['basedir'] : '';
		$temp_dir   = '' !== $base_dir ? trailingslashit( $base_dir ) . self::TEMP_DIR_NAME : sys_get_temp_dir() . '/' . self::TEMP_DIR_NAME;

		// 确保目录存在
		if ( ! file_exists( $temp_dir ) ) {
			wp_mkdir_p( $temp_dir );

			// 创建 .htaccess 防止直接访问
			$htaccess_file = $temp_dir . '/.htaccess';
			if ( ! file_exists( $htaccess_file ) ) {
				file_put_contents( $htaccess_file, "Deny from all\n" );
			}

			// 创建 index.php 防止目录列表
			$index_file = $temp_dir . '/index.php';
			if ( ! file_exists( $index_file ) ) {
				file_put_contents( $index_file, "<?php\n// 保持空白，防止目录被直接访问。\n" );
			}
		}

		return $temp_dir;
	}

	/**
	 * 创建临时文件
	 *
	 * @param string $content 文件内容
	 * @param string $prefix 文件名前缀
	 * @param string $extension 文件扩展名
	 * @return string|false 文件路径，失败返回 false
	 */
	public static function create_temp_file( $content, $prefix = 'wpmcs-', $extension = 'tmp' ) {
		$temp_dir = self::get_temp_dir();

		// 生成唯一文件名
		$filename = $prefix . wp_generate_password( 12, false ) . '.' . $extension;
		$filepath = $temp_dir . '/' . $filename;

		// 写入文件
		$result = file_put_contents( $filepath, $content );

		if ( false === $result ) {
			return false;
		}

		// 设置权限为 640（所有者读写，组只读）
		@chmod( $filepath, 0640 );

		return $filepath;
	}

	/**
	 * 创建临时目录
	 *
	 * @param string $prefix 目录名前缀
	 * @return string|false 目录路径，失败返回 false
	 */
	public static function create_temp_dir( $prefix = 'wpmcs-' ) {
		$temp_dir = self::get_temp_dir();

		// 生成唯一目录名
		$dir_name = $prefix . wp_generate_password( 12, false );
		$dir_path = $temp_dir . '/' . $dir_name;

		// 创建目录
		$result = wp_mkdir_p( $dir_path );

		if ( ! $result ) {
			return false;
		}

		// 创建 .htaccess 保护
		$htaccess_file = $dir_path . '/.htaccess';
		file_put_contents( $htaccess_file, "Deny from all\n" );

		return $dir_path;
	}

	/**
	 * 清理过期的临时文件
	 *
	 * @param int $max_age 最大存活时间（秒）
	 * @return int 清理的文件数量
	 */
	public static function cleanup_temp_files( $max_age = null ) {
		if ( null === $max_age ) {
			$max_age = self::TEMP_FILE_MAX_AGE;
		}

		$temp_dir = self::get_temp_dir();

		if ( ! file_exists( $temp_dir ) || ! is_dir( $temp_dir ) ) {
			return 0;
		}

		$count = 0;
		$cutoff = time() - $max_age;

		// 递归清理
		$count = self::cleanup_temp_files_recursive( $temp_dir, $cutoff );

		// 如果主目录为空，也删除
		$files = glob( $temp_dir . '/*' );
		if ( empty( $files ) || count( $files ) <= 2 ) { // .htaccess 和 index.php
			@rmdir( $temp_dir );
		}

		return $count;
	}

	/**
	 * 递归清理临时文件
	 *
	 * @param string $dir 目录路径
	 * @param int $cutoff 截止时间戳
	 * @return int 清理的文件数量
	 */
	private static function cleanup_temp_files_recursive( $dir, $cutoff ) {
		$count = 0;

		if ( ! is_dir( $dir ) ) {
			return $count;
		}

		$files = scandir( $dir );

		foreach ( $files as $file ) {
			// 跳过特殊目录
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			// 跳过保护文件
			if ( '.htaccess' === $file || 'index.php' === $file ) {
				continue;
			}

			$path = $dir . DIRECTORY_SEPARATOR . $file;

			if ( is_dir( $path ) ) {
				// 递归清理子目录
				$count += self::cleanup_temp_files_recursive( $path, $cutoff );

				// 如果目录为空，删除它
				$sub_files = glob( $path . '/*' );
				if ( empty( $sub_files ) || count( $sub_files ) <= 2 ) {
					@rmdir( $path );
				}
			} elseif ( is_file( $path ) ) {
				// 检查文件修改时间
				$mtime = filemtime( $path );
				if ( $mtime < $cutoff ) {
					if ( unlink( $path ) ) {
						$count++;
					}
				}
			}
		}

		return $count;
	}

	/**
	 * 清空所有临时文件（强制清理）
	 *
	 * @return int 清理的文件数量
	 */
	public static function clear_all_temp_files() {
		$temp_dir = self::get_temp_dir();

		if ( ! file_exists( $temp_dir ) ) {
			return 0;
		}

		return self::recursive_delete_dir( $temp_dir );
	}

	/**
	 * 递归删除目录
	 *
	 * @param string $dir 目录路径
	 * @return int 删除的文件数量
	 */
	private static function recursive_delete_dir( $dir ) {
		$count = 0;

		if ( ! is_dir( $dir ) ) {
			return $count;
		}

		$files = scandir( $dir );

		foreach ( $files as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			$path = $dir . DIRECTORY_SEPARATOR . $file;

			if ( is_dir( $path ) ) {
				$count += self::recursive_delete_dir( $path );
			} elseif ( is_file( $path ) ) {
				if ( unlink( $path ) ) {
					$count++;
				}
			}
		}

		// 删除空目录
		@rmdir( $dir );

		return $count;
	}

	/**
	 * 获取临时文件统计
	 *
	 * @return array 统计信息
	 */
	public static function get_stats() {
		$temp_dir = self::get_temp_dir();

		$stats = array(
			'temp_dir_exists' => false,
			'total_files'     => 0,
			'total_size'      => 0,
			'oldest_file'     => 0,
			'newest_file'     => 0,
			'expired_files'   => 0,
		);

		if ( ! file_exists( $temp_dir ) ) {
			return $stats;
		}

		$stats['temp_dir_exists'] = true;

		self::scan_temp_dir_stats( $temp_dir, $stats );

		return $stats;
	}

	/**
	 * 扫描临时目录统计信息
	 *
	 * @param string $dir 目录路径
	 * @param array $stats 统计信息（引用）
	 */
	private static function scan_temp_dir_stats( $dir, &$stats ) {
		if ( ! is_dir( $dir ) ) {
			return;
		}

		$files = scandir( $dir );
		$cutoff = time() - self::TEMP_FILE_MAX_AGE;

		foreach ( $files as $file ) {
			if ( '.' === $file || '..' === $file ) {
				continue;
			}

			if ( '.htaccess' === $file || 'index.php' === $file ) {
				continue;
			}

			$path = $dir . DIRECTORY_SEPARATOR . $file;

			if ( is_dir( $path ) ) {
				self::scan_temp_dir_stats( $path, $stats );
			} elseif ( is_file( $path ) ) {
				$stats['total_files']++;
				$stats['total_size'] += filesize( $path );

				$mtime = filemtime( $path );
				if ( 0 === $stats['oldest_file'] || $mtime < $stats['oldest_file'] ) {
					$stats['oldest_file'] = $mtime;
				}
				if ( $mtime > $stats['newest_file'] ) {
					$stats['newest_file'] = $mtime;
				}

				if ( $mtime < $cutoff ) {
					$stats['expired_files']++;
				}
			}
		}
	}

	/**
	 * 格式化文件大小
	 *
	 * @param int $bytes 字节数
	 * @return string 格式化的大小
	 */
	public static function format_size( $bytes ) {
		$units = array( 'B', 'KB', 'MB', 'GB' );
		$bytes = max( $bytes, 0 );
		$pow   = floor( ( $bytes ? log( $bytes ) : 0 ) / log( 1024 ) );
		$pow   = min( $pow, count( $units ) - 1 );
		$bytes /= pow( 1024, $pow );

		return round( $bytes, 2 ) . ' ' . $units[ $pow ];
	}
}
