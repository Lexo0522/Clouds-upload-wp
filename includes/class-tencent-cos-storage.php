<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/interface-cloud-storage-interface.php';

/**
 * 腾讯云 COS 存储驱动
 */
class Tencent_COS_Storage implements Cloud_Storage_Interface {
	
	/**
	 * @var array
	 */
	protected $config = array();
	
	public function __construct( array $config = array() ) {
		$this->config = wp_parse_args(
			$config,
			array(
				'secret_id'  => '',
				'secret_key' => '',
				'bucket'     => '',
				'region'     => 'ap-beijing',
				'domain'     => '',
			)
		);
	}
	
	/**
	 * 上传文件到腾讯云 COS
	 */
	public function upload( $file_path, $file_name ) {
		$this->assert_configured();
		
		if ( ! file_exists( $file_path ) || ! is_readable( $file_path ) ) {
			throw new RuntimeException( 'Local file does not exist or is not readable.' );
		}
		
		if ( ! function_exists( 'curl_init' ) ) {
			throw new RuntimeException( 'cURL extension is required.' );
		}
		
		// 构建请求 URL
		$url = $this->build_request_url( $file_name );
		
		// 读取文件内容
		$file_content = file_get_contents( $file_path );
		if ( false === $file_content ) {
			throw new RuntimeException( 'Failed to read file content.' );
		}
		
		// 构建签名
		$timestamp = time();
		$expiration = $timestamp + 3600; // 1小时有效期
		$mime_type = $this->guess_mime_type( $file_path );
		
		// 计算签名
		$key_time = "{$timestamp};{$expiration}";
		$sign_key = hash_hmac( 'sha1', $key_time, $this->config['secret_key'] );
		
		// 构建授权字符串
		$http_string = "put\n/{$file_name}\n\nhost={$this->get_host()}\n";
		$string_to_sign = "sha1\n{$key_time}\n" . sha1( $http_string ) . "\n";
		$signature = hash_hmac( 'sha1', $string_to_sign, $sign_key );
		
		// 构建授权头部
		$authorization = sprintf(
			'q-sign-algorithm=sha1&q-ak=%s&q-sign-time=%s&q-key-time=%s&q-header-list=host&q-url-param-list=&q-signature=%s',
			$this->config['secret_id'],
			$key_time,
			$key_time,
			$signature
		);
		
		// 构建请求头
		$headers = array(
			'Host: ' . $this->get_host(),
			'Content-Type: ' . $mime_type,
			'Authorization: ' . $authorization,
			'Content-Length: ' . strlen( $file_content ),
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
			throw new RuntimeException( $error ? $error : 'Tencent COS upload failed.' );
		}
		
		if ( $http_code < 200 || $http_code >= 300 ) {
			throw new RuntimeException( "Tencent COS upload failed. HTTP Code: {$http_code}" );
		}
		
		return $this->get_url( $file_name );
	}
	
	/**
	 * 删除腾讯云 COS 文件
	 */
	public function delete( $file_path ) {
		$this->assert_configured();
		
		if ( ! function_exists( 'curl_init' ) ) {
			throw new RuntimeException( 'cURL extension is required.' );
		}
		
		// 构建请求 URL
		$url = $this->build_request_url( $file_path );
		
		// 构建签名
		$timestamp = time();
		$expiration = $timestamp + 3600;
		
		$key_time = "{$timestamp};{$expiration}";
		$sign_key = hash_hmac( 'sha1', $key_time, $this->config['secret_key'] );
		
		$http_string = "delete\n/{$file_path}\n\nhost={$this->get_host()}\n";
		$string_to_sign = "sha1\n{$key_time}\n" . sha1( $http_string ) . "\n";
		$signature = hash_hmac( 'sha1', $string_to_sign, $sign_key );
		
		$authorization = sprintf(
			'q-sign-algorithm=sha1&q-ak=%s&q-sign-time=%s&q-key-time=%s&q-header-list=host&q-url-param-list=&q-signature=%s',
			$this->config['secret_id'],
			$key_time,
			$key_time,
			$signature
		);
		
		// 构建请求头
		$headers = array(
			'Host: ' . $this->get_host(),
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
			throw new RuntimeException( $error ? $error : 'Tencent COS delete failed.' );
		}
		
		if ( $http_code < 200 || $http_code >= 300 ) {
			throw new RuntimeException( "Tencent COS delete failed. HTTP Code: {$http_code}" );
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
		
		return "https://{$bucket}.cos.{$region}.myqcloud.com/" . ltrim( str_replace( '\\', '/', $file_path ), '/' );
	}
	
	/**
	 * 构建请求 URL
	 */
	private function build_request_url( $file_name ) {
		return 'https://' . $this->get_host() . '/' . (string) $file_name;
	}
	
	/**
	 * 获取 Host
	 */
	private function get_host() {
		$bucket = $this->config['bucket'];
		$region = $this->config['region'];
		
		return "{$bucket}.cos.{$region}.myqcloud.com";
	}
	
	/**
	 * 检查配置
	 */
	protected function assert_configured() {
		$required = array( 'secret_id', 'secret_key', 'bucket', 'region' );
		foreach ( $required as $key ) {
			if ( empty( $this->config[ $key ] ) ) {
				throw new RuntimeException( 'Tencent COS configuration is incomplete.' );
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
