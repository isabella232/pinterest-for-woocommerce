<?php
/**
 * Pinterest For WooCommerce Catalog Syncing
 *
 * @package     Pinterest_For_WooCommerce/Classes/
 * @version     1.0.0
 */

namespace Automattic\WooCommerce\Pinterest;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Handling registration & generation of the XML product feed.
 */
class ProductSync {

	const ACTION_HANDLE_SYNC     = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-handle-sync';
	const ACTION_FEED_GENERATION = PINTEREST_FOR_WOOCOMMERCE_PREFIX . '-feed-generation';

	/**
	 * The number of products to process on each Iteration of the scheduled task.
	 * A high number increases the time it takes to complete the iteration, risking a PHP timeout.
	 *
	 * @var integer
	 */
	private static $products_per_step = 500;

	/**
	 * The number of products to hold in a buffer variable before writing their XML output to a file.
	 * A high number increases memory usage while a low number increases file writes.
	 *
	 * @var integer
	 */
	private static $products_per_write = 100;

	/**
	 * Initiate class.
	 */
	public static function maybe_init() {

		// Schedule required actions.
		add_action( self::ACTION_HANDLE_SYNC, array( __CLASS__, 'handle_feed_registration' ) );
		add_action( self::ACTION_FEED_GENERATION, array( __CLASS__, 'handle_feed_generation' ) );

		// Check if enabled.
		if ( self::is_product_sync_enabled() ) {

			if ( false === as_next_scheduled_action( self::ACTION_HANDLE_SYNC, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
				$interval = 10 * MINUTE_IN_SECONDS;

				as_schedule_recurring_action( time() + $interval, $interval, self::ACTION_HANDLE_SYNC, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
			}

			$state = self::feed_job_status();

			if ( $state && 'scheduled_for_generation' === $state['status'] && false === as_next_scheduled_action( self::ACTION_FEED_GENERATION, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX ) ) {
				as_enqueue_async_action( self::ACTION_FEED_GENERATION, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
			}
		}
	}


	/**
	 * Deletes the XML file that is configured in the settings and deletes the feed_job option.
	 *
	 * @return void
	 */
	public function feed_reset() {

		$state = Pinterest_For_Woocommerce()::get_setting( 'feed_job' );

		if ( file_exists( $state['feed_file'] ) ) {
			unlink( $state['feed_file'] );
		}

		Pinterest_For_Woocommerce()::save_setting( 'feed_job', false );
	}


	/**
	 * Schedules the regeneration process of the XML feed.
	 *
	 * @return void
	 */
	public static function feed_reschedule() {

		$feed_job           = Pinterest_For_Woocommerce()::get_setting( 'feed_job' );
		$feed_job           = $feed_job ? $feed_job : array();
		$feed_job['status'] = 'scheduled_for_generation';

		Pinterest_For_Woocommerce()::save_setting( 'feed_job', $feed_job );
	}


	/**
	 * Logs Sync related messages separately.
	 *
	 * @param string $message The message to be logged.
	 * @param string $level   The level of the message.
	 *
	 * @return void
	 */
	private static function log( $message, $level = 'debug' ) {
		Logger::log( $message, $level, 'product-sync' );
	}


	/**
	 * Checks if the feature is enabled, and all requirements are met.
	 *
	 * @return boolean
	 */
	private static function is_product_sync_enabled() {
		return true;
	}


	/**
	 * Check if the feed is registered based on the plugin's settings.
	 * If not, try to register it,
	 * Log issues.
	 * Potentially report issues.
	 *
	 * Should be run on demand when settings change,
	 * and on a scheduled basis.
	 *
	 * @return mixed
	 */
	public static function handle_feed_registration() {

		if ( ! self::is_product_sync_enabled() ) {
			// TODO: Remove registered feed from API
			return false;
		}

		$state = self::feed_job_status( 'check_registration' );

		$feed_args = array(
			'feed_location'                     => $state['feed_url'],
			'feed_format'                       => 'XML',
			'feed_default_currency'             => get_woocommerce_currency(),
			'default_availability_type'         => 'IN_STOCK', // TODO: ? also check with wrong value and check why its not getting logged properly in debug.log
			'return_merchant_if_already_exists' => true,
		);

		try {
			// Get merchant Obj from API.
			$merchant = API\Base::maybe_create_merchant( $feed_args );

			if ( 'success' !== $merchant['status'] ) {
				throw new \Exception( __( 'Error' ) ); // TODO:
			}

			$registered = false;

			if ( ! isset( $merchant['data']->product_pin_feed_profile->location_config->full_feed_fetch_location ) ) {
				// No feed registered.

				$merchant   = API\Base::maybe_create_merchant( $feed_args ); // TODO: test (case where merchant exists, no feed registered)
				$registered = $merchant && 'success' === $merchant['status'] && isset( $merchant['data']->product_pin_feed_profile->location_config->full_feed_fetch_location );

			} elseif ( $feed_args['feed_location'] === $merchant['data']->product_pin_feed_profile->location_config->full_feed_fetch_location ) {
				// Feed registered.
				$registered = true;
			} else {
				// A diff feed was registered. Update to the current one.
				$feed       = API\Base::update_merchant_feed( $merchant['data']->product_pin_feed_profile->merchant_id, $merchant['data']->product_pin_feed_profile->id, $feed_args );
				$registered = $feed && 'success' === $feed['status'] && isset( $feed['data']->location_config->full_feed_fetch_location );
			}

			if ( $registered ) {
				// If local is not generated, or is older than X , schedule regeneration.
				if ( 'starting' === $state['status'] ||
					'in_progress' === $state['status'] ||
					( 'generated' === $state['status'] && $state['finished'] < ( time() - DAY_IN_SECONDS ) )
				) {
					// Do nothing
				} else {
					self::feed_reschedule();
				}

				return true;
			}

			// TODO: handle error.
			return $registered;

		} catch ( \Throwable $th ) {
			// throw $th;
		}

	}


	/**
	 * Gets the Array of IDs for the products that are to be printed to the
	 * XML feed, based on the current settings.
	 *
	 * @return array
	 */
	private static function get_product_ids_for_feed() {
		return wc_get_products(
			array(
				'limit'  => -1,
				'return' => 'ids',
			)
		);
	}


	/**
	 * Handles the feed's generation,
	 * Using AS scheduled tasks prints $products_per_step number of products
	 * on each iteration and writes to a file every $products_per_write.
	 *
	 * @return void
	 */
	public static function handle_feed_generation() {

		$state = self::feed_job_status();
		$start = microtime( true );

		if ( $state && 'generated' === $state['status'] ) {
			return; // No need to perform any action.
		}

		if ( ! $state || ( $state && 'scheduled_for_generation' === $state['status'] ) ) {
			// We need to start a generation from scratch.
			$product_ids = self::get_product_ids_for_feed();

			if ( empty( $product_ids ) ) {
				// TODO: throw error?

				self::log( 'No products found for feed generation.' );

				return false;
			}

			$state = self::feed_job_status(
				'starting',
				array(
					'dataset'       => $product_ids,
					'current_index' => 0,
				)
			);

			$current_index = 0;

		}

		if ( 'in_progress' === $state['status'] ) {
			$product_ids   = get_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_dataset_' . $state['job_id'] );
			$current_index = get_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_current_index_' . $state['job_id'] );

			if ( false === $current_index || empty( $product_ids ) ) {
				// Something went wrong.
				return false;
			}

			$current_index = ( (int) $current_index ) + 1; // Start on the next item.
		}

		$xml_file = fopen( $state['feed_file'], ( 'in_progress' === $state['status'] ? 'a' : 'w' ) );

		if ( ! $xml_file ) {
			// TODO: throw error?
			return false;
		}

		self::log( 'Generating feed for ' . count( $product_ids ) . ' products' );

		if ( 0 === $current_index ) {
			// Write header.
			fwrite( $xml_file, ProductsXmlFeed::get_xml_header() );
		}

		$iteration_buffer      = '';
		$iteration_buffer_size = 0;
		$step_index            = 0;
		$products_count        = count( $product_ids );

		for ( $current_index; ( $current_index < $products_count ); $current_index++ ) {

			$product_id = $product_ids[ $current_index ];

			$iteration_buffer .= ProductsXmlFeed::get_xml_item( wc_get_product( $product_id ) );
			$iteration_buffer_size++;

			if ( $iteration_buffer_size >= self::$products_per_write || $current_index >= count( $product_ids ) ) {
				if ( false !== fwrite( $xml_file, $iteration_buffer ) ) {
					$iteration_buffer      = '';
					$iteration_buffer_size = 0;

					self::feed_job_status(
						'in_progress',
						array(
							'current_index' => $current_index,
						)
					);

				} else {
					// Could not write ? // TODO: error
				}
			}

			$step_index++;

			if ( $step_index >= self::$products_per_step ) {
				break;
			}
		}

		if ( ! empty( $iteration_buffer ) ) {
			if ( false !== fwrite( $xml_file, $iteration_buffer ) ) {
				$iteration_buffer      = '';
				$iteration_buffer_size = 0;

				self::feed_job_status(
					'in_progress',
					array(
						'current_index' => $current_index,
					)
				);

			} else {
				// Could not write ? // TODO: error
			}
		}

		if ( $current_index >= count( $product_ids ) ) {
			// Write footer.
			fwrite( $xml_file, ProductsXmlFeed::get_xml_footer() );

			self::feed_job_status( 'generated' );
		} else {
			// We got more products left. Schedule next iteration.
			as_enqueue_async_action( self::ACTION_FEED_GENERATION, array(), PINTEREST_FOR_WOOCOMMERCE_PREFIX );
		}

		fclose( $xml_file );

		$end = microtime( true );
		self::log( 'Feed step generation completed in ' . round( ( $end - $start ) * 1000 ) . 'ms. Current Index: ' . $current_index . ' / ' . count( $product_ids ) );
		self::log( 'Wrote ' . $step_index . ' products to file: ' . $state['feed_file'] );
	}


	/**
	 * Saves or returns the Current state of the Feed generation job.
	 * Status can be one of the following:
	 *
	 * - starting                 The feed job is being initialized. A new JobID will be assigned if none exists.
	 * - check_registration       If a JobID already exists, it is retutned, otherwise a new one will be assigned.
	 * - in_progress              Signifies that we are between iterations and generating the feed.
	 * - generated                The feed is generated, no further action will be taken.
	 * - scheduled_for_generation The feed needs to be (re)generated. If this status is set, the next run of __CLASS__::handle_feed_generation() will start the generation process.
	 *
	 * @param string $status The status of the feed's generation process. See above.
	 * @param array  $args   The arguments that go along with the given status.
	 *
	 * @return array
	 */
	private static function feed_job_status( $status = null, $args = null ) {

		// TODO: add/move logging here?

		$state_data = Pinterest_For_Woocommerce()::get_setting( 'feed_job' );

		if ( is_null( $status ) || ( ! is_null( $status ) && 'check_registration' === $status && ! empty( $state_data['job_id'] ) ) ) {
			return $state_data;
		}

		$state_data = empty( $state_data ) ? array() : $state_data;

		if ( 'starting' === $status || 'check_registration' === $status ) {
			$state_data['status']  = $status;
			$state_data['started'] = time();

			if ( empty( $state_data['job_id'] ) ) {
				$state_data['job_id'] = wp_generate_password( 6, false, false );
			}

			$upload_dir = wp_get_upload_dir();

			$state_data['feed_file'] = trailingslashit( $upload_dir['basedir'] ) . PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX . '-' . $state_data['job_id'] . '.xml';
			$state_data['feed_url']  = trailingslashit( $upload_dir['baseurl'] ) . PINTEREST_FOR_WOOCOMMERCE_LOG_PREFIX . '-' . $state_data['job_id'] . '.xml';

			if ( isset( $args, $args['dataset'] ) ) {
				set_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_dataset_' . $state_data['job_id'], $args['dataset'], DAY_IN_SECONDS );
			}
		} elseif ( 'in_progress' === $status ) {
			$state_data['status'] = 'in_progress';

			if ( isset( $args, $args['current_index'] ) ) {
				set_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_current_index_' . $state_data['job_id'], $args['current_index'], DAY_IN_SECONDS );
			}
		} elseif ( 'generated' === $status ) {
			$state_data['status']   = 'generated';
			$state_data['finished'] = time();

			// Cleanup.
			delete_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_dataset_' . $state_data['job_id'] );
			delete_transient( PINTEREST_FOR_WOOCOMMERCE_PREFIX . '_feed_current_index_' . $state_data['job_id'] );
		}

		Pinterest_For_Woocommerce()::save_setting( 'feed_job', $state_data );

		return $state_data;

	}

	private function clear_xml_feed() {
		// TODO: ?
	}
}