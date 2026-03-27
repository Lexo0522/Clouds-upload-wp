<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/interface-cloud-storage-interface.php';

class Cloud_Uploader {
	/**
	 * @var Cloud_Storage_Interface
	 */
	protected $storage;

	/**
	 * @var array
	 */
	protected $settings;

	/**
	 * @var WPMCS_Logger|null
	 */
	protected $logger = null;

	public function __construct( Cloud_Storage_Interface $storage, array $settings = array() ) {
		$this->storage = $storage;
		$this->settings = $settings;
		$this->logger = class_exists( 'WPMCS_Logger' ) ? new WPMCS_Logger( $settings ) : null;
		if ( $this->logger ) {
			// Upload events are always recorded so the logs page can reflect actual transfer history.
			$this->logger->set_enabled( true );
		}
	}

	public function set_storage( Cloud_Storage_Interface $storage ) {
		$this->storage = $storage;
		return $this;
	}

	public function get_storage() {
		return $this->storage;
	}

	public function set_settings( array $settings ) {
		$this->settings = $settings;
		return $this;
	}

	/**
	 * Upload a file to cloud storage.
	 *
	 * Returns a normalized result array with the final cloud key and URL.
	 *
	 * @param array $file Upload payload.
	 * @return array<string, mixed>
	 */
	public function upload_file( array $file ) {
		$file_path = $this->resolve_file_path( $file );
		$file_name = $this->resolve_file_name( $file );

		$this->log_info(
			WPMCS_Logger::TYPE_UPLOAD,
			'Preparing upload',
			array(
				'file_path' => $file_path,
				'file_name' => $file_name,
			)
		);

		if ( ! file_exists( $file_path ) ) {
			throw new RuntimeException( "File not found: {$file_path}" );
		}

		if ( filesize( $file_path ) <= 0 ) {
			throw new RuntimeException( "File size is 0: {$file_path}" );
		}

		$prepared = class_exists( 'WPMCS_WebP_Converter' )
			? WPMCS_WebP_Converter::prepare_upload( $file_path, $file_name, $this->settings )
			: array(
				'original_path' => $file_path,
				'file_path'     => $file_path,
				'cloud_key'     => $file_name,
				'mime_type'     => $this->get_mime_type( $file_path ),
				'converted'     => false,
				'temp_file'     => '',
			);

		try {
			$result = $this->attempt_upload( $prepared );

			$this->log_info(
				WPMCS_Logger::TYPE_UPLOAD,
				'Upload succeeded',
				array(
					'file_path' => $prepared['file_path'],
					'file_name' => isset( $result['key'] ) ? (string) $result['key'] : (string) $prepared['cloud_key'],
					'cloud_url' => isset( $result['url'] ) ? (string) $result['url'] : '',
					'converted' => ! empty( $result['converted'] ),
				)
			);

			return $result;
		} finally {
			if ( class_exists( 'WPMCS_WebP_Converter' ) ) {
				WPMCS_WebP_Converter::cleanup( $prepared );
			}
		}
	}

	public function upload_files( array $files ) {
		$results = array();

		foreach ( $files as $index => $file ) {
			try {
				$uploaded = $this->upload_file( $file );
				$results[ $index ] = array(
					'success'   => true,
					'url'       => isset( $uploaded['url'] ) ? $uploaded['url'] : '',
					'key'       => isset( $uploaded['key'] ) ? $uploaded['key'] : '',
					'converted' => ! empty( $uploaded['converted'] ),
				);
			} catch ( Exception $e ) {
				$results[ $index ] = array(
					'success' => false,
					'error'   => $e->getMessage(),
				);
			}
		}

		return $results;
	}

	public function generate_unique_name( $original_name, $pattern = '' ) {
		$original_name = (string) $original_name;

		if ( ! empty( $pattern ) ) {
			return wpmcs_generate_unique_filename_with_pattern( $original_name, $pattern );
		}

		$extension = pathinfo( $original_name, PATHINFO_EXTENSION );
		$prefix = gmdate( 'YmdHis' );
		$random = wp_generate_password( 6, false, false );

		if ( '' === $extension ) {
			return $prefix . '_' . $random;
		}

		return $prefix . '_' . $random . '.' . strtolower( $extension );
	}

	public function delete_file( $file_key ) {
		if ( ! method_exists( $this->storage, 'delete' ) ) {
			$this->log_error(
				WPMCS_Logger::TYPE_DELETE,
				'Delete method not available',
				array(
					'file_key' => $file_key,
				)
			);
			throw new RuntimeException( 'Storage driver does not support delete.' );
		}

		$result = $this->storage->delete( $file_key );

		if ( is_wp_error( $result ) ) {
			$this->log_error(
				WPMCS_Logger::TYPE_DELETE,
				'Delete failed',
				array(
					'file_key' => $file_key,
					'error'    => $result->get_error_message(),
				)
			);
			throw new RuntimeException( $result->get_error_message() );
		}

		$this->log_info(
			WPMCS_Logger::TYPE_DELETE,
			'Delete succeeded',
			array(
				'file_key' => $file_key,
			)
		);

		return true;
	}

	public function file_exists( $file_key ) {
		if ( ! method_exists( $this->storage, 'exists' ) ) {
			return true;
		}

		$result = $this->storage->exists( $file_key );

		if ( is_wp_error( $result ) ) {
			throw new RuntimeException( $result->get_error_message() );
		}

		return (bool) $result;
	}

	protected function resolve_file_path( array $file ) {
		if ( ! empty( $file['tmp_name'] ) && is_string( $file['tmp_name'] ) ) {
			return $file['tmp_name'];
		}

		if ( ! empty( $file['file_path'] ) && is_string( $file['file_path'] ) ) {
			return $file['file_path'];
		}

		if ( ! empty( $file['file'] ) && is_string( $file['file'] ) ) {
			return $file['file'];
		}

		throw new InvalidArgumentException( 'Upload payload is missing tmp_name, file_path or file.' );
	}

	protected function resolve_file_name( array $file ) {
		$original_name = '';

		if ( ! empty( $file['name'] ) && is_string( $file['name'] ) ) {
			$original_name = $file['name'];
		} elseif ( ! empty( $file['file_name'] ) && is_string( $file['file_name'] ) ) {
			$original_name = $file['file_name'];
		}

		if ( '' === $original_name ) {
			throw new InvalidArgumentException( 'Upload payload is missing name or file_name.' );
		}

		$pattern = isset( $this->settings['rename_pattern'] ) ? (string) $this->settings['rename_pattern'] : '';

		return $this->generate_unique_name( $original_name, $pattern );
	}

	/**
	 * @param array<string, mixed> $prepared
	 * @return array<string, mixed>
	 */
	private function attempt_upload( array $prepared ) {
		$attempts = array( $prepared );

		if ( ! empty( $prepared['converted'] ) && ! empty( $prepared['original_path'] ) ) {
			$attempts[] = array(
				'original_path' => (string) $prepared['original_path'],
				'file_path'     => (string) $prepared['original_path'],
				'cloud_key'     => isset( $prepared['original_cloud_key'] ) && '' !== (string) $prepared['original_cloud_key'] ? (string) $prepared['original_cloud_key'] : (string) $prepared['cloud_key'],
				'mime_type'     => isset( $prepared['original_mime_type'] ) && '' !== (string) $prepared['original_mime_type'] ? (string) $prepared['original_mime_type'] : $this->get_mime_type( (string) $prepared['original_path'] ),
				'converted'     => false,
				'temp_file'     => '',
			);
		}

		$last_error = '';

		foreach ( $attempts as $index => $attempt ) {
			try {
				$result = $this->storage->upload(
					$attempt['file_path'],
					$attempt['cloud_key'],
					isset( $attempt['mime_type'] ) ? (string) $attempt['mime_type'] : ''
				);

				if ( is_wp_error( $result ) ) {
					throw new RuntimeException( $result->get_error_message() );
				}
			} catch ( Throwable $e ) {
				$last_error = $e->getMessage();
				$this->log_error(
					WPMCS_Logger::TYPE_UPLOAD,
					'Upload failed',
					array(
						'file_path' => $attempt['file_path'],
						'file_name' => $attempt['cloud_key'],
						'error'     => $last_error,
						'converted' => ! empty( $attempt['converted'] ),
					)
				);

				if ( ! empty( $attempt['converted'] ) && isset( $attempts[ $index + 1 ] ) ) {
					$this->log_info(
						WPMCS_Logger::TYPE_UPLOAD,
						'Retrying upload with original format',
						array(
							'file_path' => $attempt['original_path'],
							'file_name' => $attempt['cloud_key'],
						)
					);
					continue;
				}

				throw new RuntimeException( $last_error );
			}

			$cloud_url = is_array( $result ) && isset( $result['url'] ) ? (string) $result['url'] : (string) $result;
			$cloud_key = is_array( $result ) && isset( $result['key'] ) ? (string) $result['key'] : (string) $attempt['cloud_key'];

			return array(
				'url'       => $cloud_url,
				'key'       => $cloud_key,
				'mime_type' => isset( $attempt['mime_type'] ) ? (string) $attempt['mime_type'] : '',
				'converted' => ! empty( $attempt['converted'] ),
			);
		}

		throw new RuntimeException( $last_error ? $last_error : 'Upload failed.' );
	}

	public function get_storage_info() {
		if ( method_exists( $this->storage, 'get_info' ) ) {
			return $this->storage->get_info();
		}

		return array(
			'provider' => 'unknown',
			'version'  => '1.0.0',
			'features' => array( 'upload', 'delete' ),
		);
	}

	private function get_mime_type( $file_path ) {
		$filetype = wp_check_filetype( $file_path );
		return isset( $filetype['type'] ) ? (string) $filetype['type'] : '';
	}

	private function log_info( $type, $message, array $context = array() ) {
		if ( $this->logger ) {
			$this->logger->info( $type, $message, $context );
		}
	}

	private function log_error( $type, $message, array $context = array() ) {
		if ( $this->logger ) {
			$this->logger->error( $type, $message, $context );
		}
	}
}
