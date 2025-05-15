<?php
/**
 * Plugin Name: WP Plugin Data Fetcher
 * Description: Fetch and display plugin reviews and support data from WordPress.org
 * Version: 4.0.0
 * Author: Shahreyar
 * Author URI: https://yourwebsite.com
 * Text Domain: wp-plugin-data-fetcher
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

// Include the main fetcher class
require_once plugin_dir_path(__FILE__) . 'includes/class-wordpress-data-fetcher.php';

class WP_Plugin_Data_Fetcher {
    
    const REST_NAMESPACE = 'wp-plugin-fetcher/v1';
    const OPTION_PREFIX = 'wpdf_batch_';
    const BATCH_SIZE = 50; // Number of items to process per batch

    /**
     * Initialize
     */
    private function init() {
        // Increase limits for large operations
        @ini_set('memory_limit', '512M');
        @ini_set('max_execution_time', 300);
    }
    
    /**
     * Constructor
     */
   public function __construct() {
        // Add admin menu
        add_action('admin_menu', array($this, 'add_admin_menu'));
        
        // Register scripts and styles for React app
        add_action('admin_enqueue_scripts', array($this, 'register_react_scripts'));
        
        // Register REST API endpoints
        add_action('rest_api_init', array($this, 'register_rest_endpoints'));
        
        // Register activation hook
        register_activation_hook(__FILE__, array($this, 'plugin_activation'));
        
        // Schedule cleanup of old batch data
        if (!wp_next_scheduled('wpdf_cleanup_batch_data')) {
            wp_schedule_event(time(), 'daily', 'wpdf_cleanup_batch_data');
        }
        add_action('wpdf_cleanup_batch_data', array($this, 'cleanup_old_batch_data'));
    }

     /**
     * Register React scripts and styles
     */
    public function register_react_scripts($hook) {
        if ('tools_page_wp-plugin-data-fetcher' !== $hook) {
            return;
        }
        
        // Enqueue React build files
        $admin_js = plugin_dir_url(__FILE__) . 'build/admin.js';
        $admin_css = plugin_dir_url(__FILE__) . 'build/admin.css';
        
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
        
        // Localize script with necessary data
        wp_localize_script(
            'wp-plugin-data-fetcher-react',
            'wpPluginDataFetcher',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => esc_url_raw(rest_url(self::REST_NAMESPACE)),
                'nonce' => wp_create_nonce('wp_rest'),
            )
        );
    }
    
    /**
     * Admin page content for React app
     */
    public function admin_page() {
        ?>
        <div id="wpdf-root"></div>
        <?php
    }
    
	/**
     * Register REST API endpoints
     */
    public function register_rest_endpoints() {
        // Start batch process
        register_rest_route(self::REST_NAMESPACE, '/batch/start', array(
            'methods' => 'POST',
            'callback' => array($this, 'start_batch_process'),
            'permission_callback' => array($this, 'check_permissions'),
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
        
        // Get batch status
        register_rest_route(self::REST_NAMESPACE, '/batch/status/(?P<batch_id>[a-z0-9]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'get_batch_status'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        // Process next batch
        register_rest_route(self::REST_NAMESPACE, '/batch/process/(?P<batch_id>[a-z0-9]+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'process_next_batch'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
        
        // Download batch results
        register_rest_route(self::REST_NAMESPACE, '/batch/download/(?P<batch_id>[a-z0-9]+)', array(
            'methods' => 'GET',
            'callback' => array($this, 'download_batch_results'),
            'permission_callback' => array($this, 'check_permissions'),
            'args' => array(
                'type' => array(
                    'default' => 'all',
                    'enum' => array('all', 'details', 'reviews', 'support'),
                ),
            ),
        ));
        
        // Cancel batch
        register_rest_route(self::REST_NAMESPACE, '/batch/cancel/(?P<batch_id>[a-z0-9]+)', array(
            'methods' => 'POST',
            'callback' => array($this, 'cancel_batch'),
            'permission_callback' => array($this, 'check_permissions'),
        ));
    }


    /**
     * Check permissions for REST endpoints
     */
    public function check_permissions() {
        return current_user_can('manage_options');
    }
    
    /**
     * Start batch process
     */
    public function start_batch_process($request) {
        $plugin_slug = $request->get_param('plugin_slug');
        $fetch_reviews = $request->get_param('fetch_reviews');
        $fetch_support = $request->get_param('fetch_support');
        $fetch_details = $request->get_param('fetch_details');
        $max_items = $request->get_param('max_items');
        $fetch_full_content = $request->get_param('fetch_full_content');
        
        // Generate unique batch ID
        $batch_id = md5($plugin_slug . time() . wp_rand());
        
        // Initialize batch data
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
        
        // Save batch data
        update_option(self::OPTION_PREFIX . $batch_id, $batch_data, false);
        
        // Start with plugin details
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
    public function get_batch_status($request) {
        $batch_id = $request->get_param('batch_id');
        $batch_data = get_option(self::OPTION_PREFIX . $batch_id);
        
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
    public function process_next_batch($request) {
        $batch_id = $request->get_param('batch_id');
        $batch_data = get_option(self::OPTION_PREFIX . $batch_id);
        
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
        
        // Process based on current type
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
                update_option(self::OPTION_PREFIX . $batch_id, $batch_data, false);
                break;
                
            case 'reviews':
                $this->process_reviews_batch($batch_id);
                // Re-fetch the updated batch data
                $batch_data = get_option(self::OPTION_PREFIX . $batch_id);
                break;
                
            case 'support':
                $this->process_support_batch($batch_id);
                // Re-fetch the updated batch data
                $batch_data = get_option(self::OPTION_PREFIX . $batch_id);
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
     * Fetch plugin details
     */
    private function fetch_plugin_details($batch_id) {
        $batch_data = get_option(self::OPTION_PREFIX . $batch_id);
        if (!$batch_data) return;
        
        $fetcher = new WordPressDataFetcher();
        $plugin_slug = $batch_data['plugin_slug'];
        
        // Fetch plugin details using the existing methods
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
        
        // Move to next type
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
        update_option(self::OPTION_PREFIX . $batch_id, $batch_data, false);
    }
    
    /**
     * Process reviews batch
     */
    private function process_reviews_batch($batch_id) {
        $batch_data = get_option(self::OPTION_PREFIX . $batch_id);
        if (!$batch_data || !$batch_data['fetch_reviews']) return;
        
        $fetcher = new WordPressDataFetcher();
        $plugin_slug = $batch_data['plugin_slug'];
        $current_page = $batch_data['current_page'];
        
        // Check if we already have enough items
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
            update_option(self::OPTION_PREFIX . $batch_id, $batch_data, false);
            return;
        }
        
        // Fetch a page of reviews
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
        update_option(self::OPTION_PREFIX . $batch_id, $batch_data, false);
    }
    
    /**
     * Process support batch
     */
    private function process_support_batch($batch_id) {
        $batch_data = get_option(self::OPTION_PREFIX . $batch_id);
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
            update_option(self::OPTION_PREFIX . $batch_id, $batch_data, false);
            return;
        }
        
        // Fetch a page of support tickets
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
        update_option(self::OPTION_PREFIX . $batch_id, $batch_data, false);
    }
    

/**
 * Download batch results
 */
public function download_batch_results($request) {
    $batch_id = $request->get_param('batch_id');
    $type = $request->get_param('type');
    $page = $request->get_param('page') ?: 1;
    $per_page = $request->get_param('per_page') ?: 20;
    
    // Set proper PHP limits
    @ini_set('memory_limit', '512M');
    @ini_set('max_execution_time', 300);
    
    $batch_data = get_option(self::OPTION_PREFIX . $batch_id);
    
    if (!$batch_data) {
        return new WP_Error('batch_not_found', 'Batch not found', array('status' => 404));
    }
    
    // Check if this is a download request
    $is_download = isset($_GET['download']) && $_GET['download'] === 'true';
    
    // For preview mode with pagination
    if (!$is_download) {
        $results = $this->get_paginated_results($batch_data, $type, $page, $per_page);
        return rest_ensure_response($results);
    }
    
    // For download mode, stream the full data
    $results = $this->get_full_results($batch_data, $type);
    
    $filename = $batch_data['plugin_slug'] . '-' . $type . '-' . date('Y-m-d-His') . '.json';
    
    // Set download headers
    nocache_headers();
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Connection: close');
    
    // Stream the JSON data
    echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
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
 * Get limited results for preview
 */
private function get_limited_results($batch_data, $type) {
    $limit = 50; // Limit items for preview
    
    switch ($type) {
        case 'details':
            return $batch_data['results']['details'];
            
        case 'reviews':
            $reviews = array_slice($batch_data['results']['reviews'], 0, $limit);
            return array(
                'plugin_slug' => $batch_data['plugin_slug'],
                'review_count' => count($batch_data['results']['reviews']),
                'limited' => count($batch_data['results']['reviews']) > $limit,
                'showing' => count($reviews),
                'reviews' => $reviews,
            );
            
        case 'support':
            $tickets = array_slice($batch_data['results']['support'], 0, $limit);
            return array(
                'plugin_slug' => $batch_data['plugin_slug'],
                'ticket_count' => count($batch_data['results']['support']),
                'limited' => count($batch_data['results']['support']) > $limit,
                'showing' => count($tickets),
                'tickets' => $tickets,
            );
            
        case 'all':
        default:
            $reviews = array_slice($batch_data['results']['reviews'], 0, $limit);
            $tickets = array_slice($batch_data['results']['support'], 0, $limit);
            
            return array(
                'plugin_slug' => $batch_data['plugin_slug'],
                'details' => $batch_data['results']['details'],
                'reviews' => array(
                    'review_count' => count($batch_data['results']['reviews']),
                    'limited' => count($batch_data['results']['reviews']) > $limit,
                    'showing' => count($reviews),
                    'reviews' => $reviews,
                ),
                'support' => array(
                    'ticket_count' => count($batch_data['results']['support']),
                    'limited' => count($batch_data['results']['support']) > $limit,
                    'showing' => count($tickets),
                    'tickets' => $tickets,
                ),
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
     * Cancel batch
     */
    public function cancel_batch($request) {
        $batch_id = $request->get_param('batch_id');
        $batch_data = get_option(self::OPTION_PREFIX . $batch_id);
        
        if (!$batch_data) {
            return new WP_Error('batch_not_found', 'Batch not found', array('status' => 404));
        }
        
        $batch_data['status'] = 'cancelled';
        $batch_data['updated'] = current_time('mysql');
        update_option(self::OPTION_PREFIX . $batch_id, $batch_data, false);
        
        return rest_ensure_response(array(
            'batch_id' => $batch_id,
            'status' => 'cancelled',
            'message' => 'Batch cancelled successfully',
        ));
    }
    
    /**
     * Cleanup old batch data
     */
    public function cleanup_old_batch_data() {
        global $wpdb;
        
        // Delete batch data older than 7 days
        $old_date = date('Y-m-d H:i:s', strtotime('-7 days'));
        
        $options = $wpdb->get_col(
            $wpdb->prepare(
                "SELECT option_name FROM $wpdb->options 
                WHERE option_name LIKE %s",
                $wpdb->esc_like(self::OPTION_PREFIX) . '%'
            )
        );
        
        foreach ($options as $option_name) {
            $batch_data = get_option($option_name);
            if ($batch_data && isset($batch_data['created']) && $batch_data['created'] < $old_date) {
                delete_option($option_name);
            }
        }
    }
    
    /**
     * Add admin menu
     */
    public function add_admin_menu() {
        add_management_page(
            __('Plugin Data Fetcher', 'wp-plugin-data-fetcher'),
            __('Plugin Data Fetcher', 'wp-plugin-data-fetcher'),
            'manage_options',
            'wp-plugin-data-fetcher',
            array($this, 'admin_page')
        );
    }
    
    /**
     * Plugin activation
     */
    public function plugin_activation() {
        // Create cache directory
        $upload_dir = wp_upload_dir();
        $cache_dir = $upload_dir['basedir'] . '/wp-plugin-data-fetcher';
        
        if (!file_exists($cache_dir)) {
            wp_mkdir_p($cache_dir);
        }
        
        // Create .htaccess file to protect direct access
        $htaccess_file = $cache_dir . '/.htaccess';
        if (!file_exists($htaccess_file)) {
            $htaccess_content = "Order deny,allow\nDeny from all";
            file_put_contents($htaccess_file, $htaccess_content);
        }
        
        // Create empty index.php
        $index_file = $cache_dir . '/index.php';
        if (!file_exists($index_file)) {
            file_put_contents($index_file, '<?php // Silence is golden');
        }
    }
    
    /**
     * Register scripts and styles
     */
    public function register_scripts($hook) {
        if ('tools_page_wp-plugin-data-fetcher' !== $hook) {
            return;
        }
        
        wp_enqueue_style(
            'wp-plugin-data-fetcher-style',
            plugin_dir_url(__FILE__) . 'assets/css/admin.css',
            array(),
            '4.0.0'
        );
        
        wp_enqueue_script(
            'wp-plugin-data-fetcher-script',
            plugin_dir_url(__FILE__) . 'assets/js/admin.js',
            array('jquery'),
            '4.0.0',
            true
        );
        
        wp_localize_script(
            'wp-plugin-data-fetcher-script',
            'wpPluginDataFetcher',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'rest_url' => esc_url_raw(rest_url(self::REST_NAMESPACE)),
                'nonce' => wp_create_nonce('wp_rest'),
            )
        );
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

// Initialize the plugin
new WP_Plugin_Data_Fetcher();