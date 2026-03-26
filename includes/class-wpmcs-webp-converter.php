<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPMCS_WebP_Converter {
	public static function prepare_upload( $file_path, $cloud_key, array $settings = array() ) {
		$file_path = (string) $file_path;
		$cloud_key = (string) $cloud_key;

		$prepared = array(
			'original_path' => $file_path,
			'file_path'     => $file_path,
			'original_cloud_key' => '' !== trim( $cloud_key ) ? $cloud_key : wp_basename( $file_path ),
			'cloud_key'     => '' !== trim( $cloud_key ) ? $cloud_key : wp_basename( $file_path ),
			'mime_type'     => '',
			'original_mime_type' => '',
			'converted'     => false,
			'temp_file'     => '',
		);

		$filetype = wp_check_filetype( $file_path );
		$prepared['mime_type'] = isset( $filetype['type'] ) ? (string) $filetype['type'] : '';
		$prepared['original_mime_type'] = $prepared['mime_type'];

		if ( ! self::should_convert( $file_path, $settings, $prepared['mime_type'] ) ) {
			return $prepared;
		}

		try {
			$converted = self::convert_to_webp( $file_path, $settings );
		} catch ( Throwable $e ) {
			return $prepared;
		}

		if ( ! $converted ) {
			return $prepared;
		}

		$prepared['file_path'] = $converted;
		$prepared['cloud_key'] = self::replace_extension( $prepared['cloud_key'], 'webp' );
		$prepared['mime_type'] = 'image/webp';
		$prepared['converted'] = true;
		$prepared['temp_file'] = $converted;

		return $prepared;
	}

	public static function cleanup( array $prepared ) {
		if ( empty( $prepared['temp_file'] ) ) {
			return;
		}

		$temp_file = (string) $prepared['temp_file'];
		if ( file_exists( $temp_file ) ) {
			@unlink( $temp_file );
		}
	}

	private static function should_convert( $file_path, array $settings, $mime_type = '' ) {
		if ( empty( $settings['convert_to_webp'] ) ) {
			return false;
		}

		if ( empty( $file_path ) || ! file_exists( $file_path ) || ! is_file( $file_path ) ) {
			return false;
		}

		if ( '' === $mime_type ) {
			$filetype = wp_check_filetype( $file_path );
			$mime_type = isset( $filetype['type'] ) ? (string) $filetype['type'] : '';
		}

		$mime_type = strtolower( trim( $mime_type ) );
		if ( '' === $mime_type || 'image/webp' === $mime_type ) {
			return false;
		}

		$allowed = apply_filters( 'wpmcs_webp_convertible_mime_types', array( 'image/jpeg', 'image/png' ), $file_path, $settings );
		$allowed = array_values( array_filter( array_map( 'strval', (array) $allowed ) ) );

		return in_array( $mime_type, $allowed, true );
	}

	private static function convert_to_webp( $file_path, array $settings = array() ) {
		if ( ! function_exists( 'wp_get_image_editor' ) ) {
			return '';
		}

		$editor = wp_get_image_editor( $file_path );
		if ( is_wp_error( $editor ) ) {
			return '';
		}

		$quality = self::get_quality( $file_path, $settings );

		if ( method_exists( $editor, 'set_quality' ) ) {
			$editor->set_quality( $quality );
		}

		$temp_file = self::create_temp_file( $file_path );
		if ( ! $temp_file ) {
			return '';
		}

		$saved = $editor->save( $temp_file, 'image/webp' );
		if ( is_wp_error( $saved ) ) {
			self::cleanup( array( 'temp_file' => $temp_file ) );
			return '';
		}

		$saved_path = $temp_file;
		if ( is_array( $saved ) && ! empty( $saved['path'] ) ) {
			$saved_path = (string) $saved['path'];
		}

		if ( is_array( $saved ) && isset( $saved['mime-type'] ) && 'image/webp' !== strtolower( (string) $saved['mime-type'] ) ) {
			self::cleanup( array( 'temp_file' => $temp_file ) );
			return '';
		}

		if ( ! file_exists( $saved_path ) ) {
			self::cleanup( array( 'temp_file' => $temp_file ) );
			return '';
		}

		return $saved_path;
	}

	private static function create_temp_file( $source_path ) {
		$temp_file = '';

		if ( class_exists( 'WPMCS_Temp_File_Manager' ) ) {
			$temp_file = WPMCS_Temp_File_Manager::create_temp_file( '', 'wpmcs-webp-', 'webp' );
		}

		if ( ! empty( $temp_file ) ) {
			return $temp_file;
		}

		if ( ! function_exists( 'wp_tempnam' ) ) {
			return '';
		}

		$temp_file = wp_tempnam( wp_basename( $source_path ) );
		if ( ! $temp_file ) {
			return '';
		}

		$webp_file = $temp_file . '.webp';
		@unlink( $temp_file );

		if ( false === file_put_contents( $webp_file, '' ) ) {
			return '';
		}

		return $webp_file;
	}

	private static function replace_extension( $path, $new_extension ) {
		$path = str_replace( '\\', '/', (string) $path );
		$new_extension = ltrim( (string) $new_extension, '.' );

		if ( '' === $path ) {
			return '';
		}

		$directory = pathinfo( $path, PATHINFO_DIRNAME );
		$filename  = pathinfo( $path, PATHINFO_FILENAME );
		$basename  = $filename . '.' . $new_extension;

		if ( '' === $directory || '.' === $directory ) {
			return $basename;
		}

		return trim( $directory, '/' ) . '/' . $basename;
	}

	private static function get_quality( $file_path, array $settings ) {
		$default_quality = 82;
		$quality = isset( $settings['webp_quality'] ) ? (int) $settings['webp_quality'] : $default_quality;

		if ( $quality < 1 || $quality > 100 ) {
			$quality = $default_quality;
		}

		$quality = (int) apply_filters( 'wpmcs_webp_quality', $quality, $file_path, $settings );

		return max( 1, min( 100, $quality ) );
	}
}
