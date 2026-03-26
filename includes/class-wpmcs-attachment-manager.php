<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 附件管理器
 * 
 * 负责管理附件的云端元数据
 * 包括保存、获取、更新和删除云端文件信息
 */
class WPMCS_Attachment_Manager {
	
	/**
	 * 保存附件云端元数据
	 *
	 * @param int $attachment_id 附件 ID
	 * @param array $cloud_meta 云端元数据
	 * @return bool
	 */
	public static function save_cloud_meta( $attachment_id, array $cloud_meta ) {
		if ( empty( $cloud_meta['key'] ) || empty( $cloud_meta['url'] ) ) {
			return false;
		}
		
		// 确保必要的字段存在
		$cloud_meta = array_merge( array(
			'provider' => 'unknown',
			'key' => '',
			'url' => '',
			'sizes' => array(),
			'uploaded_at' => current_time( 'mysql' )
		), $cloud_meta );
		
		// 保存到附件元数据
		$cloud_file_size = isset( $cloud_meta['file_size'] ) ? (int) $cloud_meta['file_size'] : 0;
		if ( $cloud_file_size <= 0 ) {
			$file_path = get_attached_file( $attachment_id );
			if ( $file_path && file_exists( $file_path ) ) {
				$cloud_file_size = (int) filesize( $file_path );
			}
		}
		if ( $cloud_file_size > 0 ) {
			$cloud_meta['file_size'] = $cloud_file_size;
		} else {
			unset( $cloud_meta['file_size'] );
		}
		$result = update_post_meta( $attachment_id, '_wpmcs_cloud_meta', $cloud_meta );
		if ( $cloud_file_size > 0 ) {
			update_post_meta( $attachment_id, '_wpmcs_file_size', $cloud_file_size );
		} else {
			delete_post_meta( $attachment_id, '_wpmcs_file_size' );
		}
		
		// 同时保存到附件元数据中，方便直接访问
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $metadata ) ) {
			$metadata['wpmcs_cloud'] = $cloud_meta;
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}
		
		// 清除错误标记
		delete_post_meta( $attachment_id, '_wpmcs_last_error' );
		delete_option( 'wpmcs_storage_stats' );
		delete_transient( 'wpmcs_tracking_url_map' );
		
		return $result;
	}
	
	/**
	 * 获取附件云端元数据
	 *
	 * @param int $attachment_id 附件 ID
	 * @return array
	 */
	public static function get_cloud_meta( $attachment_id ) {
		// 首先尝试从专用元数据获取
		$cloud_meta = get_post_meta( $attachment_id, '_wpmcs_cloud_meta', true );
		
		if ( is_array( $cloud_meta ) && ! empty( $cloud_meta['key'] ) ) {
			return $cloud_meta;
		}

		// 兼容旧数据：批量上传/迁移早期版本只保存了分散字段
		$legacy_url = get_post_meta( $attachment_id, '_wpmcs_cloud_url', true );
		$legacy_path = get_post_meta( $attachment_id, '_wpmcs_cloud_path', true );
		if ( ! empty( $legacy_url ) ) {
			return array(
				'provider' => 'unknown',
				'key' => ! empty( $legacy_path ) ? $legacy_path : $legacy_url,
				'url' => $legacy_url,
				'path' => $legacy_path,
				'sizes' => array(),
				'uploaded_at' => get_post_meta( $attachment_id, '_wpmcs_uploaded_at', true ),
			);
		}
		
		// 然后尝试从附件元数据中获取
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $metadata ) && ! empty( $metadata['wpmcs_cloud'] ) ) {
			return $metadata['wpmcs_cloud'];
		}
		
		return array();
	}

	/**
	 * 获取媒体访问跟踪 URL。
	 *
	 * @param int $attachment_id 附件 ID
	 * @param string $size 尺寸名称
	 * @return string
	 */
	public static function get_tracking_url( $attachment_id, $size = 'full' ) {
		$attachment_id = (int) $attachment_id;
		$size = sanitize_key( (string) $size );

		if ( $attachment_id <= 0 ) {
			return '';
		}

		return add_query_arg(
			array(
				'wpmcs_track' => 1,
				'attachment_id' => $attachment_id,
				'size' => $size ? $size : 'full',
			),
			home_url( '/' )
		);
	}
	
	/**
	 * 检查附件是否有云端元数据
	 *
	 * @param int $attachment_id 附件 ID
	 * @return bool
	 */
	public static function has_cloud_meta( $attachment_id ) {
		$cloud_meta = self::get_cloud_meta( $attachment_id );
		return ! empty( $cloud_meta['key'] ) && ! empty( $cloud_meta['url'] );
	}
	
	/**
	 * 删除附件云端元数据
	 *
	 * @param int $attachment_id 附件 ID
	 * @return bool
	 */
	public static function delete_cloud_meta( $attachment_id ) {
		// 删除专用元数据
		delete_post_meta( $attachment_id, '_wpmcs_cloud_meta' );
		delete_post_meta( $attachment_id, '_wpmcs_cloud_url' );
		delete_post_meta( $attachment_id, '_wpmcs_cloud_path' );
		delete_post_meta( $attachment_id, '_wpmcs_uploaded_at' );
		
		// 从附件元数据中移除
		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $metadata ) && isset( $metadata['wpmcs_cloud'] ) ) {
			unset( $metadata['wpmcs_cloud'] );
			wp_update_attachment_metadata( $attachment_id, $metadata );
		}
		
		// 删除错误标记
		delete_post_meta( $attachment_id, '_wpmcs_last_error' );
		delete_post_meta( $attachment_id, '_wpmcs_file_size' );
		delete_option( 'wpmcs_storage_stats' );
		delete_transient( 'wpmcs_tracking_url_map' );
		
		return true;
	}
	
	/**
	 * 保存上传错误信息
	 *
	 * @param int $attachment_id 附件 ID
	 * @param string $error_message 错误信息
	 * @return bool
	 */
	public static function save_upload_error( $attachment_id, $error_message ) {
		return update_post_meta( $attachment_id, '_wpmcs_last_error', sanitize_text_field( $error_message ) );
	}
	
	/**
	 * 获取上传错误信息
	 *
	 * @param int $attachment_id 附件 ID
	 * @return string
	 */
	public static function get_preview_file_data( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$file_path = get_attached_file( $attachment_id );

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return array();
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $metadata ) || empty( $metadata['sizes'] ) || ! is_array( $metadata['sizes'] ) ) {
			return array();
		}

		$size_name = '';
		$size_data = array();

		if ( ! empty( $metadata['sizes']['thumbnail'] ) && is_array( $metadata['sizes']['thumbnail'] ) ) {
			$size_name = 'thumbnail';
			$size_data = $metadata['sizes']['thumbnail'];
		} else {
			foreach ( $metadata['sizes'] as $name => $data ) {
				if ( ! is_array( $data ) || empty( $data['file'] ) ) {
					continue;
				}

				$size_name = (string) $name;
				$size_data = $data;
				break;
			}
		}

		if ( empty( $size_name ) || empty( $size_data['file'] ) ) {
			return array();
		}

		$size_data['size'] = $size_name;
		$size_data['path'] = trailingslashit( dirname( $file_path ) ) . $size_data['file'];

		return $size_data;
	}

	public static function delete_local_files( $attachment_id, array $settings = array() ) {
		if ( empty( $settings['replace_url'] ) ) {
			return false;
		}

		$attachment_id = (int) $attachment_id;
		$file_path = get_attached_file( $attachment_id );

		if ( empty( $file_path ) || ! file_exists( $file_path ) ) {
			return false;
		}

		$paths = array( $file_path );
		$metadata = wp_get_attachment_metadata( $attachment_id );

		if ( is_array( $metadata ) ) {
			$base_dir = trailingslashit( dirname( $file_path ) );

			if ( ! empty( $metadata['original_image'] ) ) {
				$paths[] = $base_dir . $metadata['original_image'];
			}

			if ( ! empty( $metadata['sizes'] ) && is_array( $metadata['sizes'] ) ) {
				foreach ( $metadata['sizes'] as $size_data ) {
					if ( empty( $size_data['file'] ) ) {
						continue;
					}

					$paths[] = $base_dir . $size_data['file'];
				}
			}
		}

		if ( ! empty( $settings['keep_local_file'] ) ) {
			$paths = array_diff( $paths, array( $file_path ) );
		}

		$paths = array_values( array_unique( array_filter( $paths ) ) );

		foreach ( $paths as $path ) {
			if ( ! file_exists( $path ) ) {
				continue;
			}

			if ( function_exists( 'wp_delete_file' ) ) {
				wp_delete_file( $path );
			} else {
				@unlink( $path );
			}
		}

		return true;
	}

	public static function get_upload_error( $attachment_id ) {
		return get_post_meta( $attachment_id, '_wpmcs_last_error', true );
	}
	
	/**
	 * 获取指定尺寸的云端 URL
	 *
	 * @param int $attachment_id 附件 ID
	 * @param string $size 尺寸名称
	 * @return string
	 */
	public static function get_size_url( $attachment_id, $size = 'full' ) {
		$cloud_meta = self::get_cloud_meta( $attachment_id );
		
		if ( empty( $cloud_meta['url'] ) ) {
			return '';
		}
		
		// 如果是全尺寸图片
		if ( 'full' === $size ) {
			return $cloud_meta['url'];
		}
		
		// 如果是特定尺寸
		if ( isset( $cloud_meta['sizes'][ $size ]['url'] ) ) {
			return $cloud_meta['sizes'][ $size ]['url'];
		}
		
		// 如果找不到指定尺寸，返回全尺寸 URL
		return $cloud_meta['url'];
	}
	
	/**
	 * 获取附件的云端文件 key
	 *
	 * @param int $attachment_id 附件 ID
	 * @return string
	 */
	public static function get_cloud_key( $attachment_id ) {
		$cloud_meta = self::get_cloud_meta( $attachment_id );
		return $cloud_meta['key'] ?? '';
	}
	
	/**
	 * 更新尺寸信息
	 *
	 * @param int $attachment_id 附件 ID
	 * @param string $size_name 尺寸名称
	 * @param array $size_data 尺寸数据
	 * @return bool
	 */
	public static function update_size_info( $attachment_id, $size_name, array $size_data ) {
		$cloud_meta = self::get_cloud_meta( $attachment_id );
		
		if ( empty( $cloud_meta['key'] ) ) {
			return false;
		}
		
		$cloud_meta['sizes'][ $size_name ] = array_merge( array(
			'url' => '',
			'file' => '',
			'width' => 0,
			'height' => 0
		), $size_data );
		
		return self::save_cloud_meta( $attachment_id, $cloud_meta );
	}
	
	/**
	 * 批量获取附件的云端信息
	 *
	 * @param array $attachment_ids 附件 ID 数组
	 * @return array
	 */
	public static function get_batch_cloud_info( array $attachment_ids ) {
		$results = array();
		
		foreach ( $attachment_ids as $attachment_id ) {
			$cloud_meta = self::get_cloud_meta( $attachment_id );
			$results[ $attachment_id ] = array(
				'has_cloud' => ! empty( $cloud_meta['key'] ),
				'cloud_url' => $cloud_meta['url'] ?? '',
				'provider' => $cloud_meta['provider'] ?? 'unknown',
				'uploaded_at' => $cloud_meta['uploaded_at'] ?? ''
			);
		}
		
		return $results;
	}
	
	/**
	 * 获取所有有云端元数据的附件 ID
	 *
	 * @return array
	 */
	public static function get_all_cloud_attachments() {
		global $wpdb;
		
		$attachment_ids = $wpdb->get_col( 
			"SELECT DISTINCT post_id FROM {$wpdb->postmeta} WHERE meta_key IN ('_wpmcs_cloud_meta', '_wpmcs_cloud_url')"
		);
		
		return array_map( 'intval', $attachment_ids );
	}

	/**
	 * 获取云端 URL 到跟踪 URL 的映射。
	 *
	 * @return array<string, string>
	 */
	public static function get_tracking_url_map() {
		$cache_key = 'wpmcs_tracking_url_map';
		$cached_map = get_transient( $cache_key );
		if ( is_array( $cached_map ) ) {
			return $cached_map;
		}

		$map = array();
		$attachment_ids = self::get_all_cloud_attachments();

		foreach ( $attachment_ids as $attachment_id ) {
			$cloud_meta = self::get_cloud_meta( $attachment_id );
			if ( empty( $cloud_meta['url'] ) ) {
				continue;
			}

			$map[ $cloud_meta['url'] ] = self::get_tracking_url( $attachment_id, 'full' );

			if ( ! empty( $cloud_meta['sizes'] ) && is_array( $cloud_meta['sizes'] ) ) {
				foreach ( $cloud_meta['sizes'] as $size_name => $size_data ) {
					if ( empty( $size_data['url'] ) ) {
						continue;
					}

					$map[ $size_data['url'] ] = self::get_tracking_url( $attachment_id, $size_name );
				}
			}
		}

		set_transient( $cache_key, $map, HOUR_IN_SECONDS );

		return $map;
	}
	
	/**
	 * 统计云端附件信息
	 *
	 * @return array
	 */
	public static function get_cloud_stats() {
		global $wpdb;
		
		$total_attachments = (int) $wpdb->get_var( 
			"SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'"
		);
		
		$cloud_attachments = (int) $wpdb->get_var( 
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_wpmcs_cloud_meta'"
		);
		
		$error_attachments = (int) $wpdb->get_var( 
			"SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = '_wpmcs_last_error'"
		);
		
		return array(
			'total_attachments' => $total_attachments,
			'cloud_attachments' => $cloud_attachments,
			'error_attachments' => $error_attachments,
			'cloud_percentage' => $total_attachments > 0 ? round( ( $cloud_attachments / $total_attachments ) * 100, 2 ) : 0
		);
	}
}
