<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// 确保 WordPress 函数可用
if ( ! function_exists( 'add_filter' ) ) {
	require_once ABSPATH . 'wp-includes/plugin.php';
}

if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	require_once ABSPATH . 'wp-includes/post.php';
}

if ( ! function_exists( 'wp_upload_dir' ) ) {
	require_once ABSPATH . 'wp-includes/functions.php';
}

/**
 * WordPress 上传拦截器
 * 
 * 负责拦截 WordPress 默认上传流程，将文件上传到云存储
 * 并替换默认的附件 URL
 */
class WPMCS_Upload_Interceptor {
	
	/**
	 * @var Cloud_Uploader
	 */
	private $uploader;
	
	/**
	 * @var array
	 */
	private $settings;
	
	/**
	 * @var bool
	 */
	private $is_uploading = false;
	
	/**
	 * @var WPMCS_Cache_Manager|null
	 */
	private $cache_manager;
	
	/**
	 * @var WPMCS_Async_Queue|null
	 */
	private $async_queue;
	
	public function __construct( Cloud_Uploader $uploader, array $settings, $cache_manager = null, $async_queue = null ) {
		$this->uploader = $uploader;
		$this->settings = $settings;
		$this->cache_manager = $cache_manager;
		$this->async_queue = $async_queue;
	}
	
	/**
	 * 注册所有 WordPress 钩子
	 */
	public function register_hooks() {
		// 拦截上传过程
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'intercept_upload' ), 10, 1 );
		add_filter( 'wp_handle_sideload_prefilter', array( $this, 'intercept_upload' ), 10, 1 );
		
		// 上传完成后处理
		add_filter( 'wp_handle_upload', array( $this, 'handle_upload_complete' ), 10, 2 );
		add_filter( 'wp_handle_sideload', array( $this, 'handle_upload_complete' ), 10, 2 );
		
		// 替换附件 URL
		add_filter( 'wp_get_attachment_url', array( $this, 'replace_attachment_url' ), 10, 2 );
		add_filter( 'wp_get_attachment_image_src', array( $this, 'replace_image_src' ), 10, 4 );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'replace_image_srcset' ), 10, 5 );
		
		// 删除附件时同步删除云端文件
		add_action( 'delete_attachment', array( $this, 'delete_cloud_file' ), 10, 1 );
		
		// 处理媒体库插入
		add_filter( 'media_send_to_editor', array( $this, 'replace_editor_urls' ), 10, 3 );
		add_filter( 'the_content', array( $this, 'replace_content_urls' ), 20 );
	}
	
	/**
	 * 拦截上传过程
	 */
	public function intercept_upload( $file ) {
		if ( ! $this->is_enabled() ) {
			return $file;
		}
		
		// 标记正在上传
		$this->is_uploading = true;
		
		// 如果需要自动重命名
		return $file;
	}
	
	/**
	 * 处理上传完成
	 */
	public function handle_upload_complete( $upload, $context ) {
		if ( ! $this->is_enabled() || ! $this->is_uploading ) {
			$this->is_uploading = false;
			return $upload;
		}
		
		$this->is_uploading = false;
		
		// 如果启用了异步上传，将任务加入队列
		if ( ! empty( $this->settings['async_upload'] ) && $this->async_queue ) {
			// 需要先创建附件记录才能获取 ID
			// 这里使用临时方式，后续通过元数据钩子处理
			return $upload;
		}
		
		// 获取上传的文件信息
		$file_path = $upload['file'];
		$file_name = basename( $file_path );
		
		// 构建云存储 key
		$cloud_key = $this->build_cloud_key( $file_path );
		
		try {
			// 上传到云存储
			$cloud_result = $this->uploader->upload_file( array(
				'file_path' => $file_path,
				'file_name' => $cloud_key
			) );
			$cloud_url = is_array( $cloud_result ) && isset( $cloud_result['url'] ) ? (string) $cloud_result['url'] : (string) $cloud_result;
			$cloud_key = is_array( $cloud_result ) && isset( $cloud_result['key'] ) ? (string) $cloud_result['key'] : $cloud_key;
			
			// 保存云存储信息到上传结果中
			$upload['wpmcs_cloud'] = array(
				'key' => $cloud_key,
				'url' => $cloud_url,
				'converted' => is_array( $cloud_result ) && ! empty( $cloud_result['converted'] ),
				'provider' => $this->settings['provider'] ?? 'qiniu'
			);
			
			// 如果启用了替换 URL，则立即替换
			if ( ! empty( $this->settings['replace_url'] ) ) {
				$upload['url'] = $cloud_url;
			}
			
		} catch ( Exception $e ) {
			// 记录错误，但不中断上传流程
			error_log( 'WPMCS Upload Error: ' . $e->getMessage() );
		}
		
		return $upload;
	}
	
	/**
	 * 替换附件 URL
	 */
	public function replace_attachment_url( $url, $attachment_id ) {
		if ( ! $this->should_replace_url() ) {
			return $url;
		}
		
		$cloud_url = $this->get_cloud_url( $attachment_id );
		if ( $cloud_url && $this->should_track_requests() ) {
			return WPMCS_Attachment_Manager::get_tracking_url( $attachment_id, 'full' );
		}
		
		return $cloud_url ?: $url;
	}
	
	/**
	 * 替换图片 src
	 */
	public function replace_image_src( $image, $attachment_id, $size, $icon ) {
		if ( ! $this->should_replace_url() || empty( $image[0] ) ) {
			return $image;
		}
		
		$cloud_url = $this->get_cloud_url( $attachment_id, $size );
		
		if ( $cloud_url ) {
			if ( $this->should_track_requests() ) {
				$image[0] = WPMCS_Attachment_Manager::get_tracking_url( $attachment_id, is_string( $size ) ? $size : 'full' );
				return $image;
			}
			$image[0] = $cloud_url;
		}
		
		return $image;
	}
	
	/**
	 * 替换图片 srcset
	 */
	public function replace_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		return $sources;
	}
	
	/**
	 * 删除云端文件
	 */
	public function delete_cloud_file( $attachment_id ) {
		if ( ! $this->is_enabled() ) {
			return;
		}
		
		// 获取云端文件信息
		$cloud_meta = get_post_meta( $attachment_id, '_wpmcs_cloud_meta', true );
		
		if ( empty( $cloud_meta['key'] ) ) {
			return;
		}
		
		try {
			// 删除云端文件
			$this->uploader->get_storage()->delete( $cloud_meta['key'] );
			
			// 记录删除日志
			error_log( "WPMCS: Deleted cloud file - {$cloud_meta['key']}" );
			if ( function_exists( 'wpmcs_get_logger' ) ) {
				wpmcs_get_logger()->info( WPMCS_Logger::TYPE_DELETE, '删除云文件成功', array(
					'attachment_id' => $attachment_id,
					'cloud_key' => $cloud_meta['key'],
				) );
			}
			
		} catch ( Exception $e ) {
			error_log( 'WPMCS Delete Error: ' . $e->getMessage() );
			if ( function_exists( 'wpmcs_get_logger' ) ) {
				wpmcs_get_logger()->error( WPMCS_Logger::TYPE_DELETE, '删除云文件失败', array(
					'attachment_id' => $attachment_id,
					'cloud_key' => $cloud_meta['key'],
					'error' => $e->getMessage(),
				) );
			}
		}
	}
	
	/**
	 * 替换编辑器中的 URL
	 */
	public function replace_editor_urls( $html, $attachment_id, $attachment ) {
		if ( ! $this->should_replace_url() ) {
			return $html;
		}
		
		$html = (string) $html;
		$cloud_url = $this->get_cloud_url( $attachment_id );
		
		if ( $cloud_url ) {
			// 替换所有本地 URL 为云端 URL
			$local_url = (string) wp_get_attachment_url( $attachment_id );
			$cloud_url = (string) $cloud_url;
			if ( $this->should_track_requests() ) {
				$cloud_url = WPMCS_Attachment_Manager::get_tracking_url( $attachment_id, 'full' );
			}
			if ( '' === $local_url || '' === $cloud_url ) {
				return $html;
			}
			$html = str_replace( $local_url, $cloud_url, $html );
		}
		
		return $html;
	}

	/**
	 * 替换正文中的云端直链为跟踪入口。
	 */
	public function replace_content_urls( $content ) {
		if ( ! $this->should_track_requests() ) {
			return $content;
		}

		$content = (string) $content;
		if ( '' === $content || false === strpos( $content, 'http' ) ) {
			return $content;
		}

		$map = class_exists( 'WPMCS_Attachment_Manager' ) ? WPMCS_Attachment_Manager::get_tracking_url_map() : array();
		if ( empty( $map ) ) {
			return $content;
		}

		return str_replace( array_keys( $map ), array_values( $map ), $content );
	}
	
	/**
	 * 构建云存储 key
	 */
	private function build_cloud_key( $file_path ) {
		$file_path = (string) $file_path;

		if ( '' === trim( $file_path ) ) {
			return '';
		}

		$uploads = wpmcs_get_upload_dir();
		$base_dir = isset( $uploads['basedir'] ) ? (string) $uploads['basedir'] : '';
		$normalized_path = wp_normalize_path( $file_path );
		$normalized_base = '' !== $base_dir ? wp_normalize_path( $base_dir ) : '';
		$relative = '' !== $normalized_base ? ltrim( str_replace( $normalized_base, '', $normalized_path ), '/' ) : ltrim( $normalized_path, '/' );
		$prefix = '';
		
		if ( ! empty( $this->settings['upload_path'] ) ) {
			$prefix = trim( (string) $this->settings['upload_path'], '/' ) . '/';
		}
		
		if ( '' === $relative ) {
			$relative = basename( $file_path );
		}
		
		return $prefix . str_replace( '\\', '/', $relative );
	}
	
	/**
	 * 获取云端 URL
	 */
	private function get_cloud_url( $attachment_id, $size = 'full' ) {
		// 使用缓存管理器
		if ( $this->cache_manager ) {
			return $this->cache_manager->get_cloud_url( $attachment_id, $size );
		}
		
		if ( class_exists( 'WPMCS_Attachment_Manager' ) ) {
			$cloud_meta = WPMCS_Attachment_Manager::get_cloud_meta( $attachment_id );
		} else {
			$cloud_meta = get_post_meta( $attachment_id, '_wpmcs_cloud_meta', true );
		}
		
		if ( empty( $cloud_meta ) ) {
			// 尝试从附件元数据中获取
			$metadata = wp_get_attachment_metadata( $attachment_id );
			if ( ! empty( $metadata['wpmcs_cloud'] ) ) {
				$cloud_meta = $metadata['wpmcs_cloud'];
			}
		}
		
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
		
		return $cloud_meta['url'];
	}
	
	/**
	 * 检查是否启用
	 */
	private function is_enabled() {
		return ! empty( $this->settings['enabled'] ) && 
			   ! empty( $this->settings['provider'] ) &&
			   ! empty( $this->settings['access_key'] ) &&
			   ! empty( $this->settings['secret_key'] ) &&
			   ! empty( $this->settings['bucket'] );
	}
	
	/**
	 * 检查是否应该替换 URL
	 */
	private function should_replace_url() {
		return $this->is_enabled() && ! empty( $this->settings['replace_url'] );
	}

	/**
	 * 是否将媒体请求转入跟踪入口。
	 */
	private function should_track_requests() {
		return $this->should_replace_url() && ! empty( $this->settings['enable_logging'] );
	}
}
