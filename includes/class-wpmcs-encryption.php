<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * 加密/解密管理器
 *
 * 用于保护敏感信息（如 API 密钥）的存储
 */
class WPMCS_Encryption {

	/**
	 * 加密密钥（基于站点唯一标识符生成）
	 */
	private static function get_encryption_key() {
		// 使用站点的 auth salt 作为密钥基础
		$key = defined( 'AUTH_KEY' ) ? AUTH_KEY : 'wpmcs_default_key';

		// 使用 SHA-256 生成固定长度的密钥
		return hash( 'sha256', $key . get_option( 'siteurl' ), true );
	}

	/**
	 * 加密数据
	 *
	 * @param string $data 要加密的数据
	 * @return string|false 加密后的数据（Base64 编码）
	 */
	public static function encrypt( $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		$key = self::get_encryption_key();
		$iv  = random_bytes( 16 ); // AES-256-CBC 需要 16 字节 IV

		// 加密
		$encrypted = openssl_encrypt(
			$data,
			'AES-256-CBC',
			$key,
			0,
			$iv
		);

		if ( false === $encrypted ) {
			return false;
		}

		// 将 IV 和加密数据组合（IV 需要存储以便解密）
		$result = base64_encode( $iv . $encrypted );

		return $result;
	}

	/**
	 * 解密数据
	 *
	 * @param string $data 要解密的数据（Base64 编码）
	 * @return string|false 解密后的原始数据
	 */
	public static function decrypt( $data ) {
		if ( empty( $data ) ) {
			return '';
		}

		$key = self::get_encryption_key();

		// 解码 Base64
		$decoded = base64_decode( $data );

		if ( false === $decoded ) {
			return false;
		}

		// 提取 IV（前 16 字节）
		$iv         = substr( $decoded, 0, 16 );
		$encrypted = substr( $decoded, 16 );

		// 解密
		$decrypted = openssl_decrypt(
			$encrypted,
			'AES-256-CBC',
			$key,
			0,
			$iv
		);

		return $decrypted;
	}

	/**
	 * 隐藏敏感信息（用于日志显示）
	 *
	 * @param string $value 原始值
	 * @param int $show_chars 显示的前几位
	 * @return string 隐藏后的值
	 */
	public static function mask_sensitive_value( $value, $show_chars = 4 ) {
		if ( empty( $value ) ) {
			return '';
		}

		$value = (string) $value;

		if ( strlen( $value ) <= $show_chars + 4 ) {
			return str_repeat( '*', strlen( $value ) );
		}

		$prefix  = substr( $value, 0, $show_chars );
		$suffix  = substr( $value, -4 );
		$length  = strlen( $value ) - $show_chars - 4;
		$masked  = str_repeat( '*', $length );

		return $prefix . $masked . $suffix;
	}

	/**
	 * 检查数据是否已加密
	 *
	 * @param string $data 要检查的数据
	 * @return bool 是否已加密
	 */
	public static function is_encrypted( $data ) {
		if ( empty( $data ) ) {
			return false;
		}

		// Base64 编码的加密数据通常是 IV(16) + encrypted_data
		// 最小长度应该大于 16 字节
		$decoded = base64_decode( $data, true );

		if ( false === $decoded || strlen( $decoded ) < 16 ) {
			return false;
		}

		// 尝试解密，如果成功则认为是加密的
		$decrypted = self::decrypt( $data );
		return false !== $decrypted;
	}
}
