<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 又拍云适配器
 */
class WPMCS_Upyun_Adapter extends WPMCS_Cloud_Adapter {
	
	/**
	 * 获取服务商标识
	 */
	public function get_provider_key() {
		return 'upyun';
	}
	
	/**
	 * 检查配置是否完整
	 */
	public function is_configured() {
		$required = array( 'username', 'password', 'bucket' );
		
		foreach ( $required as $field ) {
			if ( empty( $this->settings[ $field ] ) ) {
				return false;
			}
		}
		
		return true;
	}
	
	/**
	 * 上传文件
	 */
	public function upload( $local_path, $cloud_key, $mime_type = '' ) {
		if ( ! $this->is_configured() ) {
			return new WP_Error( 'wpmcs_upyun_not_configured', '又拍云配置不完整。' );
		}
		
		if ( ! file_exists( $local_path ) || ! is_readable( $local_path ) ) {
			return new WP_Error( 'wpmcs_file_missing', '待上传文件不存在或不可读。' );
		}
		
		if ( ! function_exists( 'curl_init' ) ) {
			return new WP_Error( 'wpmcs_curl_missing', '当前 PHP 环境未启用 cURL 扩展。' );
		}
		
		// 创建又拍云存储实例
		$storage = new Upyun_Storage( array(
			'username' => $this->settings['username'],
			'password' => $this->settings['password'],
			'bucket'   => $this->settings['bucket'],
			'domain'   => isset( $this->settings['domain'] ) ? $this->settings['domain'] : '',
			'endpoint' => isset( $this->settings['endpoint'] ) ? $this->settings['endpoint'] : 'v0.api.upyun.com',
		) );
		
		try {
			$url = $storage->upload( $local_path, $cloud_key );
			
			return array(
				'provider' => $this->get_provider_key(),
				'key'      => $cloud_key,
				'url'      => $url,
			);
			
		} catch ( Exception $e ) {
			return new WP_Error( 'wpmcs_upyun_upload_failed', $e->getMessage() );
		}
	}
}