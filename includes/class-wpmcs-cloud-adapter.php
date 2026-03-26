<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

abstract class WPMCS_Cloud_Adapter {
	/**
	 * @var array<string, mixed>
	 */
	protected $settings = array();

	public function __construct( array $settings = array() ) {
		$this->settings = $settings;
	}

	/**
	 * @return array<string, mixed>
	 */
	public function get_settings() {
		return $this->settings;
	}

	public function is_enabled() {
		return ! empty( $this->settings['enabled'] );
	}

	abstract public function get_provider_key();

	abstract public function is_configured();

	/**
	 * @param string $local_path Full local file path.
	 * @param string $cloud_key  Target object key on cloud storage.
	 * @param string $mime_type  File mime type.
	 * @return array<string, mixed>|WP_Error
	 */
	abstract public function upload( $local_path, $cloud_key, $mime_type = '' );

	public function get_file_url( $cloud_key ) {
		$cloud_key = (string) $cloud_key;
		$domain = $this->normalize_domain( isset( $this->settings['domain'] ) ? (string) $this->settings['domain'] : '' );

		if ( '' === $domain ) {
			return '';
		}

		return trailingslashit( $domain ) . ltrim( str_replace( '\\', '/', $cloud_key ), '/' );
	}

	protected function normalize_domain( $domain ) {
		$domain = trim( (string) $domain );

		if ( '' === $domain ) {
			return '';
		}

		if ( 0 !== strpos( $domain, 'http://' ) && 0 !== strpos( $domain, 'https://' ) ) {
			$domain = 'https://' . $domain;
		}

		return untrailingslashit( $domain );
	}
}
