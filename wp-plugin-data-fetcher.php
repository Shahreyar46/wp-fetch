<?php
/**
 * Plugin Name: WP Plugin Data Fetcher
 * Description: Fetch and display plugin reviews and support data from WordPress.org
 * Version: 1.1.0
 * Author: Shahreyar
 * Author URI: https://yourwebsite.com
 * Text Domain: wp-plugin-data-fetcher
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants.
define( 'WPDF_PLUGIN_FILE', __FILE__ );
define( 'WPDF_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPDF_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Include Composer autoloader.
require_once WPDF_PLUGIN_DIR . 'vendor/autoload.php';

use WPPluginDataFetcher\Core\Plugin;

// Initialize the plugin.
function wpdf_init() {
	return Plugin::get_instance();
}

// Start the plugin.
add_action( 'plugins_loaded', 'wpdf_init' );

// Register activation hook.
register_activation_hook( __FILE__, [ Plugin::class, 'activate' ] );

// Register deactivation hook.
register_deactivation_hook( __FILE__, [ Plugin::class, 'deactivate' ] );