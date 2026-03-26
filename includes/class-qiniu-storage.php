<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/interface-cloud-storage-interface.php';

class Qiniu_Storage implements Cloud_Storage_Interface {
	/**
	 * @var array<string, mixed>
	 */
	protected $config = array();

	public function __construct( array $config = array() ) {
		$this->config = wp_parse_args(
			$config,
			array(
				'access_key'      => '',
				'secret_key'      => '',
				'bucket'          => '',
				'domain'          => '',
				'upload_endpoint' => 'https://upload.qiniup.com',
				'delete_endpoint' => 'https://rs.qiniu.com',
			)
		);
	}

	public function upload( $file_path, $file_name ) {
		$this->assert_configured();

		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			throw new RuntimeException( 'Local file does not exist or is not readable.' );
		}

		if ( ! function_exists( 'curl_init' ) || ! function_exists( 'curl_file_create' ) ) {
			throw new RuntimeException( 'cURL extension is required.' );
		}

		$token = $this->build_upload_token( $file_name );
		$mime  = $this->guess_mime_type( $file_path );
		$file  = curl_file_create( $file_path, $mime, wp_basename( $file_name ) );
		$url   = ! empty( $this->config['upload_endpoint'] ) ? (string) $this->config['upload_endpoint'] : 'https://upload.qiniup.com';

		$ch = curl_init( $url );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_POST           => true,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER         => false,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_HTTPHEADER      => array( 'Expect:' ),
				CURLOPT_POSTFIELDS     => array(
					'token' => $token,
					'key'   => $file_name,
					'file'  => $file,
				),
			)
		);

		$response = curl_exec( $ch );
		$http_code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$error     = curl_error( $ch );
		curl_close( $ch );

		if ( false === $response ) {
			throw new RuntimeException( $error ? $error : 'Qiniu upload failed.' );
		}

		$decoded = json_decode( $response, true );
		if ( $http_code < 200 || $http_code >= 300 ) {
			$message = 'Qiniu upload failed.';
			if ( is_array( $decoded ) && ! empty( $decoded['error'] ) ) {
				$message = (string) $decoded['error'];
			}
			throw new RuntimeException( $message );
		}

		return $this->get_url( $file_name );
	}

	public function delete( $file_path ) {
		$this->assert_configured();

		if ( ! function_exists( 'curl_init' ) ) {
			throw new RuntimeException( 'cURL extension is required.' );
		}

		$entry = $this->config['bucket'] . ':' . ltrim( $file_path, '/' );
		$encoded_entry = $this->urlsafe_base64_encode( $entry );
		$url = rtrim( (string) $this->config['delete_endpoint'], '/' ) . '/delete/' . $encoded_entry;
		$auth = $this->build_authorization( 'POST', '/delete/' . $encoded_entry, '', 'application/x-www-form-urlencoded' );

		$ch = curl_init( $url );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => '',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER         => false,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_HTTPHEADER      => array(
					'Authorization: QBox ' . $auth,
				),
			)
		);

		$response = curl_exec( $ch );
		$http_code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$error     = curl_error( $ch );
		curl_close( $ch );

		if ( false === $response ) {
			throw new RuntimeException( $error ? $error : 'Qiniu delete failed.' );
		}

		if ( $http_code < 200 || $http_code >= 300 ) {
			throw new RuntimeException( 'Qiniu delete failed.' );
		}

		return true;
	}

	public function get_url( $file_path ) {
		$file_path = (string) $file_path;
		$domain = $this->normalize_domain( (string) $this->config['domain'] );

		if ( '' === $domain ) {
			return '';
		}

		return trailingslashit( $domain ) . ltrim( str_replace( '\\', '/', $file_path ), '/' );
	}

	protected function assert_configured() {
		$required = array( 'access_key', 'secret_key', 'bucket', 'domain' );
		foreach ( $required as $key ) {
			if ( empty( $this->config[ $key ] ) ) {
				throw new RuntimeException( 'Qiniu storage configuration is incomplete.' );
			}
		}
	}

	protected function build_upload_token( $file_name ) {
		$file_name = (string) $file_name;
		$policy = wp_json_encode(
			array(
				'scope'    => $this->config['bucket'] . ':' . $file_name,
				'deadline' => time() + HOUR_IN_SECONDS,
			)
		);

		$encoded_policy = $this->urlsafe_base64_encode( $policy );
		$sign = hash_hmac( 'sha1', $encoded_policy, (string) $this->config['secret_key'], true );
		$encoded_sign = $this->urlsafe_base64_encode( $sign );

		return $this->config['access_key'] . ':' . $encoded_sign . ':' . $encoded_policy;
	}

	protected function build_authorization( $method, $path, $query = '', $content_type = '' ) {
		$sign_text = sprintf( "%s %s\n%s\n%s\n", (string) $method, (string) $path, (string) $query, (string) $content_type );
		$sign = hash_hmac( 'sha1', $sign_text, (string) $this->config['secret_key'], true );
		return $this->config['access_key'] . ':' . $this->urlsafe_base64_encode( $sign );
	}

	protected function urlsafe_base64_encode( $data ) {
		return str_replace( array( '+', '/' ), array( '-', '_' ), base64_encode( $data ) );
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

	protected function guess_mime_type( $file_path ) {
		$filetype = wp_check_filetype( $file_path );
		if ( ! empty( $filetype['type'] ) ) {
			return (string) $filetype['type'];
		}

		return 'application/octet-stream';
	}
}
