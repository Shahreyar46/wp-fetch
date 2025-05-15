<?php
namespace WPPluginDataFetcher\DataFetcher;

use WP_Error;
use DOMDocument;
use DOMXPath;

/**
 * WordPress.org Data Fetcher Class
 *
 * This class contains all the methods for fetching plugin data,
 * reviews, and support tickets from WordPress.org
 */
class WordPressDataFetcher {

	/**
	 * Fetches reviews from WordPress.org plugin directory
	 *
	 * @param string $plugin_slug The slug of the plugin
	 * @param int $limit Number of reviews to fetch (default: 40)
	 * @param bool $debug Enable debug output (default: false)
	 * @return array|WP_Error Array of reviews or WP_Error on failure
	 */
	public function get_wordpress_plugin_reviews($plugin_slug, $limit = 40, $debug = false) {
		if (empty($plugin_slug)) {
			return new WP_Error('invalid_plugin', 'Plugin slug is required');
		}

		// Increase memory limit for large requests
		if ($limit > 500) {
			ini_set('memory_limit', '512M');
		}

		$all_reviews = array();
		$page = 1;
		$max_pages = ceil($limit / 30) + 5; // Add buffer pages

		while (count($all_reviews) < $limit && $page <= $max_pages) {
			// Construct URL.
			if ($page === 1) {
				$url = sprintf('https://wordpress.org/support/plugin/%s/reviews/', sanitize_title($plugin_slug));
			} else {
				$url = sprintf('https://wordpress.org/support/plugin/%s/reviews/page/%d/', sanitize_title($plugin_slug), $page);
			}

			if ($debug) {
				error_log("Fetching URL: " . $url);
			}

			$args = array(
				'timeout' => 60, // Increased timeout
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
				'sslverify' => false, // Disable SSL verification for speed
			);

			$response = wp_remote_get($url, $args);

			if (is_wp_error($response)) {
				if ($page === 1) {
					return $response;
				}
				break;
			}

			$body = wp_remote_retrieve_body($response);

			if (empty($body)) {
				break;
			}

			// Parse reviews from the HTML
			$reviews = $this->parse_wordpress_reviews_html($body, $debug);

			if ($debug) {
				error_log("Found " . count($reviews) . " reviews on page " . $page);
			}

			if (empty($reviews)) {
				break;
			}

			$all_reviews = array_merge($all_reviews, $reviews);

			// Check if we have all reviews or should continue.
			if (count($reviews) < 30 || count($all_reviews) >= $limit) {
				break;
			}

			$page++;

			// Small delay to be respectful, but faster for large requests.
			if ($limit < 500) {
				sleep(1);
			} else {
				usleep(500000); // 0.5 seconds for large requests.
			}
		}

		// Trim to requested limit.
		if (count($all_reviews) > $limit) {
			$all_reviews = array_slice($all_reviews, 0, $limit);
		}

		return array(
			'plugin_slug' => $plugin_slug,
			'review_count' => count($all_reviews),
			'reviews' => $all_reviews,
		);
	}

	/**
	 * Parse WordPress.org reviews HTML based on actual structure
	 *
	 * @param string $html The HTML content to parse
	 * @param bool $debug Enable debug output
	 * @return array Array of parsed reviews
	 */
	public function parse_wordpress_reviews_html($html, $debug = false) {
		$reviews = array();

		// Create a DOMDocument instance.
		$dom = new DOMDocument();

		// Suppress HTML parsing warnings.
		libxml_use_internal_errors(true);

		// Load the HTML.
		if (!$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'))) {
			libxml_clear_errors();
			return $reviews;
		}

		libxml_clear_errors();

		// Create DOMXPath instance.
		$xpath = new DOMXPath($dom);

		// Find all review topic items in the correct structure
		$review_items = $xpath->query('//ul[@class="bbp-topics"]//li[@class="bbp-body"]//ul[contains(@id, "bbp-topic-")]');

		if ($debug) {
			error_log("Found " . $review_items->length . " review items");
		}

		foreach ($review_items as $item) {
			$review = array();

			// Get review ID from the ul's id attribute
			if ($item->hasAttribute('id')) {
				$review['id'] = $item->getAttribute('id');
			}

			// Get the topic title link.
			$title_link = $xpath->query('.//a[@class="bbp-topic-permalink"]', $item);
			if ($title_link->length > 0) {
				$title_node = $title_link->item(0);

				// Extract title text (without the rating div).
				$title_text = '';
				foreach ($title_node->childNodes as $child) {
					if ($child->nodeType === XML_TEXT_NODE) {
						$title_text .= $child->nodeValue;
					}
				}
				$review['title'] = trim($title_text);

				// Get the review URL.
				$review['url'] = $title_node->getAttribute('href');

				// Extract rating from the wporg-ratings div inside the title link.
				$rating_div = $xpath->query('.//div[@class="wporg-ratings"]', $title_node);
				if ($rating_div->length > 0) {
					$rating_title = $rating_div->item(0)->getAttribute('title');
					if (preg_match('/(\d+) out of 5 stars/', $rating_title, $matches)) {
						$review['rating'] = intval($matches[1]);
					}
				}
			}

			// Get author information.
			$author_span = $xpath->query('.//span[@class="bbp-topic-started-by"]//a', $item);
			if ($author_span->length > 0) {
				$author_link = $author_span->item(0);
				$review['author'] = array(
					'name' => trim($author_link->textContent),
					'url' => $author_link->getAttribute('href')
				);
			}

			// Get freshness/date information.
			$freshness_li = $xpath->query('.//li[@class="bbp-topic-freshness"]//a', $item);
			if ($freshness_li->length > 0) {
				$review['date_formatted'] = trim($freshness_li->item(0)->textContent);

				// Try to get the title attribute which often has the full date.
				$title_attr = $freshness_li->item(0)->getAttribute('title');
				if (!empty($title_attr)) {
					$review['date'] = $title_attr;
				}
			}

			// Get participant and reply counts.
			$voice_count = $xpath->query('.//li[@class="bbp-topic-voice-count"]', $item);
			if ($voice_count->length > 0) {
				$review['participants'] = intval(trim($voice_count->item(0)->textContent));
			}

			$reply_count = $xpath->query('.//li[@class="bbp-topic-reply-count"]', $item);
			if ($reply_count->length > 0) {
				$review['replies'] = intval(trim($reply_count->item(0)->textContent));
			}

			// Don't set description here - it will be fetched later if needed.

			// Only add review if it has a title.
			if (!empty($review['title'])) {
				$reviews[] = $review;

				if ($debug) {
					error_log("Parsed review: " . $review['title'] . " (Rating: " . ($review['rating'] ?? 'N/A') . ")");
				}
			}
		}

		return $reviews;
	}

	/**
	 * Optimized method to fetch review content with timeout handling
	 * 
	 * @param string $review_url The review URL
	 * @return string The review content/description
	 */
	public function fetch_review_content($review_url) {
		$response = wp_remote_get($review_url, array(
			'timeout' => 15, // Reduced timeout for faster processing
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
			'sslverify' => false,
			'compress' => true, // Enable compression for faster transfers.
		));

		if (is_wp_error($response)) {
			return '';
		}

		$body = wp_remote_retrieve_body($response);

		// Quick content extraction without full DOM parsing.
		if (preg_match('/<div class="bbp-topic-content">(.*?)<\/div>/s', $body, $matches)) {
			$content = strip_tags($matches[1]);
			return trim(preg_replace('/\s+/', ' ', $content));
		}

		// Fallback to DOM parsing if regex fails.
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML(mb_convert_encoding($body, 'HTML-ENTITIES', 'UTF-8'));
		libxml_clear_errors();

		$xpath = new DOMXPath($dom);
		$content_nodes = $xpath->query('//div[@class="bbp-topic-content"]');

		if ($content_nodes->length > 0) {
			$content = $content_nodes->item(0)->textContent;
			return trim(preg_replace('/\s+/', ' ', $content));
		}

		return '';
	}

	/**
	 * Get reviews with full content using chunked processing for large requests
	 * 
	 * @param string $plugin_slug The plugin slug
	 * @param int $limit Number of reviews to fetch
	 * @param bool $fetch_content Whether to fetch full content by visiting each review
	 * @return array Array of reviews
	 */
	public function get_plugin_reviews_with_chunked_content($plugin_slug, $limit = 40, $fetch_content = false) {
		$reviews_data = $this->get_wordpress_plugin_reviews($plugin_slug, $limit);

		if (is_wp_error($reviews_data)) {
			return $reviews_data;
		}

		if ($fetch_content && !empty($reviews_data['reviews'])) {
			$total_reviews = count($reviews_data['reviews']);
			$chunk_size = 50; // Process 50 reviews at a time
			$processed = 0;

			foreach (array_chunk($reviews_data['reviews'], $chunk_size) as $chunk_index => $review_chunk) {
				foreach ($review_chunk as $index => &$review) {
					if (!empty($review['url'])) {
						// Check execution time periodically
						if ($processed % 10 === 0 && function_exists('set_time_limit')) {
							set_time_limit(60); // Reset timeout for each batch
						}

						$content = $this->fetch_review_content($review['url']);
						if (!empty($content)) {
							$review['description'] = $content;
						}

						$processed++;

						// Progress tracking
						if ($processed % 10 === 0) {
							error_log("Fetched content for $processed/$total_reviews reviews...");
						}

						// Dynamic delay based on request size.
						if ($limit < 100) {
							usleep(500000); // 0.5 seconds for small requests.
						} else {
							usleep(200000); // 0.2 seconds for large requests.
						}
					}
				}

				// Update the reviews in the main array.
				array_splice($reviews_data['reviews'], $chunk_index * $chunk_size, count($review_chunk), $review_chunk);
			}
		}

		//error_log(json_encode($reviews_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

		return $reviews_data;
	}

	/**
	 * Fetches support tickets from WordPress.org plugin support forum
	 * 
	 * @param string $plugin_slug The slug of the plugin
	 * @param int $limit Number of tickets to fetch (default: 30)
	 * @param bool $debug Enable debug output (default: false)
	 * @return array|WP_Error Array of tickets or WP_Error on failure
	 */
	public function get_wordpress_plugin_support_tickets($plugin_slug, $limit = 30, $debug = false) {
		if (empty($plugin_slug)) {
			return new WP_Error('invalid_plugin', 'Plugin slug is required');
		}

		// Increase memory limit for large requests.
		if ($limit > 500) {
			ini_set('memory_limit', '512M');
		}

		$all_tickets = array();
		$page = 1;
		$max_pages = ceil($limit / 30) + 5; // Add buffer pages

		while (count($all_tickets) < $limit && $page <= $max_pages) {
			// Construct URL for support forum
			if ($page === 1) {
				$url = sprintf('https://wordpress.org/support/plugin/%s/', sanitize_title($plugin_slug));
			} else {
				$url = sprintf('https://wordpress.org/support/plugin/%s/page/%d/', sanitize_title($plugin_slug), $page);
			}

			if ($debug) {
				error_log("Fetching support tickets from: " . $url);
			}

			$args = array(
				'timeout' => 60,
				'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
				'sslverify' => false,
			);

			$response = wp_remote_get($url, $args);

			if (is_wp_error($response)) {
				if ($page === 1) {
					return $response;
				}
				break;
			}

			$body = wp_remote_retrieve_body($response);

			if (empty($body)) {
				break;
			}

			// Parse tickets from the HTML.
			$tickets = $this->parse_wordpress_support_tickets_html($body, $debug);

			if ($debug) {
				error_log("Found " . count($tickets) . " tickets on page " . $page);
			}

			if (empty($tickets)) {
				break;
			}

			$all_tickets = array_merge($all_tickets, $tickets);

			// Check if we have all tickets or should continue.
			if (count($tickets) < 30 || count($all_tickets) >= $limit) {
				break;
			}

			$page++;

			// Dynamic delay based on request size.
			if ($limit < 500) {
				sleep(1);
			} else {
				usleep(500000); // 0.5 seconds for large requests.
			}
		}

		// Trim to requested limit.
		if (count($all_tickets) > $limit) {
			$all_tickets = array_slice($all_tickets, 0, $limit);
		}

		return array(
			'plugin_slug' => $plugin_slug,
			'ticket_count' => count($all_tickets),
			'tickets' => $all_tickets,
		);
	}

	/**
	 * Parse WordPress.org support tickets HTML
	 * 
	 * @param string $html The HTML content to parse
	 * @param bool $debug Enable debug output
	 * @return array Array of parsed tickets
	 */
	public function parse_wordpress_support_tickets_html($html, $debug = false) {
		$tickets = array();

		// Create a DOMDocument instance.
		$dom = new DOMDocument();

		// Suppress HTML parsing warnings.
		libxml_use_internal_errors(true);

		// Load the HTML.
		if (!$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'))) {
			libxml_clear_errors();
			return $tickets;
		}

		libxml_clear_errors();

		// Create DOMXPath instance.
		$xpath = new DOMXPath($dom);

		// Find all ticket items in the correct structure
		$ticket_items = $xpath->query('//ul[@class="bbp-topics"]//li[@class="bbp-body"]//ul[contains(@id, "bbp-topic-")]');

		if ($debug) {
			error_log("Found " . $ticket_items->length . " ticket items");
		}

		foreach ($ticket_items as $item) {
			$ticket = array();
			
			// Get ticket ID from the ul's id attribute
			if ($item->hasAttribute('id')) {
				$ticket['id'] = $item->getAttribute('id');
				// Extract numeric ID
				if (preg_match('/bbp-topic-(\d+)/', $ticket['id'], $matches)) {
					$ticket['topic_id'] = $matches[1];
				}
			}
			
			// Get the topic title link
			$title_link = $xpath->query('.//a[@class="bbp-topic-permalink"]', $item);
			if ($title_link->length > 0) {
				$title_node = $title_link->item(0);
				$ticket['title'] = trim($title_node->textContent);
				$ticket['url'] = $title_node->getAttribute('href');
				
				// Check if topic is resolved
				$resolved_span = $xpath->query('.//span[@class="resolved"]', $title_node);
				$ticket['resolved'] = $resolved_span->length > 0;
			}
			
			// Get author information
			$author_span = $xpath->query('.//span[@class="bbp-topic-started-by"]//a', $item);
			if ($author_span->length > 0) {
				$author_link = $author_span->item(0);
				$ticket['author'] = array(
					'name' => trim($author_link->textContent),
					'url' => $author_link->getAttribute('href')
				);
			}
			
			// Get freshness/date information
			$freshness_li = $xpath->query('.//li[@class="bbp-topic-freshness"]//a', $item);
			if ($freshness_li->length > 0) {
				$ticket['last_activity'] = trim($freshness_li->item(0)->textContent);
				
				// Try to get the title attribute which often has the full date
				$title_attr = $freshness_li->item(0)->getAttribute('title');
				if (!empty($title_attr)) {
					$ticket['last_activity_date'] = $title_attr;
				}
			}
			
			// Get last responder
			$freshness_author = $xpath->query('.//span[@class="bbp-topic-freshness-author"]//a', $item);
			if ($freshness_author->length > 0) {
				$ticket['last_responder'] = array(
					'name' => trim($freshness_author->item(0)->textContent),
					'url' => $freshness_author->item(0)->getAttribute('href')
				);
			}
			
			// Get participant and reply counts
			$voice_count = $xpath->query('.//li[@class="bbp-topic-voice-count"]', $item);
			if ($voice_count->length > 0) {
				$ticket['participants'] = intval(trim($voice_count->item(0)->textContent));
			}
			
			$reply_count = $xpath->query('.//li[@class="bbp-topic-reply-count"]', $item);
			if ($reply_count->length > 0) {
				$ticket['replies'] = intval(trim($reply_count->item(0)->textContent));
			}
			
			// Only add ticket if it has a title
			if (!empty($ticket['title'])) {
				$tickets[] = $ticket;
				
				if ($debug) {
					error_log("Parsed ticket: " . $ticket['title'] . " (Resolved: " . ($ticket['resolved'] ? 'Yes' : 'No') . ")");
				}
			}
		}
		
		return $tickets;
	}
	
	/**
	 * Fetch ticket content by visiting individual ticket pages
	 * 
	 * @param string $ticket_url The ticket URL
	 * @return array The ticket content including replies
	 */
	public function fetch_ticket_content($ticket_url) {
		$response = wp_remote_get($ticket_url, array(
			'timeout' => 30,
			'user-agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
			'sslverify' => false,
		));
		
		if (is_wp_error($response)) {
			return array();
		}
		
		$body = wp_remote_retrieve_body($response);
		
		// Parse the ticket page to get the actual content
		$dom = new DOMDocument();
		libxml_use_internal_errors(true);
		$dom->loadHTML(mb_convert_encoding($body, 'HTML-ENTITIES', 'UTF-8'));
		libxml_clear_errors();
		
		$xpath = new DOMXPath($dom);
		
		$content_data = array(
			'replies' => array()
		);
		
		// Get the initial topic content
		$topic_content = $xpath->query('//div[@class="bbp-topic-content"]');
		if ($topic_content->length > 0) {
			$content_data['initial_content'] = trim($topic_content->item(0)->textContent);
		}
		
		// Get all replies
		$reply_containers = $xpath->query('//li[contains(@class, "bbp-reply")]');
		foreach ($reply_containers as $reply) {
			$reply_data = array();
			
			// Get reply content
			$reply_content = $xpath->query('.//div[@class="bbp-reply-content"]', $reply);
			if ($reply_content->length > 0) {
				$reply_data['content'] = trim($reply_content->item(0)->textContent);
			}
			
			// Get reply author
			$reply_author = $xpath->query('.//a[@class="bbp-author-name"]', $reply);
			if ($reply_author->length > 0) {
				$reply_data['author'] = trim($reply_author->item(0)->textContent);
			}
			
			// Get reply date
			$reply_date = $xpath->query('.//a[@class="bbp-reply-post-date"]', $reply);
			if ($reply_date->length > 0) {
				$reply_data['date'] = trim($reply_date->item(0)->textContent);
			}
			
			if (!empty($reply_data['content'])) {
				$content_data['replies'][] = $reply_data;
			}
		}
		
		return $content_data;
	}
	
	/**
	 * Get support tickets with full content using chunked processing for large requests
	 * 
	 * @param string $plugin_slug The plugin slug
	 * @param int $limit Number of tickets to fetch
	 * @param bool $fetch_content Whether to fetch full content by visiting each ticket
	 * @return array Array of tickets
	 */
	public function get_plugin_support_tickets_with_chunked_content($plugin_slug, $limit = 30, $fetch_content = false) {
		$tickets_data = $this->get_wordpress_plugin_support_tickets($plugin_slug, $limit);
		
		if (is_wp_error($tickets_data)) {
			return $tickets_data;
		}
		
		if ($fetch_content && !empty($tickets_data['tickets'])) {
			$total_tickets = count($tickets_data['tickets']);
			$chunk_size = 50; // Process 50 tickets at a time
			$processed = 0;
			
			foreach (array_chunk($tickets_data['tickets'], $chunk_size) as $chunk_index => $ticket_chunk) {
				foreach ($ticket_chunk as $index => &$ticket) {
					if (!empty($ticket['url'])) {
						// Check execution time periodically
						if ($processed % 10 === 0 && function_exists('set_time_limit')) {
							set_time_limit(60); // Reset timeout for each batch
						}
						
						$content = $this->fetch_ticket_content($ticket['url']);
						if (!empty($content)) {
							$ticket = array_merge($ticket, $content);
						}
						
						$processed++;
						
						// Progress tracking
						if ($processed % 10 === 0) {
							error_log("Fetched content for $processed/$total_tickets tickets...");
						}
						
						// Dynamic delay based on request size
						if ($limit < 100) {
							usleep(500000); // 0.5 seconds for small requests
						} else {
							usleep(200000); // 0.2 seconds for large requests
						}
					}
				}
				
				// Update the tickets in the main array
				array_splice($tickets_data['tickets'], $chunk_index * $chunk_size, count($ticket_chunk), $ticket_chunk);
			}
		}
		//error_log(json_encode($tickets_data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
		
		return $tickets_data;

		 
	}
}