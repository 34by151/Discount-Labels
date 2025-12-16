<?php

defined( 'ABSPATH' ) || exit;

if ( ! class_exists( '\WC_Background_Process', false ) ) {
	include_once WC_ABSPATH . 'includes/abstracts/class-wc-background-process.php';
}

class WCCS_Background_Analytics extends WC_Background_Process {

	protected $analytics;

	/**
	 * Initiate new background process.
	 */
	public function __construct( $analytics = null ) {
		// Uses unique prefix per blog so each blog has separate queue.
		$this->prefix = 'wp_' . get_current_blog_id();
		$this->action = 'asnp_wccs_analytics';
		$this->analytics = null !== $analytics ? $analytics : WCCS()->container()->get( WCCS_DB_Analytics::class);

		parent::__construct();
	}

	/**
	 * Dispatch updater.
	 *
	 * Updater will still run via cron job if this fails for any reason.
	 */
	public function dispatch() {
		$dispatched = parent::dispatch();
		$logger = wc_get_logger();

		if ( is_wp_error( $dispatched ) ) {
			$logger->error(
				sprintf( 'Unable to dispatch WooCommerce Discount Analytics: %s', $dispatched->get_error_message() ),
				array( 'source' => 'asnp_wccs_analytics' )
			);
		}
	}

	/**
	 * Handle cron healthcheck
	 *
	 * Restart the background process if not already running
	 * and data exists in the queue.
	 */
	public function handle_cron_healthcheck() {
		if ( $this->is_process_running() ) {
			// Background process already running.
			return;
		}

		if ( $this->is_queue_empty() ) {
			// No data to process.
			$this->clear_scheduled_event();
			return;
		}

		$this->handle();
	}

	/**
	 * Schedule fallback event.
	 */
	protected function schedule_event() {
		if ( ! wp_next_scheduled( $this->cron_hook_identifier ) ) {
			wp_schedule_event( time() + 10, $this->cron_interval_identifier, $this->cron_hook_identifier );
		}
	}

	/**
	 * Is the updater running?
	 *
	 * @return boolean
	 */
	public function is_updating() {
		return false === $this->is_queue_empty();
	}

	/**
	 * Task
	 *
	 * Override this method to perform any actions required on each
	 * queue item. Return the modified item for further processing
	 * in the next pass through. Or, return false to remove the
	 * item from the queue.
	 *
	 * @param WC_Product|int $product Product object or ID for which you wish to sync.
	 * @return string|bool
	 */
	protected function task( $item ) {
		if ( empty( $item['product'] ) || 0 >= (int) $item['product'] ) {
			return false;
		}

		$product = wc_get_product( (int) $item['product'] );
		if ( ! $product ) {
			return false;
		}

		$this->impression( $product );

		return false;
	}

	public function impression( $product ) {
		$product = is_numeric( $product ) ? wc_get_product( $product ) : $product;
		if ( ! $product ) {
			return;
		}

		$impression = [];
		$this->count_impression( $product, $impression );
		if ( $product->is_type( 'variable' ) ) {
			$variations = $product->get_available_variations( 'objects' );
			if ( ! empty( $variations ) ) {
				foreach ( $variations as $variation ) {
					$this->count_impression( $variation, $impression );
				}
			}
		}

		if ( ! empty( $impression ) ) {
			$this->analytics->log_events( 'impression', $impression );
		}
	}

	protected function count_impression( $product, &$impression ) {
		$product_id = $product->is_type( 'variation' ) ? $product->get_parent_id() : $product->get_id();
		if ( 0 >= $product_id ) {
			return;
		}

		$variation_id = $product->is_type( 'variation' ) ? $product->get_id() : 0;
		$attributes = $product->is_type( 'variation' ) ? WCCS()->WCCS_Attribute_Helpers->get_product_attributes( $product_id ) : [];

		$rules = WCCS()->pricing->get_pricings();
		if ( empty( $rules ) ) {
			return;
		}

		$exclude_rules = WCCS()->pricing->get_exclude_rules();
		if ( ! empty( $exclude_rules ) ) {
			if ( WCCS()->pricing->is_in_exclude_rules( $product_id, $variation_id, $attributes ) ) {
				return;
			}
		}

		foreach ( $rules as $type => $pricings ) {
			foreach ( $pricings as $pricing ) {
				if ( isset( $impression[ (int) $pricing['id'] ] ) ) {
					continue;
				}

				if ( 'products_group' === $pricing['mode'] ) {
					if ( ! empty( $pricing['groups'] ) ) {
						foreach ( $pricing['groups'] as $group ) {
							if ( ! empty( $group['items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $group['items'], $product_id, $variation_id, $attributes ) ) {
								$impression[ (int) $pricing['id'] ] = 1;
								break;
							}
						}
					}
				} elseif ( ! empty( $pricing['items'] ) && WCCS()->WCCS_Product_Validator->is_valid_product( $pricing['items'], $product_id, $variation_id, $attributes ) ) {
					$impression[ (int) $pricing['id'] ] = 1;
				}
			}
		}
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		$logger = wc_get_logger();
		$logger->info( 'Product impression update complete', array( 'source' => 'asnp_wccs_background_analytics' ) );
		parent::complete();
	}

	/**
	 * See if the batch limit has been exceeded.
	 *
	 * @return bool
	 */
	public function is_memory_exceeded() {
		return $this->memory_exceeded();
	}

}
