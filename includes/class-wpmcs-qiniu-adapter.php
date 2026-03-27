<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPMCS_Qiniu_Adapter extends WPMCS_Cloud_Adapter {
	public function get_provider_key() {
		return 'qiniu';
	}

	public function is_configured() {
		$required = array( 'access_key', 'secret_key', 'bucket', 'domain' );

		foreach ( $required as $field ) {
			if ( empty( $this->settings[ $field ] ) ) {
				return false;
			}
		}

		return true;
	}

	public function upload( $local_path, $cloud_key, $mime_type = '' ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'wpmcs_qiniu_not_configured', '七牛云配置不完整。' );
		}

		if ( ! file_exists( $local_path ) || ! is_readable( $local_path ) ) {
			return new WP_Error( 'wpmcs_file_missing', '待上传文件不存在或不可读。' );
		}

		if ( ! function_exists( 'curl_init' ) || ! function_exists( 'curl_file_create' ) ) {
			return new WP_Error( 'wpmcs_curl_missing', '当前 PHP 环境未启用 cURL 扩展。' );
		}

		$mime_type = $mime_type ? $mime_type : 'application/octet-stream';
		$token     = $this->build_upload_token( $cloud_key );
		$endpoint  = ! empty( $this->settings['upload_endpoint'] ) ? (string) $this->settings['upload_endpoint'] : 'https://upload.qiniup.com';
		$file      = curl_file_create( $local_path, $mime_type, wp_basename( $cloud_key ) );

		$ch = curl_init( $endpoint );

		curl_setopt(
			$ch,
			CURLOPT_POSTFIELDS,
			array(
				'token' => $token,
				'key'   => $cloud_key,
				'file'  => $file,
			)
		);
		curl_setopt( $ch, CURLOPT_POST, true );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $ch, CURLOPT_HEADER, false );
		curl_setopt( $ch, CURLOPT_TIMEOUT, 30 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, true );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Expect:' ) );

		$response = curl_exec( $ch );
		$status   = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$error    = curl_error( $ch );

		curl_close( $ch );

		if ( false === $response ) {
			return new WP_Error( 'wpmcs_qiniu_transport_error', $error ? $error : '七牛上传失败。' );
		}

		$decoded = json_decode( $response, true );

		if ( $status < 200 || $status >= 300 ) {
			$message = '七牛上传失败。';

			if ( is_array( $decoded ) && ! empty( $decoded['error'] ) ) {
				$message = (string) $decoded['error'];
			}

			return new WP_Error( 'wpmcs_qiniu_upload_failed', $message, $decoded );
		}

		return array(
			'provider' => $this->get_provider_key(),
			'key'      => $cloud_key,
			'url'      => $this->get_file_url( $cloud_key ),
			'response' => is_array( $decoded ) ? $decoded : array(),
		);
	}

	private function build_upload_token( $cloud_key ) {
		$policy = wp_json_encode(
			array(
				'scope'    => $this->settings['bucket'] . ':' . $cloud_key,
				'deadline' => time() + HOUR_IN_SECONDS,
			)
		);

		$encoded_policy = $this->urlsafe_base64_encode( $policy );
		$sign           = hash_hmac( 'sha1', $encoded_policy, (string) $this->settings['secret_key'], true );
		$encoded_sign   = $this->urlsafe_base64_encode( $sign );

		return $this->settings['access_key'] . ':' . $encoded_sign . ':' . $encoded_policy;
	}

	private function urlsafe_base64_encode( $data ) {
		return str_replace( array( '+', '/' ), array( '-', '_' ), base64_encode( $data ) );
	}
}
