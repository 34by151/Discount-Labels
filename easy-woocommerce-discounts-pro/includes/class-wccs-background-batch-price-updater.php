<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Background_Batch_Price_Updater {

    /**
     * Background process to update products price.
     *
     * @var WCCS_Background_Price_Updater
     */
    protected $background_process;

    public function init() {
        if ( ! apply_filters( 'wccs_background_products_price_updates', true ) ) {
            return;
        }

        include_once dirname( __FILE__ ) . '/class-wccs-background-price-updater.php';

        if ( current_user_can( 'manage_woocommerce' ) ) {
            add_action( 'admin_init', array( &$this, 'update_prices_request' ) );
        }

        // Automatically update prices if enabled.
        if ( (int) apply_filters( 'wccs_background_auto_update_products_price', WCCS()->settings->get_setting( 'auto_update_products_price', 0 ) ) ) {
            add_action( 'woocommerce_update_product', array( &$this, 'maybe_update_prices' ) );
            add_action( 'woocommerce_update_product_variation', array( &$this, 'maybe_update_prices' ) );
            add_action( 'woocommerce_settings_saved', array( &$this, 'maybe_update_prices' ) );
            add_action( 'update_option_wccs_settings', array( &$this, 'maybe_update_prices' ) );
        }

        add_action( 'admin_init', array( &$this, 'updating_prices_notice' ) );
        add_action( 'wccs_hide_updating_prices_notice', array( &$this, 'dismiss_updating_prices_notice' ) );

        $this->background_process = new WCCS_Background_Price_Updater();
    }

    public function update_prices_request() {
        if ( empty( $_GET['do_update_products_price_asnp_wccs'] ) ) {
            return;
        } elseif ( ! wp_verify_nonce( sanitize_key( wp_unslash( $_GET['_wccs_update_products_price_nonce'] ) ), 'wccs_update_products_price_nonce' ) ) {
            return;
        }
        $this->maybe_update_prices();
        wp_safe_redirect( remove_query_arg( array( 'do_update_products_price_asnp_wccs', '_wccs_update_products_price_nonce' ), wp_get_referer() ) );
    }

    public function maybe_update_prices() {
        $this->queue_update_prices();
    }

    public function queue_update_prices() {
        global $wpdb;
        // First lets cancel existing running queue to avoid running it more than once.
        $this->background_process->kill_process();

        $products = $wpdb->get_results(
            "SELECT ID
			FROM $wpdb->posts
			WHERE post_type = 'product'
			ORDER BY ID DESC"
        );
        foreach ( $products as $product ) {
            $this->background_process->push_to_queue(
                array(
                    'product_id' => $product->ID,
                )
            );
        }

        // Lets dispatch the queue to start processing.
		$this->background_process->save()->dispatch();
    }

    public function updating_prices_notice() {
        if ( $this->background_process->is_updating() ) {
            WCCS()->WCCS_Admin_Notices->add_notice( 'update_price' );
        } else {
            WCCS()->WCCS_Admin_Notices->remove_notice( 'update_price' );
        }
    }

    public function dismiss_updating_prices_notice() {
        if ( ! $this->background_process ) {
            return;
        }

        $this->background_process->kill_process();
        $logger = WCCS_Helpers::wc_get_logger();
		if ( WCCS_Helpers::wc_version_check() ) {
			$logger->info( __( 'Cancelled product price update job.', 'easy-woocommerce-discounts' ), array( 'source' => 'wccs_price_updater' ) );
		} else {
			$logger->add( 'wccs_price_updater', __( 'Cancelled product price update job.', 'easy-woocommerce-discounts' ) );
		}
    }

}
