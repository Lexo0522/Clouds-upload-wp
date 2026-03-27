<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

require_once __DIR__ . '/interface-cloud-storage-interface.php';

/**
 * 阿里云 OSS 存储驱动
 */
class Aliyun_OSS_Storage implements Cloud_Storage_Interface {
	
	/**
	 * @var array
	 */
	protected $config = array();
	
	public function __construct( array $config = array() ) {
		$this->config = wp_parse_args(
			$config,
			array(
				'access_key'      => '',
				'secret_key'      => '',
				'bucket'          => '',
				'endpoint'        => 'oss-cn-hangzhou.aliyuncs.com',
				'domain'          => '',
			)
		);
	}
	
	/**
	 * 上传文件到阿里云 OSS
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
		$date = gmdate( 'D, d M Y H:i:s \G\M\T' );
		$mime_type = $this->guess_mime_type( $file_path );
		
		// 计算签名
		$string_to_sign = "PUT\n\n{$mime_type}\n{$date}\n/{$this->config['bucket']}/{$file_name}";
		$signature = base64_encode( hash_hmac( 'sha1', $string_to_sign, $this->config['secret_key'], true ) );
		
		// 构建请求头
		$headers = array(
			'Date: ' . $date,
			'Content-Type: ' . $mime_type,
			'Authorization: OSS ' . $this->config['access_key'] . ':' . $signature,
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
			throw new RuntimeException( $error ? $error : 'Aliyun OSS upload failed.' );
		}
		
		if ( $http_code < 200 || $http_code >= 300 ) {
			throw new RuntimeException( "Aliyun OSS upload failed. HTTP Code: {$http_code}" );
		}
		
		return $this->get_url( $file_name );
	}
	
	/**
	 * 删除阿里云 OSS 文件
	 */
	public function delete( $file_path ) {
		$this->assert_configured();
		
		if ( ! function_exists( 'curl_init' ) ) {
			throw new RuntimeException( 'cURL extension is required.' );
		}
		
		// 构建请求 URL
		$url = $this->build_request_url( $file_path );
		
		// 构建签名
		$date = gmdate( 'D, d M Y H:i:s \G\M\T' );
		$string_to_sign = "DELETE\n\n\n{$date}\n/{$this->config['bucket']}/{$file_path}";
		$signature = base64_encode( hash_hmac( 'sha1', $string_to_sign, $this->config['secret_key'], true ) );
		
		// 构建请求头
		$headers = array(
			'Date: ' . $date,
			'Authorization: OSS ' . $this->config['access_key'] . ':' . $signature,
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
			throw new RuntimeException( $error ? $error : 'Aliyun OSS delete failed.' );
		}
		
		if ( $http_code < 200 || $http_code >= 300 ) {
			throw new RuntimeException( "Aliyun OSS delete failed. HTTP Code: {$http_code}" );
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
		$endpoint = $this->config['endpoint'];
		
		return "https://{$bucket}.{$endpoint}/" . ltrim( str_replace( '\\', '/', $file_path ), '/' );
	}
	
	/**
	 * 构建请求 URL
	 */
	private function build_request_url( $file_name ) {
		$file_name = (string) $file_name;
		$bucket = $this->config['bucket'];
		$endpoint = $this->config['endpoint'];
		
		return "https://{$bucket}.{$endpoint}/{$file_name}";
	}
	
	/**
	 * 检查配置
	 */
	protected function assert_configured() {
		$required = array( 'access_key', 'secret_key', 'bucket', 'endpoint' );
		foreach ( $required as $key ) {
			if ( empty( $this->config[ $key ] ) ) {
				throw new RuntimeException( 'Aliyun OSS configuration is incomplete.' );
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
