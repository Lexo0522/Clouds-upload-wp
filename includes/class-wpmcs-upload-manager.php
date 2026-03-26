<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPMCS_Upload_Manager {
	/**
	 * @var WPMCS_Cloud_Adapter
	 */
	private $adapter;

	/**
	 * @var array<string, mixed>
	 */
	private $settings = array();

	public function __construct( WPMCS_Cloud_Adapter $adapter, array $settings ) {
		$this->adapter  = $adapter;
		$this->settings = $settings;
	}

	public function register_hooks() {
		add_filter( 'wp_handle_upload_prefilter', array( $this, 'maybe_rename_uploaded_file' ), 10, 1 );
		add_filter( 'wp_handle_sideload_prefilter', array( $this, 'maybe_rename_uploaded_file' ), 10, 1 );
		add_filter( 'wp_generate_attachment_metadata', array( $this, 'sync_attachment_to_cloud' ), 20, 2 );
		add_filter( 'wp_get_attachment_url', array( $this, 'filter_attachment_url' ), 20, 2 );
		add_filter( 'image_downsize', array( $this, 'filter_image_downsize' ), 20, 3 );
		add_filter( 'wp_calculate_image_srcset', array( $this, 'filter_image_srcset' ), 20, 5 );
	}

	public function maybe_rename_uploaded_file( $file ) {
		if ( ! $this->is_enabled() || empty( $this->settings['auto_rename'] ) || empty( $file['name'] ) ) {
			return $file;
		}

		$file['name'] = wpmcs_generate_unique_filename( $file['name'] );

		return $file;
	}

	public function sync_attachment_to_cloud( $metadata, $attachment_id ) {
		if ( ! $this->is_enabled() ) {
			return $metadata;
		}

		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return $metadata;
		}

		$cloud_meta = array(
			'provider' => $this->adapter->get_provider_key(),
			'key'      => '',
			'url'      => '',
			'sizes'    => array(),
		);

		$original = $this->upload_single_file( $file_path );
		if ( is_wp_error( $original ) ) {
			update_post_meta( $attachment_id, '_wpmcs_last_error', $original->get_error_message() );
			return $metadata;
		}

		$preview_data = WPMCS_Attachment_Manager::get_preview_file_data( $attachment_id );
		$preview_upload = null;

		if ( ! empty( $preview_data['path'] ) && file_exists( $preview_data['path'] ) ) {
			$preview_upload = $this->upload_single_file( $preview_data['path'] );
			if ( is_wp_error( $preview_upload ) ) {
				$preview_upload = null;
			}
		}

		$cloud_meta['key'] = $original['key'];
		$cloud_meta['url'] = $original['url'];

		if ( $preview_upload && ! empty( $preview_data['file'] ) ) {
			$cloud_meta['sizes']['thumbnail'] = array(
				'key'    => $preview_upload['key'],
				'url'    => $preview_upload['url'],
				'file'   => (string) $preview_data['file'],
				'width'  => isset( $preview_data['width'] ) ? (int) $preview_data['width'] : 0,
				'height' => isset( $preview_data['height'] ) ? (int) $preview_data['height'] : 0,
			);
		}

		WPMCS_Attachment_Manager::save_cloud_meta( $attachment_id, $cloud_meta );
		delete_post_meta( $attachment_id, '_wpmcs_last_error' );

		$cleanup_settings = $this->settings;
		if ( ! $preview_upload && ! empty( $preview_data['path'] ) ) {
			$cleanup_settings['keep_local_file'] = '1';
		}
		WPMCS_Attachment_Manager::delete_local_files( $attachment_id, $cleanup_settings );

		if ( is_array( $metadata ) ) {
			$metadata['wpmcs_cloud'] = $cloud_meta;
		}

		return $metadata;
	}

	public function filter_attachment_url( $url, $attachment_id ) {
		if ( ! $this->should_replace_url() ) {
			return $url;
		}

		$cloud_meta = $this->get_cloud_meta( $attachment_id );
		if ( empty( $cloud_meta['url'] ) ) {
			return $url;
		}

		if ( $this->should_track_requests() ) {
			return WPMCS_Attachment_Manager::get_tracking_url( $attachment_id, 'full' );
		}

		return $cloud_meta['url'];
	}

	public function filter_image_downsize( $downsize, $attachment_id, $size ) {
		if ( ! $this->should_replace_url() ) {
			return $downsize;
		}

		$cloud_meta = $this->get_cloud_meta( $attachment_id );
		if ( empty( $cloud_meta['url'] ) ) {
			return $downsize;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		$width = isset( $metadata['width'] ) ? (int) $metadata['width'] : 0;
		$height = isset( $metadata['height'] ) ? (int) $metadata['height'] : 0;

		if ( 'full' === $size || 'original' === $size ) {
			$url = $this->should_track_requests()
				? WPMCS_Attachment_Manager::get_tracking_url( $attachment_id, 'full' )
				: $cloud_meta['url'];
			return array( $url, $width, $height, false );
		}

		if ( is_string( $size ) && ! empty( $cloud_meta['sizes'][ $size ]['url'] ) ) {
			$size_width = isset( $cloud_meta['sizes'][ $size ]['width'] ) ? (int) $cloud_meta['sizes'][ $size ]['width'] : 0;
			$size_height = isset( $cloud_meta['sizes'][ $size ]['height'] ) ? (int) $cloud_meta['sizes'][ $size ]['height'] : 0;
			$url = $this->should_track_requests()
				? WPMCS_Attachment_Manager::get_tracking_url( $attachment_id, $size )
				: $cloud_meta['sizes'][ $size ]['url'];

			return array( $url, $size_width, $size_height, true );
		}

		$url = $this->should_track_requests()
			? WPMCS_Attachment_Manager::get_tracking_url( $attachment_id, 'full' )
			: $cloud_meta['url'];

		return array( $url, $width, $height, false );
	}

	public function filter_image_srcset( $sources, $size_array, $image_src, $image_meta, $attachment_id ) {
		if ( ! $this->should_replace_url() || empty( $sources ) ) {
			return $sources;
		}

		$cloud_meta = $this->get_cloud_meta( $attachment_id );
		if ( empty( $cloud_meta['url'] ) ) {
			return $sources;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( ! is_array( $metadata ) ) {
			return $sources;
		}

		$replacement = array();

		$full_width = isset( $metadata['width'] ) ? (int) $metadata['width'] : 0;
		if ( $full_width <= 0 && ! empty( $size_array[0] ) ) {
			$full_width = (int) $size_array[0];
		}
		if ( $full_width > 0 ) {
			$replacement[ $full_width ] = array(
				'url'        => $this->should_track_requests()
					? WPMCS_Attachment_Manager::get_tracking_url( $attachment_id, 'full' )
					: $cloud_meta['url'],
				'descriptor' => 'w',
				'value'      => $full_width,
			);
		}

		if ( ! empty( $cloud_meta['sizes']['thumbnail']['url'] ) ) {
			$thumb_width = isset( $cloud_meta['sizes']['thumbnail']['width'] ) ? (int) $cloud_meta['sizes']['thumbnail']['width'] : 0;
			if ( $thumb_width <= 0 && ! empty( $metadata['sizes']['thumbnail']['width'] ) ) {
				$thumb_width = (int) $metadata['sizes']['thumbnail']['width'];
			}
			if ( $thumb_width <= 0 && ! empty( $size_array[0] ) ) {
				$thumb_width = (int) $size_array[0];
			}

			if ( $thumb_width > 0 ) {
				$replacement[ $thumb_width ] = array(
					'url'        => $this->should_track_requests()
						? WPMCS_Attachment_Manager::get_tracking_url( $attachment_id, 'thumbnail' )
						: $cloud_meta['sizes']['thumbnail']['url'],
					'descriptor' => 'w',
					'value'      => $thumb_width,
				);
			}
		}

		if ( empty( $replacement ) ) {
			return $sources;
		}

		ksort( $replacement );
		return $replacement;
	}

	private function is_enabled() {
		return $this->adapter->is_enabled() && $this->adapter->is_configured();
	}

	private function should_replace_url() {
		return $this->is_enabled() && ! empty( $this->settings['replace_url'] );
	}

	private function should_track_requests() {
		return $this->should_replace_url() && ! empty( $this->settings['enable_logging'] );
	}

	/**
	 * @return array<string, mixed>|WP_Error
	 */
	private function upload_single_file( $file_path ) {
		$cloud_key = $this->build_cloud_key( $file_path );
		$prepared = class_exists( 'WPMCS_WebP_Converter' )
			? WPMCS_WebP_Converter::prepare_upload( $file_path, $cloud_key, $this->settings )
			: array(
				'file_path' => $file_path,
				'original_path' => $file_path,
				'original_cloud_key' => $cloud_key,
				'cloud_key' => $cloud_key,
				'mime_type' => isset( wp_check_filetype( $file_path )['type'] ) ? (string) wp_check_filetype( $file_path )['type'] : '',
				'original_mime_type' => isset( wp_check_filetype( $file_path )['type'] ) ? (string) wp_check_filetype( $file_path )['type'] : '',
				'converted' => false,
				'temp_file' => '',
			);

		try {
			try {
				$result = $this->adapter->upload(
					$prepared['file_path'],
					$prepared['cloud_key'],
					isset( $prepared['mime_type'] ) ? (string) $prepared['mime_type'] : ''
				);

				if ( is_wp_error( $result ) ) {
					throw new RuntimeException( $result->get_error_message() );
				}
			} catch ( Throwable $e ) {
				if ( empty( $prepared['converted'] ) ) {
					throw $e;
				}

				$result = $this->adapter->upload(
					$prepared['original_path'],
					isset( $prepared['original_cloud_key'] ) ? (string) $prepared['original_cloud_key'] : $cloud_key,
					isset( $prepared['original_mime_type'] ) ? (string) $prepared['original_mime_type'] : ''
				);

				if ( is_wp_error( $result ) ) {
					throw new RuntimeException( $result->get_error_message() );
				}
			}

			return $result;
		} finally {
			if ( class_exists( 'WPMCS_WebP_Converter' ) ) {
				WPMCS_WebP_Converter::cleanup( $prepared );
			}
		}
	}

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
			$relative = wp_basename( $file_path );
		}

		return $prefix . str_replace( '\\', '/', $relative );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function get_cloud_meta( $attachment_id ) {
		if ( class_exists( 'WPMCS_Attachment_Manager' ) ) {
			$cloud_meta = WPMCS_Attachment_Manager::get_cloud_meta( $attachment_id );
			if ( is_array( $cloud_meta ) ) {
				return $cloud_meta;
			}
		}

		$cloud_meta = get_post_meta( $attachment_id, '_wpmcs_cloud_meta', true );
		if ( is_array( $cloud_meta ) ) {
			return $cloud_meta;
		}

		$metadata = wp_get_attachment_metadata( $attachment_id );
		if ( is_array( $metadata ) && ! empty( $metadata['wpmcs_cloud'] ) && is_array( $metadata['wpmcs_cloud'] ) ) {
			return $metadata['wpmcs_cloud'];
		}

		return array();
	}
}
