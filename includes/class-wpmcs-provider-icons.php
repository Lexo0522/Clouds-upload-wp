<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WPMCS_Provider_Icons {
	private static $providers = array(
		'qiniu' => array(
			'name' => '七牛云',
			'icon' => 'qiniu.svg',
			'color' => '#00B4D8',
			'gradient' => 'linear-gradient(135deg, #00D4FF 0%, #0096E0 100%)',
			'website' => 'https://www.qiniu.com',
		),
		'aliyun_oss' => array(
			'name' => '阿里云 OSS',
			'icon' => 'Aliyun.svg',
			'color' => '#FF6A00',
			'gradient' => 'linear-gradient(135deg, #FF8534 0%, #FF6A00 100%)',
			'website' => 'https://www.aliyun.com/product/oss',
		),
		'tencent_cos' => array(
			'name' => '腾讯云 COS',
			'icon' => 'TencentCloud.svg',
			'color' => '#00A3FF',
			'gradient' => 'linear-gradient(135deg, #00C4FF 0%, #00A3FF 100%)',
			'website' => 'https://cloud.tencent.com/product/cos',
		),
		'upyun' => array(
			'name' => '又拍云',
			'icon' => 'upyun.svg',
			'color' => '#0096E0',
			'gradient' => 'linear-gradient(135deg, #00B4FF 0%, #0096E0 100%)',
			'website' => 'https://www.upyun.com',
		),
		'dogecloud' => array(
			'name' => '多吉云',
			'icon' => 'dogecloud.svg',
			'color' => '#4285F4',
			'gradient' => 'linear-gradient(135deg, #5B9CFF 0%, #4285F4 100%)',
			'website' => 'https://www.dogecloud.com',
		),
		'aws_s3' => array(
			'name' => 'AWS S3',
			'icon' => 'AWSS3.svg',
			'color' => '#FF9900',
			'gradient' => 'linear-gradient(135deg, #FFB84D 0%, #FF9900 100%)',
			'website' => 'https://aws.amazon.com/s3/',
		),
	);

	public static function get_all_providers() {
		return self::$providers;
	}

	public static function get_provider( $provider ) {
		return isset( self::$providers[ $provider ] ) ? self::$providers[ $provider ] : null;
	}

	public static function get_name( $provider ) {
		$config = self::get_provider( $provider );
		return $config ? $config['name'] : '未命名服务商';
	}

	public static function get_icon_url( $provider ) {
		$config = self::get_provider( $provider );

		if ( ! $config ) {
			return '';
		}

		$relative_path = 'assets/images/providers/' . $config['icon'];
		$file_path     = trailingslashit( WPMCS_PLUGIN_DIR ) . $relative_path;
		$url           = trailingslashit( WPMCS_PLUGIN_URL ) . $relative_path;

		if ( file_exists( $file_path ) ) {
			$version = filemtime( $file_path );
			if ( $version ) {
				$url = add_query_arg( 'v', $version, $url );
			}
		}

		return $url;
	}

	public static function get_color( $provider ) {
		$config = self::get_provider( $provider );
		return $config ? $config['color'] : '#666666';
	}

	public static function get_gradient( $provider ) {
		$config = self::get_provider( $provider );
		return $config ? $config['gradient'] : 'linear-gradient(135deg, #666 0%, #444 100%)';
	}

	public static function get_website( $provider ) {
		$config = self::get_provider( $provider );
		return $config ? $config['website'] : '#';
	}

	public static function render_icon( $provider, $size = 32, $attrs = array() ) {
		$url = self::get_icon_url( $provider );

		if ( ! $url ) {
			return '';
		}

		if ( $size <= 16 ) {
			$size_class = 'size-xs';
		} elseif ( $size <= 20 ) {
			$size_class = 'size-sm';
		} elseif ( $size <= 32 ) {
			$size_class = 'size-md';
		} elseif ( $size <= 48 ) {
			$size_class = 'size-lg';
		} elseif ( $size <= 64 ) {
			$size_class = 'size-xl';
		} else {
			$size_class = 'size-xxl';
		}

		$default_attrs = array(
			'src'   => $url,
			'alt'   => self::get_name( $provider ),
			'class' => 'wpmcs-provider-icon ' . $size_class,
			'style' => '',
		);

		$attrs = wp_parse_args( $attrs, $default_attrs );

		$html = '<img';
		foreach ( $attrs as $key => $value ) {
			$html .= ' ' . esc_attr( $key ) . '="' . esc_attr( $value ) . '"';
		}
		$html .= ' />';

		return $html;
	}

	public static function render_icon_with_name( $provider, $size = 32, $show_name = true ) {
		$icon = self::render_icon( $provider, $size );
		$name = self::get_name( $provider );

		$html = '<span class="wpmcs-provider-badge">';
		$html .= $icon;

		if ( $show_name ) {
			$html .= ' <span class="wpmcs-provider-name">' . esc_html( $name ) . '</span>';
		}

		$html .= '</span>';

		return $html;
	}

	public static function render_provider_selector( $selected = '', $name = 'provider', $id = 'wpmcs-provider-select' ) {
		$html = '<div class="wpmcs-provider-selector">';
		$html .= '<div class="wpmcs-provider-preview" id="wpmcs-provider-preview">';
		if ( $selected ) {
			$html .= self::render_icon( $selected, 40, array( 'id' => 'wpmcs-provider-icon-preview' ) );
		}
		$html .= '</div>';

		$html .= '<select name="' . esc_attr( $name ) . '" id="' . esc_attr( $id ) . '" class="wpmcs-provider-dropdown">';
		foreach ( self::$providers as $key => $config ) {
			$html .= sprintf(
				'<option value="%s" %s data-icon="%s">%s</option>',
				esc_attr( $key ),
				selected( $selected, $key, false ),
				esc_attr( self::get_icon_url( $key ) ),
				esc_html( $config['name'] )
			);
		}
		$html .= '</select>';
		$html .= '</div>';

		return $html;
	}

	public static function render_status_badge( $provider, $status = 'active' ) {
		$status_classes = array(
			'active' => 'wpmcs-status-active',
			'inactive' => 'wpmcs-status-inactive',
			'error' => 'wpmcs-status-error',
		);

		$status_labels = array(
			'active' => '已启用',
			'inactive' => '未启用',
			'error' => '错误',
		);

		$status_class = isset( $status_classes[ $status ] ) ? $status_classes[ $status ] : 'wpmcs-status-inactive';
		$status_label = isset( $status_labels[ $status ] ) ? $status_labels[ $status ] : '未启用';

		$html = '<span class="wpmcs-status-badge ' . esc_attr( $status_class ) . '" style="background-color: ' . esc_attr( self::get_color( $provider ) ) . '">';
		$html .= self::render_icon( $provider, 16 );
		$html .= ' <span class="wpmcs-status-label">' . esc_html( $status_label ) . '</span>';
		$html .= '</span>';

		return $html;
	}

	public static function output_icon_css() {
		wp_register_style(
			'wpmcs-provider-icons',
			WPMCS_PLUGIN_URL . 'assets/css/provider-icons.css',
			array(),
			WPMCS_VERSION
		);
		wp_print_styles( 'wpmcs-provider-icons' );
	}
}
