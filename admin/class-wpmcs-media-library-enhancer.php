<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 媒体库增强器
 * 
 * 在媒体库界面显示云端状态、上传状态等信息
 */
class WPMCS_Media_Library_Enhancer {
	
	/**
	 * @var array
	 */
	private $settings;
	
	public function __construct( array $settings ) {
		$this->settings = $settings;
	}
	
	/**
	 * 注册所有钩子
	 */
	public function register_hooks() {
		// 媒体列表添加自定义列
		add_filter( 'manage_media_columns', array( $this, 'add_media_columns' ), 10, 1 );
		add_filter( 'manage_upload_sortable_columns', array( $this, 'add_sortable_columns' ), 10, 1 );
		
		// 填充自定义列数据
		add_action( 'manage_media_custom_column', array( $this, 'render_media_column' ), 10, 2 );
		
		// 添加媒体行操作
		add_filter( 'media_row_actions', array( $this, 'add_row_actions' ), 10, 3 );
		
		// 媒体编辑页面添加元数据框
		add_action( 'add_meta_boxes_attachment', array( $this, 'add_meta_box' ) );
		
		// 媒体编辑页面保存元数据
		add_action( 'edit_attachment', array( $this, 'save_meta_box' ), 10, 1 );
		
		// 添加 AJAX 处理
		add_action( 'wp_ajax_wpmcs_reupload_attachment', array( $this, 'ajax_reupload_attachment' ) );
		add_action( 'wp_ajax_wpmcs_copy_cloud_url', array( $this, 'ajax_copy_cloud_url' ) );
		
		// 加载前端资源
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		
		// 批量操作
		add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_reupload' ), 10, 3 );
		
		// 媒体筛选
		add_action( 'restrict_manage_posts', array( $this, 'add_filter_dropdown' ), 10, 2 );
		add_filter( 'request', array( $this, 'handle_filter' ), 10, 1 );
	}
	
	/**
	 * 添加自定义列
	 */
	public function add_media_columns( $columns ) {
		// 在"作者"列后添加云端状态列
		$new_columns = array();
		
		foreach ( $columns as $key => $value ) {
			$new_columns[ $key ] = $value;
			
			if ( 'author' === $key ) {
				$new_columns['wpmcs_cloud_status'] = '<span class="dashicons dashicons-cloud-upload" style="font-size: 16px; line-height: 1;"></span> 云端状态';
			}
		}
		
		return $new_columns;
	}
	
	/**
	 * 添加可排序列
	 */
	public function add_sortable_columns( $columns ) {
		$columns['wpmcs_cloud_status'] = 'wpmcs_cloud_status';
		return $columns;
	}
	
	/**
	 * 渲染自定义列内容
	 */
	public function render_media_column( $column_name, $attachment_id ) {
		if ( 'wpmcs_cloud_status' !== $column_name ) {
			return;
		}
		
		$has_cloud = WPMCS_Attachment_Manager::has_cloud_meta( $attachment_id );
		$error = WPMCS_Attachment_Manager::get_upload_error( $attachment_id );
		
		if ( $has_cloud ) {
			$cloud_meta = WPMCS_Attachment_Manager::get_cloud_meta( $attachment_id );
			
			echo '<span style="color: #46b450;">';
			echo '<span class="dashicons dashicons-yes-alt"></span> 已上传';
			echo '</span><br/>';
			
			echo '<small>';
			echo esc_html( $cloud_meta['provider'] );
			echo '</small><br/>';
			
			echo '<a href="#" class="wpmcs-copy-url" data-id="' . esc_attr( $attachment_id ) . '" data-url="' . esc_attr( $cloud_meta['url'] ) . '" style="text-decoration: none; color: #666;">';
			echo '<span class="dashicons dashicons-admin-page"></span> 复制 URL';
			echo '</a>';
			
		} elseif ( $error ) {
			echo '<span style="color: #dc3232;">';
			echo '<span class="dashicons dashicons-no-alt"></span> 失败';
			echo '</span><br/>';
			
			echo '<small style="color: #dc3232;">';
			echo esc_html( substr( $error, 0, 30 ) );
			if ( strlen( $error ) > 30 ) {
				echo '...';
			}
			echo '</small><br/>';
			
			echo '<a href="#" class="wpmcs-reupload" data-id="' . esc_attr( $attachment_id ) . '" style="text-decoration: none; color: #0073aa;">';
			echo '<span class="dashicons dashicons-update"></span> 重试上传';
			echo '</a>';
			
		} else {
			echo '<span style="color: #999;">';
			echo '<span class="dashicons dashicons-cloud"></span> 未上传';
			echo '</span><br/>';
			
			echo '<a href="#" class="wpmcs-reupload" data-id="' . esc_attr( $attachment_id ) . '" style="text-decoration: none; color: #0073aa;">';
			echo '<span class="dashicons dashicons-upload"></span> 立即上传';
			echo '</a>';
		}
	}
	
	/**
	 * 添加媒体行操作
	 */
	public function add_row_actions( $actions, $post, $detached ) {
		if ( 'attachment' !== $post->post_type ) {
			return $actions;
		}
		
		$has_cloud = WPMCS_Attachment_Manager::has_cloud_meta( $post->ID );
		
		if ( $has_cloud ) {
			$cloud_meta = WPMCS_Attachment_Manager::get_cloud_meta( $post->ID );
			
			$actions['wpmcs_view_cloud'] = sprintf(
				'<a href="%s" target="_blank">查看云端文件</a>',
				esc_url( $cloud_meta['url'] )
			);
			
			$actions['wpmcs_copy_url'] = sprintf(
				'<a href="#" class="wpmcs-copy-url" data-id="%s" data-url="%s">复制 URL</a>',
				esc_attr( $post->ID ),
				esc_attr( $cloud_meta['url'] )
			);
		} else {
			$actions['wpmcs_upload'] = sprintf(
				'<a href="#" class="wpmcs-reupload" data-id="%s">上传到云端</a>',
				esc_attr( $post->ID )
			);
		}
		
		return $actions;
	}
	
	/**
	 * 添加元数据框
	 */
	public function add_meta_box( $post ) {
		add_meta_box(
			'wpmcs_cloud_meta',
			'云端存储信息',
			array( $this, 'render_meta_box' ),
			'attachment',
			'side',
			'high'
		);
	}
	
	/**
	 * 渲染元数据框内容
	 */
	public function render_meta_box( $post ) {
		$has_cloud = WPMCS_Attachment_Manager::has_cloud_meta( $post->ID );
		$cloud_meta = WPMCS_Attachment_Manager::get_cloud_meta( $post->ID );
		$error = WPMCS_Attachment_Manager::get_upload_error( $post->ID );
		$preview_url = $this->get_preview_url( $post->ID );
		
		wp_nonce_field( 'wpmcs_save_meta_box', 'wpmcs_meta_box_nonce' );
		
		?>
		<div class="wpmcs-cloud-meta-box">
			<?php if ( $preview_url ) : ?>
				<p><strong>Preview</strong></p>
				<div style="margin: 0 0 12px; padding: 8px; border: 1px solid #ddd; background: #f6f7f7;">
					<img src="<?php echo esc_url( $preview_url ); ?>" alt="<?php echo esc_attr( get_the_title( $post ) ); ?>" style="display: block; max-width: 100%; max-height: 240px; width: auto; height: auto; object-fit: contain;">
				</div>
			<?php endif; ?>
			
			<?php if ( $has_cloud ) : ?>
				<p><strong>状态:</strong> <span style="color: #46b450;">✓ 已上传</span></p>
				<p><strong>云服务商:</strong> <?php echo esc_html( $cloud_meta['provider'] ); ?></p>
				<p><strong>文件 Key:</strong></p>
				<code style="display: block; padding: 8px; background: #f7f7f7; border: 1px solid #ddd; word-wrap: break-word; font-size: 12px;">
					<?php echo esc_html( $cloud_meta['key'] ); ?>
				</code>
				<p><strong>云端 URL:</strong></p>
				<input type="text" class="widefat" value="<?php echo esc_attr( $cloud_meta['url'] ); ?>" readonly>
				<p><strong>上传时间:</strong></p>
				<?php
				$uploaded_at = isset( $cloud_meta['uploaded_at'] ) ? $cloud_meta['uploaded_at'] : '';
				echo esc_html( $uploaded_at );
				?>
				
				<?php if ( ! empty( $cloud_meta['sizes'] ) ) : ?>
					<p><strong>缩略图:</strong></p>
					<ul style="margin-left: 20px; font-size: 12px;">
						<?php foreach ( $cloud_meta['sizes'] as $size_name => $size_data ) : ?>
							<li><?php echo esc_html( $size_name ); ?> - ✓ 已上传</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
				
				<hr>
				<p>
					<button type="button" class="button button-secondary wpmcs-reupload" data-id="<?php echo esc_attr( $post->ID ); ?>">
						<span class="dashicons dashicons-upload"></span> 重新上传
					</button>
					<button type="button" class="button button-secondary wpmcs-copy-url" data-id="<?php echo esc_attr( $post->ID ); ?>" data-url="<?php echo esc_attr( $cloud_meta['url'] ); ?>">
						<span class="dashicons dashicons-admin-page"></span> 复制 URL
					</button>
				</p>
				
			<?php elseif ( $error ) : ?>
				<p><strong>状态:</strong> <span style="color: #dc3232;">✗ 上传失败</span></p>
				<p><strong>错误信息:</strong></p>
				<div style="padding: 8px; background: #fff3f3; border: 1px solid #dc3232; color: #dc3232; word-wrap: break-word;">
					<?php echo esc_html( $error ); ?>
				</div>
				<p>
					<button type="button" class="button button-secondary wpmcs-reupload" data-id="<?php echo esc_attr( $post->ID ); ?>">
						<span class="dashicons dashicons-update"></span> 重试上传
					</button>
				</p>
				
			<?php else : ?>
				<p><strong>状态:</strong> <span style="color: #999;">○ 未上传</span></p>
				<p>该文件尚未上传到云端存储。</p>
				<p>
					<button type="button" class="button button-secondary wpmcs-reupload" data-id="<?php echo esc_attr( $post->ID ); ?>">
						<span class="dashicons dashicons-upload"></span> 立即上传
					</button>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}
	
	/**
	 * 保存元数据框
	 */
	/**
	 * Get a preview URL for the attachment.
	 *
	 * Prefers the local original image when it still exists, then falls back
	 * to the cloud original so previews keep working after local cleanup.
	 */
	private function get_preview_url( $attachment_id ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return '';
		}

		$attached_file = get_attached_file( $attachment_id );

		if ( $attached_file && file_exists( $attached_file ) ) {
			$local_url = wp_get_attachment_url( $attachment_id );
			if ( $local_url ) {
				return $local_url;
			}
		}

		$cloud_meta = WPMCS_Attachment_Manager::get_cloud_meta( $attachment_id );

		if ( ! empty( $cloud_meta['url'] ) ) {
			return $cloud_meta['url'];
		}

		return '';
	}

	public function save_meta_box( $attachment_id ) {
		// 这里可以根据需要处理保存逻辑
		// 目前主要用于重新上传等操作
	}
	
	/**
	 * AJAX 重新上传附件
	 */
	public function ajax_reupload_attachment() {
		// 验证权限
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		// 验证 nonce
		if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( $_POST['nonce'], 'wpmcs_reupload' ) ) {
			wp_send_json_error( array( 'message' => '安全验证失败' ) );
		}
		
		// 获取附件 ID
		$attachment_id = isset( $_POST['attachment_id'] ) ? intval( $_POST['attachment_id'] ) : 0;
		
		if ( ! $attachment_id ) {
			wp_send_json_error( array( 'message' => '无效的附件 ID' ) );
		}
		
		// 获取附件文件路径
		$file_path = get_attached_file( $attachment_id );
		
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			wp_send_json_error( array( 'message' => '文件不存在' ) );
		}
		
		// 获取云存储驱动
		$storage = wpmcs_create_storage_driver( $this->settings );
		
		if ( is_wp_error( $storage ) ) {
			wp_send_json_error( array( 'message' => $storage->get_error_message() ) );
		}
		
		// 构建云存储 key
		$cloud_key = $this->build_cloud_key( $file_path );
		
		// 执行上传
		try {
			$prepared = class_exists( 'WPMCS_WebP_Converter' )
				? WPMCS_WebP_Converter::prepare_upload( $file_path, $cloud_key, $this->settings )
				: array(
					'file_path' => $file_path,
					'cloud_key' => $cloud_key,
					'mime_type' => isset( wp_check_filetype( $file_path )['type'] ) ? (string) wp_check_filetype( $file_path )['type'] : '',
					'converted' => false,
					'temp_file' => '',
				);

			$result = $storage->upload( $prepared['file_path'], $prepared['cloud_key'] );
			
			if ( is_wp_error( $result ) ) {
				if ( class_exists( 'WPMCS_WebP_Converter' ) ) {
					WPMCS_WebP_Converter::cleanup( $prepared );
				}
				wp_send_json_error( array( 'message' => $result->get_error_message() ) );
			}
			
			// 保存云端元数据
			$cloud_url = is_array( $result ) ? $result['url'] : $result;
			$cloud_key = is_array( $result ) ? $result['key'] : $prepared['cloud_key'];
			
			$cloud_meta = array(
				'provider' => $this->settings['provider'],
				'key' => $cloud_key,
				'url' => $cloud_url,
				'sizes' => array(),
				'uploaded_at' => current_time( 'mysql' )
			);
			
			if ( class_exists( 'WPMCS_WebP_Converter' ) ) {
				WPMCS_WebP_Converter::cleanup( $prepared );
			}

			WPMCS_Attachment_Manager::save_cloud_meta( $attachment_id, $cloud_meta );
			
			wp_send_json_success( array(
				'message' => '上传成功',
				'cloud_url' => $cloud_url,
				'cloud_key' => $cloud_key
			) );
			
		} catch ( Exception $e ) {
			if ( isset( $prepared ) && class_exists( 'WPMCS_WebP_Converter' ) ) {
				WPMCS_WebP_Converter::cleanup( $prepared );
			}
			wp_send_json_error( array( 'message' => $e->getMessage() ) );
		}
	}
	
	/**
	 * AJAX 复制云端 URL
	 */
	public function ajax_copy_cloud_url() {
		// 验证权限
		if ( ! current_user_can( 'upload_files' ) ) {
			wp_send_json_error( array( 'message' => '权限不足' ) );
		}
		
		// 获取 URL
		$url = isset( $_POST['url'] ) ? esc_url_raw( $_POST['url'] ) : '';
		
		if ( ! $url ) {
			wp_send_json_error( array( 'message' => 'URL 为空' ) );
		}
		
		wp_send_json_success( array( 'url' => $url ) );
	}
	
	/**
	 * 添加批量操作
	 */
	public function add_bulk_actions( $bulk_actions ) {
		$bulk_actions['wpmcs_upload_cloud'] = '上传到云端';
		return $bulk_actions;
	}
	
	/**
	 * 处理批量上传
	 */
	public function handle_bulk_reupload( $redirect_to, $action, $attachment_ids ) {
		if ( 'wpmcs_upload_cloud' !== $action ) {
			return $redirect_to;
		}
		
		$uploaded = 0;
		$failed = 0;
		$errors = array();
		
		foreach ( $attachment_ids as $attachment_id ) {
			$file_path = get_attached_file( $attachment_id );
			
			if ( ! $file_path || ! file_exists( $file_path ) ) {
				$failed++;
				continue;
			}
			
			$storage = wpmcs_create_storage_driver( $this->settings );
			
			if ( is_wp_error( $storage ) ) {
				$failed++;
				continue;
			}
			
			$cloud_key = $this->build_cloud_key( $file_path );
			
			try {
				$prepared = class_exists( 'WPMCS_WebP_Converter' )
					? WPMCS_WebP_Converter::prepare_upload( $file_path, $cloud_key, $this->settings )
					: array(
						'file_path' => $file_path,
						'cloud_key' => $cloud_key,
						'mime_type' => isset( wp_check_filetype( $file_path )['type'] ) ? (string) wp_check_filetype( $file_path )['type'] : '',
						'converted' => false,
						'temp_file' => '',
					);

				$result = $storage->upload( $prepared['file_path'], $prepared['cloud_key'] );
				
				if ( is_wp_error( $result ) ) {
					if ( class_exists( 'WPMCS_WebP_Converter' ) ) {
						WPMCS_WebP_Converter::cleanup( $prepared );
					}
					$failed++;
					$errors[] = "附件 {$attachment_id}: " . $result->get_error_message();
					continue;
				}
				
				$cloud_url = is_array( $result ) ? $result['url'] : $result;
				$cloud_key = is_array( $result ) ? $result['key'] : $prepared['cloud_key'];
				
				$cloud_meta = array(
					'provider' => $this->settings['provider'],
					'key' => $cloud_key,
					'url' => $cloud_url,
					'sizes' => array(),
					'uploaded_at' => current_time( 'mysql' )
				);
				
				if ( class_exists( 'WPMCS_WebP_Converter' ) ) {
					WPMCS_WebP_Converter::cleanup( $prepared );
				}

				WPMCS_Attachment_Manager::save_cloud_meta( $attachment_id, $cloud_meta );
				$uploaded++;
				
			} catch ( Exception $e ) {
				if ( isset( $prepared ) && class_exists( 'WPMCS_WebP_Converter' ) ) {
					WPMCS_WebP_Converter::cleanup( $prepared );
				}
				$failed++;
				$errors[] = "附件 {$attachment_id}: " . $e->getMessage();
			}
		}
		
		$redirect_to = add_query_arg( array(
			'wpmcs_uploaded' => $uploaded,
			'wpmcs_failed' => $failed,
		), $redirect_to );
		
		return $redirect_to;
	}
	
	/**
	 * 添加筛选下拉菜单
	 */
	public function add_filter_dropdown( $post_type, $which ) {
		if ( 'attachment' !== $post_type ) {
			return;
		}
		
		?>
		<select name="wpmcs_cloud_status">
			<option value="">所有云端状态</option>
			<option value="uploaded" <?php selected( isset( $_GET['wpmcs_cloud_status'] ) ? $_GET['wpmcs_cloud_status'] : '', 'uploaded' ); ?>>已上传</option>
			<option value="not_uploaded" <?php selected( isset( $_GET['wpmcs_cloud_status'] ) ? $_GET['wpmcs_cloud_status'] : '', 'not_uploaded' ); ?>>未上传</option>
			<option value="error" <?php selected( isset( $_GET['wpmcs_cloud_status'] ) ? $_GET['wpmcs_cloud_status'] : '', 'error' ); ?>>上传失败</option>
		</select>
		<?php
	}
	
	/**
	 * 处理筛选
	 */
	public function handle_filter( $query_vars ) {
		if ( ! isset( $query_vars['post_type'] ) || 'attachment' !== $query_vars['post_type'] ) {
			return $query_vars;
		}
		
		if ( ! isset( $_GET['wpmcs_cloud_status'] ) ) {
			return $query_vars;
		}
		
		$status = sanitize_text_field( $_GET['wpmcs_cloud_status'] );
		
		if ( 'uploaded' === $status ) {
			// 只显示已上传的
			add_filter( 'posts_where', array( $this, 'filter_uploaded' ) );
		} elseif ( 'not_uploaded' === $status ) {
			// 只显示未上传的
			add_filter( 'posts_where', array( $this, 'filter_not_uploaded' ) );
		} elseif ( 'error' === $status ) {
			// 只显示上传失败的
			add_filter( 'posts_where', array( $this, 'filter_error' ) );
		}
		
		return $query_vars;
	}
	
	/**
	 * 筛选已上传
	 */
	public function filter_uploaded( $where ) {
		global $wpdb;
		return $where . " AND EXISTS (
			SELECT 1 FROM {$wpdb->postmeta} 
			WHERE post_id = {$wpdb->posts}.ID 
			AND meta_key IN ('_wpmcs_cloud_meta', '_wpmcs_cloud_url') 
			AND meta_value != ''
		)";
	}
	
	/**
	 * 筛选未上传
	 */
	public function filter_not_uploaded( $where ) {
		global $wpdb;
		return $where . " AND NOT EXISTS (
			SELECT 1 FROM {$wpdb->postmeta} 
			WHERE post_id = {$wpdb->posts}.ID 
			AND meta_key IN ('_wpmcs_cloud_meta', '_wpmcs_cloud_url') 
			AND meta_value != ''
		)";
	}
	
	/**
	 * 筛选失败
	 */
	public function filter_error( $where ) {
		global $wpdb;
		return $where . " AND EXISTS (
			SELECT 1 FROM {$wpdb->postmeta} 
			WHERE post_id = {$wpdb->posts}.ID 
			AND meta_key = '_wpmcs_last_error' 
			AND meta_value != ''
		)";
	}
	
	/**
	 * 加载前端资源
	 */
	public function enqueue_assets( $hook ) {
		if ( 'upload.php' === $hook || 'post.php' === $hook ) {
			// 注册 JavaScript
			wp_register_script(
				'wpmcs-media-library',
				WPMCS_PLUGIN_URL . 'assets/js/media-library.js',
				array(),
				WPMCS_VERSION,
				true
			);
			
			// 传递数据到 JavaScript
			wp_localize_script( 'wpmcs-media-library', 'wpmcsMedia', array(
				'nonce_reupload' => wp_create_nonce( 'wpmcs_reupload' ),
				'ajax_url' => admin_url( 'admin-ajax.php' ),
				'text_uploading' => '上传中...',
				'text_success' => '上传成功',
				'text_failed' => '上传失败',
				'text_copied' => '已复制'
			) );
			
			// 加载脚本
			wp_enqueue_script( 'wpmcs-media-library' );
			
			// 加载样式
			wp_enqueue_style(
				'wpmcs-media-library',
				WPMCS_PLUGIN_URL . 'assets/css/media-library.css',
				array(),
				WPMCS_VERSION
			);
		}
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
}
