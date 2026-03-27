<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/interface-cloud-storage-interface.php';

/**
 * 又拍云存储驱动
 */
class Upyun_Storage implements Cloud_Storage_Interface {
	
	/**
	 * @var array
	 */
	protected $config = array();
	
	public function __construct( array $config = array() ) {
		$this->config = wp_parse_args(
			$config,
			array(
				'username'   => '',
				'password'   => '',
				'bucket'     => '',
				'domain'     => '',
				'endpoint'   => 'v0.api.upyun.com',
			)
		);
	}
	
	/**
	 * 上传文件到又拍云
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
		$method = 'PUT';
		$uri = '/' . $this->config['bucket'] . '/' . $file_name;
		$date = gmdate( 'D, d M Y H:i:s \G\M\T' );
		$content_length = strlen( $file_content );
		
		// 计算签名
		$sign_str = "{$method}&{$uri}&{$date}&{$content_length}&" . md5( $this->config['password'] );
		$signature = base64_encode( hash_hmac( 'sha1', $sign_str, md5( $this->config['password'] ), true ) );
		
		// 构建请求头
		$headers = array(
			'Authorization: UpYun ' . $this->config['username'] . ':' . $signature,
			'Date: ' . $date,
			'Content-Length: ' . $content_length,
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
			throw new RuntimeException( $error ? $error : 'Upyun upload failed.' );
		}
		
		if ( $http_code < 200 || $http_code >= 300 ) {
			throw new RuntimeException( "Upyun upload failed. HTTP Code: {$http_code}" );
		}
		
		return $this->get_url( $file_name );
	}
	
	/**
	 * 删除又拍云文件
	 */
	public function delete( $file_path ) {
		$this->assert_configured();
		
		if ( ! function_exists( 'curl_init' ) ) {
			throw new RuntimeException( 'cURL extension is required.' );
		}
		
		// 构建请求 URL
		$url = $this->build_request_url( $file_path );
		
		// 构建签名
		$method = 'DELETE';
		$uri = '/' . $this->config['bucket'] . '/' . $file_path;
		$date = gmdate( 'D, d M Y H:i:s \G\M\T' );
		
		$sign_str = "{$method}&{$uri}&{$date}&0&" . md5( $this->config['password'] );
		$signature = base64_encode( hash_hmac( 'sha1', $sign_str, md5( $this->config['password'] ), true ) );
		
		// 构建请求头
		$headers = array(
			'Authorization: UpYun ' . $this->config['username'] . ':' . $signature,
			'Date: ' . $date,
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
			throw new RuntimeException( $error ? $error : 'Upyun delete failed.' );
		}
		
		if ( $http_code < 200 || $http_code >= 300 ) {
			throw new RuntimeException( "Upyun delete failed. HTTP Code: {$http_code}" );
		}
		
		return true;
	}
	
	/**
	 * 获取文件访问 URL
	 */
	public function get_url( $file_path ) {
		$file_path = (string) $file_path;
		$domain = $this->normalize_domain( $this->config['domain'] );
		
		if ( '' === $domain ) {
			// 使用默认域名
			$bucket = $this->config['bucket'];
			$domain = "https://{$bucket}.test.upcdn.net";
		}
		
		return trailingslashit( $domain ) . ltrim( str_replace( '\\', '/', $file_path ), '/' );
	}
	
	/**
	 * 构建请求 URL
	 */
	private function build_request_url( $file_name ) {
		$bucket = $this->config['bucket'];
		$endpoint = $this->config['endpoint'];
		
		return "https://{$endpoint}/{$bucket}/{$file_name}";
	}
	
	/**
	 * 检查配置
	 */
	protected function assert_configured() {
		$required = array( 'username', 'password', 'bucket' );
		foreach ( $required as $key ) {
			if ( empty( $this->config[ $key ] ) ) {
				throw new RuntimeException( 'Upyun configuration is incomplete.' );
			}
		}
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
