<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

interface Cloud_Storage_Interface {
	/**
	 * 将本地文件上传到云存储。
	 *
	 * @param string $file_path 本地文件路径。
	 * @param string $file_name 目标文件名或对象 key。
	 * @return string 上传成功后的云端文件 URL。
	 *
	 * @throws RuntimeException 上传失败时抛出。
	 */
	public function upload( $file_path, $file_name );

	/**
	 * 删除云端文件。
	 *
	 * @param string $file_path 云端对象 key。
	 * @return bool 成功时返回 true。
	 *
	 * @throws RuntimeException 删除失败时抛出。
	 */
	public function delete( $file_path );

	/**
	 * 获取云端文件的公开访问 URL。
	 *
	 * @param string $file_path 云端对象 key。
	 * @return string
	 */
	public function get_url( $file_path );
}
