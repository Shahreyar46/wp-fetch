<?php
namespace WPPluginDataFetcher\Core;

/**
 * Plugin Constants
 */
class Constants {

	const REST_NAMESPACE = 'wp-plugin-fetcher/v1';
	const OPTION_PREFIX = 'wpdf_batch_';
	const BATCH_SIZE = 50; // Number of items to process per batch.
}