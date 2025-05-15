<?php
namespace WPPluginDataFetcher\Core;

use WPPluginDataFetcher\Admin\Menu;
use WPPluginDataFetcher\Admin\Assets;
use WPPluginDataFetcher\RestApi\RestController;

/**
 * Main Plugin Class
 */
class Plugin {
	
	/**
	 * Plugin instance
	 *
	 * @var Plugin
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return Plugin
	 */
	public static function get_instance() {
		if (null === self::$instance) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		$this->init();
	}

	/**
	 * Initialize the plugin
	 */
	private function init() {
		// Initialize components.
		new Menu();
		new Assets();
		new RestController();

		// Schedule cleanup of old batch data.
		if (!wp_next_scheduled('wpdf_cleanup_batch_data')) {
			wp_schedule_event(time(), 'daily', 'wpdf_cleanup_batch_data');
		}
		add_action('wpdf_cleanup_batch_data', [$this, 'cleanup_old_batch_data']);
	}

	/**
	 * Plugin activation
	 */
	public static function activate() {
		// Create cache directory.
		$upload_dir = wp_upload_dir();
		$cache_dir = $upload_dir['basedir'] . '/wp-plugin-data-fetcher';

		if (!file_exists($cache_dir)) {
			wp_mkdir_p($cache_dir);
		}

		// Create .htaccess file to protect direct access.
		$htaccess_file = $cache_dir . '/.htaccess';
		if (!file_exists($htaccess_file)) {
			$htaccess_content = "Order deny,allow\nDeny from all";
			file_put_contents($htaccess_file, $htaccess_content);
		}

		// Create empty index.php.
		$index_file = $cache_dir . '/index.php';
		if (!file_exists($index_file)) {
			file_put_contents($index_file, '<?php // Silence is golden');
		}
	}

	/**
	 * Plugin deactivation
	 */
	public static function deactivate() {
		// Clear scheduled events.
		wp_clear_scheduled_hook('wpdf_cleanup_batch_data');
	}

	/**
	 * Cleanup old batch data
	 */
	public function cleanup_old_batch_data() {
		global $wpdb;

		// Delete batch data older than 7 days.
		$old_date = date('Y-m-d H:i:s', strtotime('-7 days'));

		$options = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT option_name FROM $wpdb->options 
				WHERE option_name LIKE %s",
				$wpdb->esc_like(Constants::OPTION_PREFIX) . '%'
			)
		);

		foreach ($options as $option_name) {
			$batch_data = get_option($option_name);
			if ($batch_data && isset($batch_data['created']) && $batch_data['created'] < $old_date) {
				delete_option($option_name);
			}
		}
	}
}
