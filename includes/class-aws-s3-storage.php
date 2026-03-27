<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/interface-cloud-storage-interface.php';

/**
 * AWS S3 存储驱动
 */
class AWS_S3_Storage implements Cloud_Storage_Interface {
	
	/**
	 * @var array
	 */
	protected $config = array();
	
	public function __construct( array $config = array() ) {
		$this->config = wp_parse_args(
			$config,
			array(
				'access_key' => '',
				'secret_key' => '',
				'bucket'     => '',
				'region'     => 'us-east-1',
				'domain'     => '',
			)
		);
	}
	
	/**
	 * 上传文件到 AWS S3
	 */
	public function upload( $file_path, $file_name ) {
		$this->assert_configured();
		
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			throw new RuntimeException( 'Local file does not exist or is not readable.' );
		}
		
		if ( ! function_exists( 'curl_init' ) ) {
			throw new RuntimeException( 'cURL extension is required.' );
		}
		
		// 读取文件内容
		$file_content = file_get_contents( $file_path );
		if ( false === $file_content ) {
			throw new RuntimeException( 'Failed to read file content.' );
		}
		
		// 构建请求
		$mime_type = $this->guess_mime_type( $file_path );
		$url = $this->build_request_url( $file_name );
		
		// 生成 AWS Signature Version 4
		$timestamp = gmdate( 'Ymd\THis\Z' );
		$date = gmdate( 'Ymd' );
		
		// 构建规范请求
		$canonical_uri = '/' . str_replace( '%2F', '/', rawurlencode( $file_name ) );
		$canonical_query_string = '';
		$canonical_headers = "host:{$this->get_host()}\n";
		$canonical_headers .= "x-amz-content-sha256:" . hash( 'sha256', $file_content ) . "\n";
		$canonical_headers .= "x-amz-date:{$timestamp}\n";
		
		$signed_headers = 'host;x-amz-content-sha256;x-amz-date';
		
		$canonical_request = "PUT\n";
		$canonical_request .= "{$canonical_uri}\n";
		$canonical_request .= "{$canonical_query_string}\n";
		$canonical_request .= "{$canonical_headers}\n";
		$canonical_request .= "{$signed_headers}\n";
		$canonical_request .= hash( 'sha256', $file_content );
		
		// 构建待签名字符串
		$algorithm = 'AWS4-HMAC-SHA256';
		$credential_scope = "{$date}/{$this->config['region']}/s3/aws4_request";
		$string_to_sign = "{$algorithm}\n";
		$string_to_sign .= "{$timestamp}\n";
		$string_to_sign .= "{$credential_scope}\n";
		$string_to_sign .= hash( 'sha256', $canonical_request );
		
		// 计算签名
		$k_secret = 'AWS4' . $this->config['secret_key'];
		$k_date = hash_hmac( 'sha256', $date, $k_secret );
		$k_region = hash_hmac( 'sha256', $this->config['region'], $k_date );
		$k_service = hash_hmac( 'sha256', 's3', $k_region );
		$k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service );
		$signature = hash_hmac( 'sha256', $string_to_sign, $k_signing );
		
		// 构建授权头
		$authorization = "{$algorithm} Credential={$this->config['access_key']}/{$credential_scope}, SignedHeaders={$signed_headers}, Signature={$signature}";
		
		// 构建请求头
		$headers = array(
			'Host: ' . $this->get_host(),
			'Content-Type: ' . $mime_type,
			'Content-Length: ' . strlen( $file_content ),
			'x-amz-date: ' . $timestamp,
			'x-amz-content-sha256: ' . hash( 'sha256', $file_content ),
			'Authorization: ' . $authorization,
		);
		
		// 发送请求
		$ch = curl_init( $url );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_CUSTOMREQUEST  => 'PUT',
				CURLOPT_POSTFIELDS     => $file_content,
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER         => false,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_HTTPHEADER     => $headers,
			)
		);
		
		$response = curl_exec( $ch );
		$http_code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$error = curl_error( $ch );
		curl_close( $ch );
		
		if ( false === $response ) {
			throw new RuntimeException( $error ? $error : 'AWS S3 upload failed.' );
		}
		
		if ( $http_code < 200 || $http_code >= 300 ) {
			throw new RuntimeException( "AWS S3 upload failed. HTTP Code: {$http_code}" );
		}
		
		return $this->get_url( $file_name );
	}
	
	/**
	 * 删除 AWS S3 文件
	 */
	public function delete( $file_path ) {
		$this->assert_configured();
		
		if ( ! function_exists( 'curl_init' ) ) {
			throw new RuntimeException( 'cURL extension is required.' );
		}
		
		// 构建请求
		$url = $this->build_request_url( $file_path );
		
		// 生成 AWS Signature Version 4
		$timestamp = gmdate( 'Ymd\THis\Z' );
		$date = gmdate( 'Ymd' );
		
		// 构建规范请求
		$canonical_uri = '/' . str_replace( '%2F', '/', rawurlencode( $file_path ) );
		$canonical_query_string = '';
		$canonical_headers = "host:{$this->get_host()}\n";
		$canonical_headers .= "x-amz-content-sha256:" . hash( 'sha256', '' ) . "\n";
		$canonical_headers .= "x-amz-date:{$timestamp}\n";
		
		$signed_headers = 'host;x-amz-content-sha256;x-amz-date';
		
		$canonical_request = "DELETE\n";
		$canonical_request .= "{$canonical_uri}\n";
		$canonical_request .= "{$canonical_query_string}\n";
		$canonical_request .= "{$canonical_headers}\n";
		$canonical_request .= "{$signed_headers}\n";
		$canonical_request .= hash( 'sha256', '' );
		
		// 构建待签名字符串
		$algorithm = 'AWS4-HMAC-SHA256';
		$credential_scope = "{$date}/{$this->config['region']}/s3/aws4_request";
		$string_to_sign = "{$algorithm}\n";
		$string_to_sign .= "{$timestamp}\n";
		$string_to_sign .= "{$credential_scope}\n";
		$string_to_sign .= hash( 'sha256', $canonical_request );
		
		// 计算签名
		$k_secret = 'AWS4' . $this->config['secret_key'];
		$k_date = hash_hmac( 'sha256', $date, $k_secret );
		$k_region = hash_hmac( 'sha256', $this->config['region'], $k_date );
		$k_service = hash_hmac( 'sha256', 's3', $k_region );
		$k_signing = hash_hmac( 'sha256', 'aws4_request', $k_service );
		$signature = hash_hmac( 'sha256', $string_to_sign, $k_signing );
		
		// 构建授权头
		$authorization = "{$algorithm} Credential={$this->config['access_key']}/{$credential_scope}, SignedHeaders={$signed_headers}, Signature={$signature}";
		
		// 构建请求头
		$headers = array(
			'Host: ' . $this->get_host(),
			'x-amz-date: ' . $timestamp,
			'x-amz-content-sha256: ' . hash( 'sha256', '' ),
			'Authorization: ' . $authorization,
		);
		
		// 发送请求
		$ch = curl_init( $url );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_CUSTOMREQUEST  => 'DELETE',
				CURLOPT_RETURNTRANSFER => true,
				CURLOPT_HEADER         => false,
				CURLOPT_TIMEOUT        => 30,
				CURLOPT_SSL_VERIFYPEER => true,
				CURLOPT_HTTPHEADER     => $headers,
			)
		);
		
		$response = curl_exec( $ch );
		$http_code = (int) curl_getinfo( $ch, CURLINFO_HTTP_CODE );
		$error = curl_error( $ch );
		curl_close( $ch );
		
		if ( false === $response ) {
			throw new RuntimeException( $error ? $error : 'AWS S3 delete failed.' );
		}
		
		if ( $http_code < 200 || $http_code >= 300 ) {
			throw new RuntimeException( "AWS S3 delete failed. HTTP Code: {$http_code}" );
		}
		
		return true;
	}
	
	/**
	 * 获取文件访问 URL
	 */
	public function get_url( $file_path ) {
		$file_path = (string) $file_path;
		// 如果有自定义域名，使用自定义域名
		if ( ! empty( $this->config['domain'] ) ) {
			$domain = $this->normalize_domain( $this->config['domain'] );
			return trailingslashit( $domain ) . ltrim( str_replace( '\\', '/', $file_path ), '/' );
		}
		
		// 否则使用默认域名
		$bucket = $this->config['bucket'];
		$region = $this->config['region'];
		
		// 根据区域选择域名格式
		if ( $region === 'us-east-1' ) {
			return "https://{$bucket}.s3.amazonaws.com/" . ltrim( str_replace( '\\', '/', $file_path ), '/' );
		} else {
			return "https://{$bucket}.s3.{$region}.amazonaws.com/" . ltrim( str_replace( '\\', '/', $file_path ), '/' );
		}
	}
	
	/**
	 * 构建请求 URL
	 */
	private function build_request_url( $file_name ) {
		return 'https://' . $this->get_host() . '/' . str_replace( '%2F', '/', rawurlencode( (string) $file_name ) );
	}
	
	/**
	 * 获取 Host
	 */
	private function get_host() {
		$bucket = $this->config['bucket'];
		$region = $this->config['region'];
		
		if ( $region === 'us-east-1' ) {
			return "{$bucket}.s3.amazonaws.com";
		} else {
			return "{$bucket}.s3.{$region}.amazonaws.com";
		}
	}
	
	/**
	 * 检查配置
	 */
	protected function assert_configured() {
		$required = array( 'access_key', 'secret_key', 'bucket', 'region' );
		foreach ( $required as $key ) {
			if ( empty( $this->config[ $key ] ) ) {
				throw new RuntimeException( 'AWS S3 configuration is incomplete.' );
			}
		}
	}
	
	/**
	 * 猜测 MIME 类型
	 */
	protected function guess_mime_type( $file_path ) {
		$filetype = wp_check_filetype( $file_path );
		if ( ! empty( $filetype['type'] ) ) {
			return (string) $filetype['type'];
		}
		return 'application/octet-stream';
	}
	
	/**
	 * 标准化域名
	 */
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
