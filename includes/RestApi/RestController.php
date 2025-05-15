<?php
namespace WPPluginDataFetcher\RestApi;

use WPPluginDataFetcher\Core\Constants;
use WPPluginDataFetcher\RestApi\Endpoints\BatchEndpoints;

/**
 * REST API Controller
 */
class RestController {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action('rest_api_init', [$this, 'register_routes']);
	}

	/**
	 * Register REST API routes
	 */
	public function register_routes() {
		$batch_endpoints = new BatchEndpoints();

		// Start batch process.
		register_rest_route(Constants::REST_NAMESPACE, '/batch/start', array(
			'methods' => 'POST',
			'callback' => [$batch_endpoints, 'start_batch_process'],
			'permission_callback' => [$this, 'check_permissions'],
			'args' => array(
				'plugin_slug' => array(
					'required' => true,
					'sanitize_callback' => 'sanitize_text_field',
				),
				'fetch_reviews' => array(
					'default' => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
				),
				'fetch_support' => array(
					'default' => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
				),
				'fetch_details' => array(
					'default' => true,
					'sanitize_callback' => 'rest_sanitize_boolean',
				),
				'max_items' => array(
					'default' => 100,
					'sanitize_callback' => 'absint',
				),
				'fetch_full_content' => array(
					'default' => false,
					'sanitize_callback' => 'rest_sanitize_boolean',
				),
			),
		));

		// Get batch status.
		register_rest_route(Constants::REST_NAMESPACE, '/batch/status/(?P<batch_id>[a-z0-9]+)', array(
			'methods' => 'GET',
			'callback' => [$batch_endpoints, 'get_batch_status'],
			'permission_callback' => [$this, 'check_permissions'],
		));

		// Process next batch.
		register_rest_route(Constants::REST_NAMESPACE, '/batch/process/(?P<batch_id>[a-z0-9]+)', array(
			'methods' => 'POST',
			'callback' => [$batch_endpoints, 'process_next_batch'],
			'permission_callback' => [$this, 'check_permissions'],
		));

		// Download batch results.
		register_rest_route(Constants::REST_NAMESPACE, '/batch/download/(?P<batch_id>[a-z0-9]+)', array(
			'methods' => 'GET',
			'callback' => [$batch_endpoints, 'download_batch_results'],
			'permission_callback' => [$this, 'check_permissions'],
			'args' => array(
				'type' => array(
					'default' => 'all',
					'enum' => array('all', 'details', 'reviews', 'support'),
				),
			),
		));

		// Cancel batch.
		register_rest_route(Constants::REST_NAMESPACE, '/batch/cancel/(?P<batch_id>[a-z0-9]+)', array(
			'methods' => 'POST',
			'callback' => [$batch_endpoints, 'cancel_batch'],
			'permission_callback' => [$this, 'check_permissions'],
		));
	}

	/**
	 * Check permissions for REST endpoints
	 */
	public function check_permissions() {
		return current_user_can('manage_options');
	}
}