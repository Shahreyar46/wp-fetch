<?php
namespace WPPluginDataFetcher\RestApi\Endpoints;

use WP_Error;
use WP_REST_Request;
use WPPluginDataFetcher\Core\Constants;
use WPPluginDataFetcher\DataFetcher\WordPressDataFetcher;

/**
 * Batch Endpoints Handler
 */
class BatchEndpoints {

	/**
	 * Constructor
	 */
	public function __construct() {
		// Increase limits for large operations.
		@ini_set('memory_limit', '512M');
		@ini_set('max_execution_time', 300);
	}

	/**
	 * Start batch process
	 */
	public function start_batch_process(WP_REST_Request $request) {
		$plugin_slug = $request->get_param('plugin_slug');
		$fetch_reviews = $request->get_param('fetch_reviews');
		$fetch_support = $request->get_param('fetch_support');
		$fetch_details = $request->get_param('fetch_details');
		$max_items = $request->get_param('max_items');
		$fetch_full_content = $request->get_param('fetch_full_content');

		// Generate unique batch ID.
		$batch_id = md5($plugin_slug . time() . wp_rand());

		// Initialize batch data.
		$batch_data = array(
			'id' => $batch_id,
			'plugin_slug' => $plugin_slug,
			'status' => 'running',
			'progress' => 0,
			'total' => 0,
			'current' => 0,
			'fetch_reviews' => $fetch_reviews,
			'fetch_support' => $fetch_support,
			'fetch_details' => $fetch_details,
			'max_items' => $max_items,
			'fetch_full_content' => $fetch_full_content,
			'results' => array(
				'details' => null,
				'reviews' => array(),
				'support' => array(),
			),
			'current_page' => 1,
			'current_type' => 'details',
			'created' => current_time('mysql'),
			'updated' => current_time('mysql'),
		);

		// Save batch data.
		update_option(Constants::OPTION_PREFIX . $batch_id, $batch_data, false);

		// Start with plugin details.
		if ($fetch_details) {
			$this->fetch_plugin_details($batch_id);
		}

		return rest_ensure_response(array(
			'batch_id' => $batch_id,
			'status' => 'started',
			'message' => 'Batch process started successfully',
		));
	}

	/**
	 * Get batch status
	 */
	public function get_batch_status(WP_REST_Request $request) {
		$batch_id = $request->get_param('batch_id');
		$batch_data = get_option(Constants::OPTION_PREFIX . $batch_id);

		if (!$batch_data) {
			return new WP_Error('batch_not_found', 'Batch not found', array('status' => 404));
		}

		return rest_ensure_response(array(
			'batch_id' => $batch_id,
			'status' => $batch_data['status'],
			'progress' => $batch_data['progress'],
			'total' => $batch_data['total'],
			'current' => $batch_data['current'],
			'current_type' => $batch_data['current_type'],
			'updated' => $batch_data['updated'],
		));
	}

	/**
	 * Process next batch
	 */
	public function process_next_batch(WP_REST_Request $request) {
		$batch_id = $request->get_param('batch_id');
		$batch_data = get_option(Constants::OPTION_PREFIX . $batch_id);

		if (!$batch_data) {
			return new WP_Error('batch_not_found', 'Batch not found', array('status' => 404));
		}

		if ($batch_data['status'] !== 'running') {
			return rest_ensure_response(array(
				'batch_id' => $batch_id,
				'status' => $batch_data['status'],
				'message' => 'Batch is not running',
			));
		}

		// Process based on current type.
		switch ($batch_data['current_type']) {
			case 'details':
				// Details should already be fetched, move to reviews
				if ($batch_data['fetch_reviews']) {
					$batch_data['current_type'] = 'reviews';
					$batch_data['current_page'] = 1;
					// Reset progress for reviews
					$batch_data['current'] = 0;
					$batch_data['total'] = $batch_data['max_items'];
					$batch_data['progress'] = 0;
				} elseif ($batch_data['fetch_support']) {
					$batch_data['current_type'] = 'support';
					$batch_data['current_page'] = 1;
					// Reset progress for support
					$batch_data['current'] = 0;
					$batch_data['total'] = $batch_data['max_items'];
					$batch_data['progress'] = 0;
				} else {
					$batch_data['status'] = 'completed';
					$batch_data['progress'] = 100;
				}
				// Update batch data immediately
				$batch_data['updated'] = current_time('mysql');
				update_option(Constants::OPTION_PREFIX . $batch_id, $batch_data, false);
				break;

			case 'reviews':
				$this->process_reviews_batch($batch_id);
				// Re-fetch the updated batch data
				$batch_data = get_option(Constants::OPTION_PREFIX . $batch_id);
				break;

			case 'support':
				$this->process_support_batch($batch_id);
				// Re-fetch the updated batch data
				$batch_data = get_option(Constants::OPTION_PREFIX . $batch_id);
				break;
		}

		return rest_ensure_response(array(
			'batch_id' => $batch_id,
			'status' => $batch_data['status'],
			'progress' => $batch_data['progress'],
			'total' => $batch_data['total'],
			'current' => $batch_data['current'],
			'current_type' => $batch_data['current_type'],
		));
	}

	/**
	 * Download batch results
	 */
	public function download_batch_results(WP_REST_Request $request) {
		$batch_id = $request->get_param('batch_id');
		$type = $request->get_param('type');
		$page = $request->get_param('page') ?: 1;
		$per_page = $request->get_param('per_page') ?: 20;

		// Set proper PHP limits
		@ini_set('memory_limit', '512M');
		@ini_set('max_execution_time', 300);

		$batch_data = get_option(Constants::OPTION_PREFIX . $batch_id);

		if (!$batch_data) {
			return new WP_Error('batch_not_found', 'Batch not found', array('status' => 404));
		}

		// Check if this is a download request.
		$is_download = isset($_GET['download']) && $_GET['download'] === 'true';

		// For preview mode with pagination.
		if (!$is_download) {
			$results = $this->get_paginated_results($batch_data, $type, $page, $per_page);
			return rest_ensure_response($results);
		}

		// For download mode, stream the full data.
		$results = $this->get_full_results($batch_data, $type);

		$filename = $batch_data['plugin_slug'] . '-' . $type . '-' . date('Y-m-d-His') . '.json';

		// Set download headers.
		nocache_headers();
		header('Content-Type: application/json; charset=utf-8');
		header('Content-Disposition: attachment; filename="' . $filename . '"');
		header('Content-Transfer-Encoding: binary');
		header('Connection: close');

		// Stream the JSON data.
		echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
		exit;
	}

	/**
	 * Cancel batch
	 */
	public function cancel_batch(WP_REST_Request $request) {
		$batch_id = $request->get_param('batch_id');
		$batch_data = get_option(Constants::OPTION_PREFIX . $batch_id);

		if (!$batch_data) {
			return new WP_Error('batch_not_found', 'Batch not found', array('status' => 404));
		}

		$batch_data['status'] = 'cancelled';
		$batch_data['updated'] = current_time('mysql');
		update_option(Constants::OPTION_PREFIX . $batch_id, $batch_data, false);

		return rest_ensure_response(array(
			'batch_id' => $batch_id,
			'status' => 'cancelled',
			'message' => 'Batch cancelled successfully',
		));
	}

	/**
	 * Fetch plugin details
	 */
	private function fetch_plugin_details($batch_id) {
		$batch_data = get_option(Constants::OPTION_PREFIX . $batch_id);
		if (!$batch_data) return;

		$fetcher = new WordPressDataFetcher();
		$plugin_slug = $batch_data['plugin_slug'];

		// Fetch plugin details using the existing methods.
		if (!function_exists('plugins_api')) {
			require_once(ABSPATH . 'wp-admin/includes/plugin-install.php');
		}

		$args = array(
			'slug' => $plugin_slug,
			'fields' => array(
				'short_description' => true,
				'description' => true,
				'sections' => true,
				'tested' => true,
				'requires' => true,
				'requires_php' => true,
				'rating' => true,
				'ratings' => true,
				'downloaded' => true,
				'downloadlink' => true,
				'last_updated' => true,
				'added' => true,
				'tags' => true,
				'compatibility' => true,
				'homepage' => true,
				'versions' => true,
				'donate_link' => true,
				'reviews' => true,
				'banners' => true,
				'icons' => true,
				'active_installs' => true,
				'contributors' => true,
				'support_threads' => true,
				'support_threads_resolved' => true,
				'num_ratings' => true,
				'average_rating' => true,
				'author' => true,
				'author_profile' => true
			)
		);

		$api_response = plugins_api('plugin_information', $args);

		if (!is_wp_error($api_response)) {
			$batch_data['results']['details'] = $this->object_to_array($api_response);
		}

		// Move to next type.
		if ($batch_data['fetch_reviews']) {
			$batch_data['current_type'] = 'reviews';
			$batch_data['current_page'] = 1;
			// Initialize progress for reviews
			$batch_data['current'] = 0;
			$batch_data['total'] = $batch_data['max_items'];
			$batch_data['progress'] = 0;
		} elseif ($batch_data['fetch_support']) {
			$batch_data['current_type'] = 'support';
			$batch_data['current_page'] = 1;
			// Initialize progress for support
			$batch_data['current'] = 0;
			$batch_data['total'] = $batch_data['max_items'];
			$batch_data['progress'] = 0;
		} else {
			$batch_data['status'] = 'completed';
			$batch_data['progress'] = 100;
		}

		$batch_data['updated'] = current_time('mysql');
		update_option(Constants::OPTION_PREFIX . $batch_id, $batch_data, false);
	}

	/**
	 * Process reviews batch
	 */
	private function process_reviews_batch($batch_id) {
		$batch_data = get_option(Constants::OPTION_PREFIX . $batch_id);
		if (!$batch_data || !$batch_data['fetch_reviews']) return;

		$fetcher = new WordPressDataFetcher();
		$plugin_slug = $batch_data['plugin_slug'];
		$current_page = $batch_data['current_page'];

		// Check if we already have enough items.
		$current_count = count($batch_data['results']['reviews']);
		if ($current_count >= $batch_data['max_items']) {
			// Move to support or complete
			if ($batch_data['fetch_support']) {
				$batch_data['current_type'] = 'support';
				$batch_data['current_page'] = 1;
				// Reset progress for support tickets
				$batch_data['current'] = 0;
				$batch_data['total'] = $batch_data['max_items'];
				$batch_data['progress'] = 0;
			} else {
				$batch_data['status'] = 'completed';
				$batch_data['progress'] = 100;
			}
			$batch_data['updated'] = current_time('mysql');
			update_option(Constants::OPTION_PREFIX . $batch_id, $batch_data, false);
			return;
		}

		// Fetch a page of reviews.
		$url = $current_page === 1 
			? sprintf('https://wordpress.org/support/plugin/%s/reviews/', sanitize_title($plugin_slug))
			: sprintf('https://wordpress.org/support/plugin/%s/reviews/page/%d/', sanitize_title($plugin_slug), $current_page);

		$response = wp_remote_get($url, array(
			'timeout' => 30,
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
		));

		if (!is_wp_error($response)) {
			$body = wp_remote_retrieve_body($response);
			// Create temporary fetcher instance to use its methods
			$temp_fetcher = new WordPressDataFetcher();
			$reviews = $temp_fetcher->parse_wordpress_reviews_html($body);

			if (!empty($reviews)) {
				// Limit the number of reviews to add based on max_items
				$items_needed = $batch_data['max_items'] - $current_count;
				$reviews_to_add = array_slice($reviews, 0, $items_needed);

				// If fetching full content, process in smaller chunks
				if ($batch_data['fetch_full_content']) {
					foreach ($reviews_to_add as &$review) {
						if (!empty($review['url'])) {
							$content = $temp_fetcher->fetch_review_content($review['url']);
							if (!empty($content)) {
								$review['description'] = $content;
							}
							usleep(200000); // 0.2 second delay
						}
					}
				}

				$batch_data['results']['reviews'] = array_merge(
					$batch_data['results']['reviews'],
					$reviews_to_add
				);

				$batch_data['current'] = count($batch_data['results']['reviews']);
				$batch_data['total'] = $batch_data['max_items'];
				$batch_data['progress'] = min(100, ($batch_data['current'] / $batch_data['max_items']) * 100);

				// Check if we need more reviews
				if ($batch_data['current'] < $batch_data['max_items'] && count($reviews) >= 30) {
					$batch_data['current_page']++;
				} else {
					// Move to support or complete
					if ($batch_data['fetch_support']) {
						$batch_data['current_type'] = 'support';
						$batch_data['current_page'] = 1;
						// Reset progress for support tickets
						$batch_data['current'] = 0;
						$batch_data['total'] = $batch_data['max_items'];
						$batch_data['progress'] = 0;
					} else {
						$batch_data['status'] = 'completed';
						$batch_data['progress'] = 100;
					}
				}
			} else {
				// No more reviews, move to next type
				if ($batch_data['fetch_support']) {
					$batch_data['current_type'] = 'support';
					$batch_data['current_page'] = 1;
					// Reset progress for support tickets
					$batch_data['current'] = 0;
					$batch_data['total'] = $batch_data['max_items'];
					$batch_data['progress'] = 0;
				} else {
					$batch_data['status'] = 'completed';
					$batch_data['progress'] = 100;
				}
			}
		} else {
			// Error fetching, move to next type or complete
			if ($batch_data['fetch_support']) {
				$batch_data['current_type'] = 'support';
				$batch_data['current_page'] = 1;
				// Reset progress for support
				$batch_data['current'] = 0;
				$batch_data['total'] = $batch_data['max_items'];
				$batch_data['progress'] = 0;
			} else {
				$batch_data['status'] = 'completed';
				$batch_data['progress'] = 100;
			}
		}

		$batch_data['updated'] = current_time('mysql');
		update_option(Constants::OPTION_PREFIX . $batch_id, $batch_data, false);
	}

	/**
	 * Process support batch
	 */
	private function process_support_batch($batch_id) {
		$batch_data = get_option(Constants::OPTION_PREFIX . $batch_id);
		if (!$batch_data || !$batch_data['fetch_support']) return;

		$fetcher = new WordPressDataFetcher();
		$plugin_slug = $batch_data['plugin_slug'];
		$current_page = $batch_data['current_page'];

		// Check if we already have enough items
		$current_count = count($batch_data['results']['support']);
		if ($current_count >= $batch_data['max_items']) {
			$batch_data['status'] = 'completed';
			$batch_data['progress'] = 100;
			$batch_data['updated'] = current_time('mysql');
			update_option(Constants::OPTION_PREFIX . $batch_id, $batch_data, false);
			return;
		}

		// Fetch a page of support tickets.
		$url = $current_page === 1 
			? sprintf('https://wordpress.org/support/plugin/%s/', sanitize_title($plugin_slug))
			: sprintf('https://wordpress.org/support/plugin/%s/page/%d/', sanitize_title($plugin_slug), $current_page);

		$response = wp_remote_get($url, array(
			'timeout' => 30,
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
		));

		if (!is_wp_error($response)) {
			$body = wp_remote_retrieve_body($response);
			// Create temporary fetcher instance to use its methods
			$temp_fetcher = new WordPressDataFetcher();
			$tickets = $temp_fetcher->parse_wordpress_support_tickets_html($body);

			if (!empty($tickets)) {
				// Limit the number of tickets to add based on max_items
				$items_needed = $batch_data['max_items'] - $current_count;
				$tickets_to_add = array_slice($tickets, 0, $items_needed);

				// If fetching full content, process in smaller chunks
				if ($batch_data['fetch_full_content']) {
					foreach ($tickets_to_add as &$ticket) {
						if (!empty($ticket['url'])) {
							$content = $temp_fetcher->fetch_ticket_content($ticket['url']);
							if (!empty($content)) {
								$ticket = array_merge($ticket, $content);
							}
							usleep(200000); // 0.2 second delay
						}
					}
				}
				
				$batch_data['results']['support'] = array_merge(
					$batch_data['results']['support'],
					$tickets_to_add
				);
				
				$batch_data['current'] = count($batch_data['results']['support']);
				$batch_data['total'] = $batch_data['max_items'];
				$batch_data['progress'] = min(100, ($batch_data['current'] / $batch_data['max_items']) * 100);
				
				// Check if we need more tickets
				if ($batch_data['current'] < $batch_data['max_items'] && count($tickets) >= 30) {
					$batch_data['current_page']++;
				} else {
					$batch_data['status'] = 'completed';
					$batch_data['progress'] = 100;
				}
			} else {
				// No more tickets
				$batch_data['status'] = 'completed';
				$batch_data['progress'] = 100;
			}
		} else {
			// Error fetching, complete
			$batch_data['status'] = 'completed';
			$batch_data['progress'] = 100;
		}
		
		$batch_data['updated'] = current_time('mysql');
		update_option(Constants::OPTION_PREFIX . $batch_id, $batch_data, false);
	}
	
	/**
	 * Get paginated results for preview
	 */
	private function get_paginated_results($batch_data, $type, $page = 1, $per_page = 20) {
		$offset = ($page - 1) * $per_page;
		
		switch ($type) {
			case 'details':
				return array(
					'data' => $batch_data['results']['details'],
					'pagination' => array(
						'page' => 1,
						'per_page' => 1,
						'total' => 1,
						'total_pages' => 1
					)
				);
				
			case 'reviews':
				$total = count($batch_data['results']['reviews']);
				$items = array_slice($batch_data['results']['reviews'], $offset, $per_page);
				
				return array(
					'plugin_slug' => $batch_data['plugin_slug'],
					'data' => array(
						'review_count' => $total,
						'reviews' => $items,
					),
					'pagination' => array(
						'page' => $page,
						'per_page' => $per_page,
						'total' => $total,
						'total_pages' => ceil($total / $per_page),
						'has_more' => ($offset + $per_page) < $total
					)
				);
				
			case 'support':
				$total = count($batch_data['results']['support']);
				$items = array_slice($batch_data['results']['support'], $offset, $per_page);
				
				return array(
					'plugin_slug' => $batch_data['plugin_slug'],
					'data' => array(
						'ticket_count' => $total,
						'tickets' => $items,
					),
					'pagination' => array(
						'page' => $page,
						'per_page' => $per_page,
						'total' => $total,
						'total_pages' => ceil($total / $per_page),
						'has_more' => ($offset + $per_page) < $total
					)
				);
				
			case 'all':
			default:
				$reviews_total = count($batch_data['results']['reviews']);
				$support_total = count($batch_data['results']['support']);
				
				$reviews_items = array_slice($batch_data['results']['reviews'], 0, $per_page);
				$support_items = array_slice($batch_data['results']['support'], 0, $per_page);
				
				return array(
					'plugin_slug' => $batch_data['plugin_slug'],
					'data' => array(
						'details' => $batch_data['results']['details'],
						'reviews' => array(
							'review_count' => $reviews_total,
							'reviews' => $reviews_items,
						),
						'support' => array(
							'ticket_count' => $support_total,
							'tickets' => $support_items,
						),
					),
					'pagination' => array(
						'reviews' => array(
							'page' => 1,
							'per_page' => $per_page,
							'total' => $reviews_total,
							'total_pages' => ceil($reviews_total / $per_page),
							'has_more' => $per_page < $reviews_total
						),
						'support' => array(
							'page' => 1,
							'per_page' => $per_page,
							'total' => $support_total,
							'total_pages' => ceil($support_total / $per_page),
							'has_more' => $per_page < $support_total
						)
					)
				);
		}
	}
	
	/**
	 * Get full results for download
	 */
	private function get_full_results($batch_data, $type) {
		switch ($type) {
			case 'details':
				return $batch_data['results']['details'];
				
			case 'reviews':
				return array(
					'plugin_slug' => $batch_data['plugin_slug'],
					'review_count' => count($batch_data['results']['reviews']),
					'reviews' => $batch_data['results']['reviews'],
				);
				
			case 'support':
				return array(
					'plugin_slug' => $batch_data['plugin_slug'],
					'ticket_count' => count($batch_data['results']['support']),
					'tickets' => $batch_data['results']['support'],
				);
				
			case 'all':
			default:
				return array(
					'plugin_slug' => $batch_data['plugin_slug'],
					'details' => $batch_data['results']['details'],
					'reviews' => array(
						'review_count' => count($batch_data['results']['reviews']),
						'reviews' => $batch_data['results']['reviews'],
					),
					'support' => array(
						'ticket_count' => count($batch_data['results']['support']),
						'tickets' => $batch_data['results']['support'],
					),
				);
		}
	}
	
	/**
	 * Helper function to convert objects to arrays recursively
	 */
	private function object_to_array($obj) {
		if (is_object($obj)) {
			$obj = (array) $obj;
		}
		if (is_array($obj)) {
			$new = array();
			foreach ($obj as $key => $val) {
				$new[$key] = is_object($val) || is_array($val) ? $this->object_to_array($val) : $val;
			}
		} else {
			$new = $obj;
		}
		return $new;
	}
}