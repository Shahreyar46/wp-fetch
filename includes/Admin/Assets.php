<?php
namespace WPPluginDataFetcher\Admin;

use WPPluginDataFetcher\Core\Constants;

/**
 * Assets Management Class
 */
class Assets {
	/**
	 * Constructor
	 */
	public function __construct() {
		add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts']);
	}

	/**
	 * Enqueue admin scripts and styles.
	 */
	public function enqueue_scripts($hook) {

		if ( 'toplevel_page_wp-plugin-data-fetcher' !== $hook ) {
			return;
		}

		// Enqueue React build files.
		$admin_js = WPDF_PLUGIN_URL . 'build/admin.js';
		$admin_css = WPDF_PLUGIN_URL . 'build/admin.css';

		wp_enqueue_script(
			'wp-plugin-data-fetcher-react',
			$admin_js,
			array(),
			'5.0.0',
			true
		);

		wp_enqueue_style(
			'wp-plugin-data-fetcher-style',
			$admin_css,
			array(),
			'5.0.0'
		);

		// Localize script with necessary data.
		wp_localize_script(
			'wp-plugin-data-fetcher-react',
			'wpPluginDataFetcher',
			array(
				'ajax_url' => admin_url('admin-ajax.php'),
				'rest_url' => esc_url_raw(rest_url(Constants::REST_NAMESPACE)),
				'nonce' => wp_create_nonce('wp_rest'),
			)
		);
	}
}