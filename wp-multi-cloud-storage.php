<?php
/**
 * Plugin Name: WP Multi Cloud Storage
 * Plugin URI: https://github.com/Lexo0522/Clouds-upload-wp
 * Description: Manage WordPress media uploads with S3-compatible object storage, remote URL replacement, and an admin SPA.
 * Version: 0.2.2
 * Author: kate522
 * Requires PHP: 7.4
 * Requires at least: 6.0
 * Text Domain: wp-multi-cloud-storage
 */

if (! defined('ABSPATH')) {
    exit;
}

define('WMCS_VERSION', '0.2.2');
define('WMCS_PLUGIN_FILE', __FILE__);
define('WMCS_PLUGIN_PATH', plugin_dir_path(__FILE__));
define('WMCS_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once WMCS_PLUGIN_PATH . 'includes/class-wmcs-settings.php';
require_once WMCS_PLUGIN_PATH . 'includes/class-wmcs-logger.php';
require_once WMCS_PLUGIN_PATH . 'includes/class-wmcs-s3-client.php';
require_once WMCS_PLUGIN_PATH . 'includes/class-wmcs-plugin.php';

WMCS_Plugin::instance();
