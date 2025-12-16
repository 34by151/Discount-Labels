<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'WCCS_Background_Process', false ) ) {
	include_once WCCS_PLUGIN_DIR . 'includes/abstracts/class-wccs-background-process.php';
}

/**
 * WCCS_Background_Price_Updater Class.
 */
class WCCS_Background_Price_Updater extends WCCS_Background_Process {

    /**
	 * Stores the product ID being processed.
	 *
	 * @var integer
	 */
    protected $product_id = 0;

	/**
	 * Initiate new background process.
	 */
	public function __construct() {
		// Uses unique prefix per blog so each blog has separate queue.
		$this->prefix = 'wp_' . get_current_blog_id();
		$this->action = 'wccs_price_updater';

		parent::__construct();
	}

	/**
	 * Dispatch updater.
	 *
	 * Updater will still run via cron job if this fails for any reason.
	 */
	public function dispatch() {
		$dispatched = parent::dispatch();
		$logger     = WCCS_Helpers::wc_get_logger();

		if ( is_wp_error( $dispatched ) ) {
			if ( WCCS_Helpers::wc_version_check() ) {
				$logger->error(
					sprintf( __( 'Unable to dispatch WooCommerce Conditions price updater: %s', 'easy-woocommerce-discounts' ), $dispatched->get_error_message() ),
					array( 'source' => 'wccs_price_updater' )
				);
			} else {
				$logger->add( 'wccs_price_updater', sprintf( __( 'Unable to dispatch WooCommerce Conditions price updater: %s', 'easy-woocommerce-discounts' ), $dispatched->get_error_message() ) );
			}
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
	 * @param string $callback Update callback function
	 * @return mixed
	 */
	protected function task( $item ) {
		if ( ! is_array( $item ) || ! isset( $item['product_id'] ) ) {
            return false;
        }

        $this->product_id = absint( $item['product_id'] );
        $product          = wc_get_product( $this->product_id );

        if ( ! $product ) {
            return false;
        }

        $product->get_price_html();

		return false;
	}

	/**
	 * Complete
	 *
	 * Override if applicable, but ensure that the below actions are
	 * performed, or, call parent::complete().
	 */
	protected function complete() {
		$logger = WCCS_Helpers::wc_get_logger();
		if ( WCCS_Helpers::wc_version_check() ) {
			$logger->info( __( 'Completed product price update job.', 'easy-woocommerce-discounts' ), array( 'source' => 'wccs_price_updater' ) );
		} else {
			$logger->add( 'wccs_price_updater', __( 'Completed product price update job.', 'easy-woocommerce-discounts' ) );
		}
        parent::complete();
	}

}
