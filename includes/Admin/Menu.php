<?php
namespace WPPluginDataFetcher\Admin;

/**
 * Admin Menu Class
 */
class Menu {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'admin_menu', [ $this, 'register_menu' ] );
	}

	/**
	 * Register admin menu
	 */
	public function register_menu() {
		add_menu_page(
			__('Plugin Insights', 'wp-plugin-data-fetcher'),
			__('Plugin Insights', 'wp-plugin-data-fetcher'),
			'manage_options',
			'wp-plugin-data-fetcher',
			[$this, 'render_page'],
			'dashicons-analytics',
			25
		);
	}


	/**
	 * Render admin page
	 */
	public function render_page() {
		?>
		<div id="wpdf-root"></div>
		<?php
	}
}