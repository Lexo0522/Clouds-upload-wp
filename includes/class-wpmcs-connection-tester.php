<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPMCS_Connection_Tester {
	/**
	 * @var array
	 */
	private $settings;

	/**
	 * @var Cloud_Storage_Interface|null
	 */
	private $storage;

	public function __construct( array $settings ) {
		$this->settings = $settings;
	}

	/**
	 * 运行完整连接测试。
	 *
	 * @return array 测试结果
	 */
	public function run_full_test() {
		$results = array(
			'success'   => false,
			'message'   => '',
			'details'   => array(),
			'timestamp' => current_time( 'mysql' ),
		);

		if ( function_exists( 'wpmcs_get_logger' ) ) {
			wpmcs_get_logger()->info( WPMCS_Logger::TYPE_SYSTEM, '开始云存储连接测试', array(
				'provider' => isset( $this->settings['provider'] ) ? sanitize_key( (string) $this->settings['provider'] ) : '',
			) );
		}

		$validation = $this->validate_config();
		if ( ! $validation['valid'] ) {
			$results['message'] = '配置验证失败';
			$results['details'] = $validation['errors'];
			if ( function_exists( 'wpmcs_get_logger' ) ) {
				wpmcs_get_logger()->warning( WPMCS_Logger::TYPE_SYSTEM, '连接测试配置校验失败', array(
					'errors' => $validation['errors'],
				) );
			}
			return $results;
		}

		$results['details'][] = '配置验证通过';

		$storage_result = $this->create_storage_driver();
		if ( is_wp_error( $storage_result ) ) {
			$results['message'] = '存储驱动创建失败';
			$results['details'][] = '错误：' . $storage_result->get_error_message();
			return $results;
		}

		$results['details'][] = '存储驱动创建成功';

		if ( function_exists( 'wpmcs_get_logger' ) ) {
			wpmcs_get_logger()->info( WPMCS_Logger::TYPE_SYSTEM, '存储驱动创建成功', array(
				'provider' => isset( $this->settings['provider'] ) ? sanitize_key( (string) $this->settings['provider'] ) : '',
			) );
		}

		$upload_result = $this->test_upload();
		if ( is_wp_error( $upload_result ) ) {
			$results['message'] = '上传测试失败';
			$results['details'][] = '错误：' . $upload_result->get_error_message();
			return $results;
		}

		$results['details'][] = '上传测试成功';
		$results['details'][] = '文件：' . $upload_result['key'];
		$results['details'][] = 'URL：' . $upload_result['url'];

		$access_result = $this->test_file_access( $upload_result['url'] );
		if ( is_wp_error( $access_result ) ) {
			$results['message'] = '文件访问测试失败';
			$results['details'][] = '错误：' . $access_result->get_error_message();
			$this->cleanup_test_file( $upload_result['key'] );
			return $results;
		}

		$results['details'][] = '文件访问测试成功';

		$delete_result = $this->test_delete( $upload_result['key'] );
		if ( is_wp_error( $delete_result ) ) {
			$results['message'] = '删除测试失败，文件已上传但未能删除';
			$results['details'][] = '错误：' . $delete_result->get_error_message();
			$results['details'][] = '请手动删除测试文件：' . $upload_result['key'];
			return $results;
		}

		$results['details'][] = '删除测试成功';
		$results['details'][] = '测试文件已清理';
		$results['success']   = true;
		$results['message']   = '连接测试成功，云存储配置正确。';

		return $results;
	}

	/**
	 * 校验配置。
	 *
	 * @return array
	 */
	private function validate_config() {
		$errors = array();
		$provider = isset( $this->settings['provider'] ) ? sanitize_key( (string) $this->settings['provider'] ) : 'qiniu';

		$required_fields_map = array(
			'qiniu' => array(
				'access_key' => 'Access Key',
				'secret_key' => 'Secret Key',
				'bucket'     => 'Bucket',
				'domain'     => 'Domain',
			),
			'aliyun_oss' => array(
				'access_key' => 'Access Key ID',
				'secret_key' => 'Access Key Secret',
				'bucket'     => 'Bucket',
				'endpoint'   => 'Endpoint',
			),
			'tencent_cos' => array(
				'secret_id'  => 'Secret ID',
				'secret_key' => 'Secret Key',
				'bucket'     => 'Bucket',
				'region'     => 'Region',
			),
			'upyun' => array(
				'username' => 'Username',
				'password' => 'Password',
				'bucket'   => 'Bucket',
				'endpoint' => 'Endpoint',
			),
			'dogecloud' => array(
				'access_key' => 'Access Key',
				'secret_key' => 'Secret Key',
				'bucket'     => 'Bucket',
			),
			'aws_s3' => array(
				'access_key' => 'Access Key ID',
				'secret_key' => 'Secret Access Key',
				'bucket'     => 'Bucket',
				'region'     => 'Region',
			),
		);

		$required_fields = isset( $required_fields_map[ $provider ] ) ? $required_fields_map[ $provider ] : $required_fields_map['qiniu'];

		foreach ( $required_fields as $field => $label ) {
			if ( empty( $this->settings[ $field ] ) ) {
				$errors[] = sprintf( '缺少必要配置：%s', $label );
			}
		}

		if ( ! empty( $this->settings['domain'] ) ) {
			$domain = (string) $this->settings['domain'];
			if ( ! preg_match( '/^https?:\/\//i', $domain ) ) {
				$errors[] = '域名需要以 http:// 或 https:// 开头';
			}
		}

		if ( ! empty( $this->settings['upload_endpoint'] ) ) {
			$endpoint = (string) $this->settings['upload_endpoint'];
			if ( ! preg_match( '/^https?:\/\//i', $endpoint ) ) {
				$errors[] = '上传地址需要以 http:// 或 https:// 开头';
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);

		$required_fields = array(
			'access_key' => 'Access Key',
			'secret_key' => 'Secret Key',
			'bucket'     => 'Bucket',
			'domain'     => '加速域名',
		);

		foreach ( $required_fields as $field => $label ) {
			if ( empty( $this->settings[ $field ] ) ) {
				$errors[] = sprintf( '缺少必要配置：%s', $label );
			}
		}

		if ( ! empty( $this->settings['domain'] ) ) {
			$domain = (string) $this->settings['domain'];
			if ( ! preg_match( '/^https?:\/\//i', $domain ) ) {
				$errors[] = '域名必须以 http:// 或 https:// 开头';
			}
		}

		if ( ! empty( $this->settings['upload_endpoint'] ) ) {
			$endpoint = (string) $this->settings['upload_endpoint'];
			if ( ! preg_match( '/^https?:\/\//i', $endpoint ) ) {
				$errors[] = '上传接口必须以 http:// 或 https:// 开头';
			}
		}

		return array(
			'valid'  => empty( $errors ),
			'errors' => $errors,
		);
	}

	/**
	 * 创建存储驱动。
	 *
	 * @return Cloud_Storage_Interface|WP_Error
	 */
	private function create_storage_driver() {
		$storage = wpmcs_create_storage_driver( $this->settings );

		if ( is_wp_error( $storage ) ) {
			if ( function_exists( 'wpmcs_get_logger' ) ) {
				wpmcs_get_logger()->error( WPMCS_Logger::TYPE_SYSTEM, '存储驱动实例化失败', array(
					'error' => $storage->get_error_message(),
				) );
			}
			return $storage;
		}

		$this->storage = $storage;
		return $storage;
	}

	/**
	 * 测试上传功能。
	 *
	 * @return array|WP_Error
	 */
	private function test_upload() {
		if ( function_exists( 'wpmcs_get_logger' ) ) {
			wpmcs_get_logger()->info( WPMCS_Logger::TYPE_UPLOAD, '开始测试文件上传', array(
				'provider' => isset( $this->settings['provider'] ) ? sanitize_key( (string) $this->settings['provider'] ) : '',
			) );
		}

		$test_content = sprintf(
			"WP Multi Cloud Storage - 连接测试\n测试时间：%s\n随机 ID：%s",
			current_time( 'mysql' ),
			wp_generate_password( 12, false, false )
		);

		$temp_file = wp_tempnam( 'wpmcs-test-' );
		if ( ! $temp_file ) {
			return new WP_Error(
				'wpmcs_temp_file_failed',
				'无法创建临时测试文件'
			);
		}

		if ( false === file_put_contents( $temp_file, $test_content ) ) {
			@unlink( $temp_file );
			return new WP_Error(
				'wpmcs_write_file_failed',
				'无法写入测试文件'
			);
		}

		$test_key = $this->generate_test_key();

		try {
			$result = $this->storage->upload( $temp_file, $test_key );
			@unlink( $temp_file );

			if ( is_wp_error( $result ) ) {
				if ( function_exists( 'wpmcs_get_logger' ) ) {
					wpmcs_get_logger()->error( WPMCS_Logger::TYPE_UPLOAD, '测试上传返回错误', array(
						'key' => $test_key,
						'error' => $result->get_error_message(),
					) );
				}
				return $result;
			}

			if ( is_string( $result ) ) {
				return array(
					'key' => $test_key,
					'url' => $result,
				);
			}

			if ( is_array( $result ) && isset( $result['url'] ) ) {
				return array(
					'key' => isset( $result['key'] ) ? (string) $result['key'] : $test_key,
					'url' => (string) $result['url'],
				);
			}

			return new WP_Error(
				'wpmcs_invalid_upload_result',
				'上传返回结果格式无效'
			);
		} catch ( Exception $e ) {
			@unlink( $temp_file );

			if ( function_exists( 'wpmcs_get_logger' ) ) {
				wpmcs_get_logger()->error( WPMCS_Logger::TYPE_UPLOAD, '测试上传抛出异常', array(
					'key' => $test_key,
					'error' => $e->getMessage(),
				) );
			}

			return new WP_Error(
				'wpmcs_upload_exception',
				$e->getMessage()
			);
		}
	}

	/**
	 * 测试文件访问。
	 *
	 * @param string $url 文件 URL
	 * @return true|WP_Error
	 */
	private function test_file_access( $url ) {
		if ( function_exists( 'wpmcs_get_logger' ) ) {
			wpmcs_get_logger()->info( WPMCS_Logger::TYPE_UPLOAD, '开始测试文件访问', array(
				'url' => $url,
			) );
		}

		$response = wp_remote_get(
			$url,
			array(
				'timeout'   => 10,
				'sslverify' => false,
				'user-agent' => 'WP-Multi-Cloud-Storage/' . WPMCS_VERSION,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'wpmcs_file_access_failed',
				'无法访问文件：' . $response->get_error_message()
			);
		}

		$status_code = wp_remote_retrieve_response_code( $response );
		if ( 200 !== $status_code ) {
			return new WP_Error(
				'wpmcs_file_not_accessible',
				sprintf( '文件访问返回错误状态码：%d', $status_code )
			);
		}

		$body = (string) wp_remote_retrieve_body( $response );
		if ( false === strpos( $body, 'WP Multi Cloud Storage' ) ) {
			return new WP_Error(
				'wpmcs_file_content_mismatch',
				'文件内容验证失败，上传的可能不是预期文件'
			);
		}

		return true;
	}

	/**
	 * 测试删除功能。
	 *
	 * @param string $key 文件 key
	 * @return true|WP_Error
	 */
	private function test_delete( $key ) {
		if ( function_exists( 'wpmcs_get_logger' ) ) {
			wpmcs_get_logger()->info( WPMCS_Logger::TYPE_DELETE, '开始测试文件删除', array(
				'key' => $key,
			) );
		}

		try {
			$result = $this->storage->delete( $key );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			return true;
		} catch ( Exception $e ) {
			return new WP_Error(
				'wpmcs_delete_exception',
				$e->getMessage()
			);
		}
	}

	/**
	 * 清理测试文件。
	 *
	 * @param string $key 文件 key
	 */
	private function cleanup_test_file( $key ) {
		if ( $this->storage ) {
			if ( function_exists( 'wpmcs_get_logger' ) ) {
				wpmcs_get_logger()->info( WPMCS_Logger::TYPE_DELETE, '开始清理测试文件', array(
					'key' => $key,
				) );
			}
			try {
				$this->storage->delete( $key );
			} catch ( Exception $e ) {
				// 忽略清理失败
			}
		}
	}

	/**
	 * 生成测试文件 key。
	 *
	 * @return string
	 */
	private function generate_test_key() {
		$prefix = '';

		if ( ! empty( $this->settings['upload_path'] ) ) {
			$prefix = trim( (string) $this->settings['upload_path'], '/' ) . '/';
		}

		return $prefix . sprintf( 'wpmcs-test-%s.txt', gmdate( 'YmdHis' ) );
	}

	/**
	 * 快速连接测试，仅验证配置与驱动初始化。
	 *
	 * @return array
	 */
	public function quick_test() {
		$results = array(
			'success' => false,
			'message' => '',
			'details' => array(),
		);

		$validation = $this->validate_config();
		if ( ! $validation['valid'] ) {
			$results['message'] = '配置验证失败';
			$results['details'] = $validation['errors'];
			return $results;
		}

		$storage = wpmcs_create_storage_driver( $this->settings );
		if ( is_wp_error( $storage ) ) {
			$results['message'] = '存储驱动创建失败';
			$results['details'][] = $storage->get_error_message();
			return $results;
		}

		$results['success'] = true;
		$results['message'] = '配置验证通过';
		$results['details'][] = '配置格式正确';
		$results['details'][] = '存储驱动初始化成功';

		return $results;
	}
}
