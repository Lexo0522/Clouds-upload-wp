<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/interface-cloud-storage-interface.php';

/**
 * 多吉云存储驱动
 */
class Dogecloud_Storage implements Cloud_Storage_Interface {
	
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
				'domain'     => '',
				'endpoint'   => 'https://api.dogecloud.com',
			)
		);
	}
	
	/**
	 * 上传文件到多吉云
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
		$url = $this->config['endpoint'] . '/object/upload.json';
		
		// 读取文件内容
		$file_content = file_get_contents( $file_path );
		if ( false === $file_content ) {
			throw new RuntimeException( 'Failed to read file content.' );
		}
		
		// 构建签名
		$timestamp = time();
		$nonce = wp_generate_password( 16, false, false );
		
		// 计算签名
		$sign_str = $timestamp . $nonce . $this->config['secret_key'];
		$signature = hash_hmac( 'sha256', $sign_str, $this->config['secret_key'] );
		
		// 构建请求参数
		$post_data = array(
			'bucket' => $this->config['bucket'],
			'key'    => $file_name,
			'file'   => new CURLFile( $file_path ),
		);
		
		// 构建请求头
		$headers = array(
			'X-Dogecloud-Timestamp: ' . $timestamp,
			'X-Dogecloud-Nonce: ' . $nonce,
			'X-Dogecloud-Signature: ' . $signature,
			'X-Dogecloud-AccessKey: ' . $this->config['access_key'],
		);
		
		// 发送请求
		$ch = curl_init( $url );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $post_data,
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
			throw new RuntimeException( $error ? $error : 'Dogecloud upload failed.' );
		}
		
		// 解析响应
		$result = json_decode( $response, true );
		
		if ( ! is_array( $result ) ) {
			throw new RuntimeException( 'Invalid response from Dogecloud API.' );
		}
		
		if ( $http_code < 200 || $http_code >= 300 ) {
			$msg = isset( $result['message'] ) ? $result['message'] : "HTTP Code: {$http_code}";
			throw new RuntimeException( "Dogecloud upload failed: {$msg}" );
		}
		
		return $this->get_url( $file_name );
	}
	
	/**
	 * 删除多吉云文件
	 */
	public function delete( $file_path ) {
		$this->assert_configured();
		
		if ( ! function_exists( 'curl_init' ) ) {
			throw new RuntimeException( 'cURL extension is required.' );
		}
		
		// 构建请求 URL
		$url = $this->config['endpoint'] . '/object/delete.json';
		
		// 构建签名
		$timestamp = time();
		$nonce = wp_generate_password( 16, false, false );
		
		$sign_str = $timestamp . $nonce . $this->config['secret_key'];
		$signature = hash_hmac( 'sha256', $sign_str, $this->config['secret_key'] );
		
		// 构建请求参数
		$post_data = http_build_query( array(
			'bucket' => $this->config['bucket'],
			'key'    => $file_path,
		) );
		
		// 构建请求头
		$headers = array(
			'X-Dogecloud-Timestamp: ' . $timestamp,
			'X-Dogecloud-Nonce: ' . $nonce,
			'X-Dogecloud-Signature: ' . $signature,
			'X-Dogecloud-AccessKey: ' . $this->config['access_key'],
			'Content-Type: application/x-www-form-urlencoded',
		);
		
		// 发送请求
		$ch = curl_init( $url );
		curl_setopt_array(
			$ch,
			array(
				CURLOPT_POST           => true,
				CURLOPT_POSTFIELDS     => $post_data,
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
			throw new RuntimeException( $error ? $error : 'Dogecloud delete failed.' );
		}
		
		$result = json_decode( $response, true );
		
		if ( $http_code < 200 || $http_code >= 300 ) {
			$msg = isset( $result['message'] ) ? $result['message'] : "HTTP Code: {$http_code}";
			throw new RuntimeException( "Dogecloud delete failed: {$msg}" );
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
			$domain = "https://{$bucket}.cdn.dogecloud.com";
		}
		
		return trailingslashit( $domain ) . ltrim( str_replace( '\\', '/', $file_path ), '/' );
	}
	
	/**
	 * 检查配置
	 */
	protected function assert_configured() {
		$required = array( 'access_key', 'secret_key', 'bucket' );
		foreach ( $required as $key ) {
			if ( empty( $this->config[ $key ] ) ) {
				throw new RuntimeException( 'Dogecloud configuration is incomplete.' );
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
