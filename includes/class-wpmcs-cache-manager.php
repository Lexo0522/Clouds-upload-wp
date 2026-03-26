<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 缓存管理器
 * 
 * 用于优化数据库查询，减少重复查询
 */
class WPMCS_Cache_Manager {
	
	/**
	 * 缓存组名称
	 */
	const CACHE_GROUP = 'wpmcs';
	
	/**
	 * 缓存过期时间（秒）
	 */
	const CACHE_EXPIRE = 3600; // 1小时
	
	/**
	 * 是否启用对象缓存
	 */
	private $use_object_cache = false;
	
	/**
	 * 内存缓存
	 */
	private static $memory_cache = array();

	/**
	 * 内存缓存最大条目数
	 */
	private static $memory_cache_max_size = 500;

	/**
	 * 内存缓存当前大小
	 */
	private static $memory_cache_size = 0;

	public function __construct() {
		// 检查是否支持对象缓存
		$this->use_object_cache = $this->check_object_cache();
	}
	
	/**
	 * 检查对象缓存是否可用
	 * 
	 * @return bool
	 */
	private function check_object_cache() {
		// WordPress 内存对象缓存始终可用
		return true;
	}
	
	/**
	 * 获取缓存
	 *
	 * @param string $key 缓存键
	 * @param mixed $default 默认值
	 * @return mixed
	 */
	public function get( $key, $default = null ) {
		// 首先检查内存缓存
		if ( isset( self::$memory_cache[ $key ] ) ) {
			return self::$memory_cache[ $key ];
		}

		// 使用对象缓存
		if ( $this->use_object_cache ) {
			$value = wp_cache_get( $key, self::CACHE_GROUP );

			if ( false !== $value ) {
				// 只在内存缓存未满时存入
				$this->add_to_memory_cache( $key, $value );
				return $value;
			}
		}

		return $default;
	}

	/**
	 * 安全地添加到内存缓存（检查大小限制）
	 *
	 * @param string $key 缓存键
	 * @param mixed $value 缓存值
	 */
	private function add_to_memory_cache( $key, $value ) {
		// 不缓存 null 值
		if ( null === $value ) {
			return;
		}

		if ( isset( self::$memory_cache[ $key ] ) ) {
			// 已存在,直接更新
			self::$memory_cache[ $key ] = $value;
		} elseif ( self::$memory_cache_size < self::$memory_cache_max_size ) {
			// 未达到上限,添加新缓存
			self::$memory_cache[ $key ] = $value;
			self::$memory_cache_size++;
		}
	}
	
	/**
	 * 设置缓存
	 *
	 * @param string $key 缓存键
	 * @param mixed $value 缓存值
	 * @param int $expire 过期时间（秒）
	 * @return bool
	 */
	public function set( $key, $value, $expire = null ) {
		// 不缓存 null 值到内存缓存（但对象缓存可以）
		if ( null !== $value ) {
			// 限制内存缓存大小,防止内存溢出
			if ( isset( self::$memory_cache[ $key ] ) ) {
				// 已存在,直接更新
				self::$memory_cache[ $key ] = $value;
			} elseif ( self::$memory_cache_size < self::$memory_cache_max_size ) {
				// 未达到上限,添加新缓存
				self::$memory_cache[ $key ] = $value;
				self::$memory_cache_size++;
			}
		}

		// 使用对象缓存（null 值也会存储到对象缓存）
		if ( $this->use_object_cache ) {
			$expire = $expire ? $expire : self::CACHE_EXPIRE;
			return wp_cache_set( $key, $value, self::CACHE_GROUP, $expire );
		}

		return true;
	}
	
	/**
	 * 删除缓存
	 *
	 * @param string $key 缓存键
	 * @return bool
	 */
	public function delete( $key ) {
		// 删除内存缓存
		if ( isset( self::$memory_cache[ $key ] ) ) {
			unset( self::$memory_cache[ $key ] );
			self::$memory_cache_size--;
		}

		// 删除对象缓存
		if ( $this->use_object_cache ) {
			return wp_cache_delete( $key, self::CACHE_GROUP );
		}

		return true;
	}
	
	/**
	 * 清空所有缓存
	 *
	 * @return bool
	 */
	public function flush() {
		// 清空内存缓存
		self::$memory_cache = array();
		self::$memory_cache_size = 0;

		// 清空对象缓存（如果支持）
		if ( $this->use_object_cache && function_exists( 'wp_cache_flush_group' ) ) {
			return wp_cache_flush_group( self::CACHE_GROUP );
		}

		return true;
	}
	
	/**
	 * 获取附件云端元数据（带缓存）
	 *
	 * @param int $attachment_id 附件 ID
	 * @return array|null
	 */
	public function get_cloud_meta( $attachment_id ) {
		$cache_key = "cloud_meta_{$attachment_id}";

		// 尝试从缓存获取
		$cached = $this->get( $cache_key );

		if ( null !== $cached ) {
			return $cached;
		}

		// 从数据库获取
		if ( class_exists( 'WPMCS_Attachment_Manager' ) ) {
			$cloud_meta = WPMCS_Attachment_Manager::get_cloud_meta( $attachment_id );
		} else {
			$cloud_meta = get_post_meta( $attachment_id, '_wpmcs_cloud_meta', true );
		}

		if ( ! is_array( $cloud_meta ) ) {
			// 尝试从附件元数据中获取
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( is_array( $metadata ) && ! empty( $metadata['wpmcs_cloud'] ) ) {
				$cloud_meta = $metadata['wpmcs_cloud'];
			}
		}

		// 只缓存非 null 值到内存缓存，避免占用空间
		if ( is_array( $cloud_meta ) && ! empty( $cloud_meta ) ) {
			$this->set( $cache_key, $cloud_meta );
		}

		return is_array( $cloud_meta ) && ! empty( $cloud_meta ) ? $cloud_meta : null;
	}
	
	/**
	 * 更新附件云端元数据缓存
	 * 
	 * @param int $attachment_id 附件 ID
	 * @param array $cloud_meta 云端元数据
	 */
	public function update_cloud_meta_cache( $attachment_id, $cloud_meta ) {
		$cache_key = "cloud_meta_{$attachment_id}";
		$this->set( $cache_key, $cloud_meta );
	}
	
	/**
	 * 删除附件云端元数据缓存
	 * 
	 * @param int $attachment_id 附件 ID
	 */
	public function delete_cloud_meta_cache( $attachment_id ) {
		$cache_key = "cloud_meta_{$attachment_id}";
		$this->delete( $cache_key );
	}
	
	/**
	 * 批量获取云端元数据
	 *
	 * @param array $attachment_ids 附件 ID 数组
	 * @return array
	 */
	public function batch_get_cloud_meta( $attachment_ids ) {
		global $wpdb;

		if ( empty( $attachment_ids ) ) {
			return array();
		}

		$results = array();
		$uncached_ids = array();

		// 先从缓存获取
		foreach ( $attachment_ids as $attachment_id ) {
			$cached = $this->get( "cloud_meta_{$attachment_id}" );

			if ( null !== $cached ) {
				$results[ $attachment_id ] = $cached;
			} else {
				$uncached_ids[] = $attachment_id;
			}
		}

		// 批量查询未缓存的
		if ( ! empty( $uncached_ids ) ) {
			$ids_string = implode( ',', array_map( 'intval', $uncached_ids ) );

			$metas = $wpdb->get_results(
				"SELECT post_id, meta_value
				FROM {$wpdb->postmeta}
				WHERE post_id IN ({$ids_string})
				AND meta_key = '_wpmcs_cloud_meta'"
			);

			// 记录有数据的 ID
			$found_ids = array();

			if ( $metas ) {
				foreach ( $metas as $meta ) {
					$cloud_meta = maybe_unserialize( $meta->meta_value );
					$results[ $meta->post_id ] = $cloud_meta;
					$found_ids[ $meta->post_id ] = true;

					// 只缓存非空数据到内存缓存
					if ( is_array( $cloud_meta ) && ! empty( $cloud_meta ) ) {
						$this->set( "cloud_meta_{$meta->post_id}", $cloud_meta );
					}
				}
			}

			// 对于没有云端元数据的，不在内存缓存中存储 null
			// 但需要返回 null 以保持结果完整性
			foreach ( $uncached_ids as $attachment_id ) {
				if ( ! isset( $found_ids[ $attachment_id ] ) ) {
					$results[ $attachment_id ] = null;
				}
			}

			// 释放内存
			unset( $metas, $found_ids, $uncached_ids );
		}

		return $results;
	}
	
	/**
	 * 获取云端 URL（带缓存）
	 *
	 * @param int $attachment_id 附件 ID
	 * @param string $size 尺寸
	 * @return string|null
	 */
	public function get_cloud_url( $attachment_id, $size = 'full' ) {
		$cache_key = "cloud_url_{$attachment_id}_{$size}";

		// 尝试从缓存获取
		$cached = $this->get( $cache_key );

		if ( null !== $cached ) {
			return $cached;
		}

		// 获取云端元数据
		$cloud_meta = $this->get_cloud_meta( $attachment_id );

		if ( ! $cloud_meta || empty( $cloud_meta['url'] ) ) {
			return null;
		}

		$url = '';

		if ( 'full' === $size ) {
			$url = $cloud_meta['url'];
		} else if ( isset( $cloud_meta['sizes'][ $size ]['url'] ) ) {
			$url = $cloud_meta['sizes'][ $size ]['url'];
		} else {
			$url = $cloud_meta['url'];
		}

		// 只缓存有效 URL 到内存缓存
		if ( ! empty( $url ) ) {
			$this->set( $cache_key, $url );
		}

		return $url;
	}
	
	/**
	 * 预热缓存
	 *
	 * @param array $attachment_ids 附件 ID 数组
	 */
	public function warm_cache( $attachment_ids ) {
		// 限制预热数量，防止内存溢出
		if ( count( $attachment_ids ) > 100 ) {
			$attachment_ids = array_slice( $attachment_ids, 0, 100 );
		}

		$this->batch_get_cloud_meta( $attachment_ids );

		// 释放内存
		unset( $attachment_ids );
	}

	/**
	 * 清理最旧的缓存条目（LRU 策略）
	 *
	 * @param int $count 要清理的条目数
	 */
	public function cleanup_old_cache( $count = 50 ) {
		// 从开头删除最旧的条目
		$keys_to_remove = array_slice( array_keys( self::$memory_cache ), 0, $count );

		foreach ( $keys_to_remove as $key ) {
			unset( self::$memory_cache[ $key ] );
		}

		// 更新计数
		self::$memory_cache_size = count( self::$memory_cache );
	}

	/**
	 * 强制清理内存缓存
	 */
	public function force_cleanup() {
		// 清空内存缓存
		self::$memory_cache = array();
		self::$memory_cache_size = 0;
	}
	
	/**
	 * 获取缓存统计
	 * 
	 * @return array
	 */
	public function get_stats() {
		return array(
			'memory_cache_count' => count( self::$memory_cache ),
			'object_cache_enabled' => $this->use_object_cache,
		);
	}
	
	/**
	 * 记住查询次数（用于调试）
	 */
	public static $query_count = 0;
	
	/**
	 * 增加查询计数
	 */
	public static function increment_query_count() {
		self::$query_count++;
	}
	
	/**
	 * 获取查询计数
	 * 
	 * @return int
	 */
	public static function get_query_count() {
		return self::$query_count;
	}
}
