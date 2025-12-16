<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Admin_Conditions_Hooks {

    protected $loader;

    protected $auto_update_prices;

    public function __construct( WCCS_Loader $loader ) {
        $this->loader             = $loader;
        $this->auto_update_prices = (int) apply_filters( 'wccs_background_auto_update_products_price', WCCS()->settings->get_setting( 'auto_update_products_price', 0 ) );
    }

    public function enable_hooks() {
        $this->loader->add_action( 'wccs_condition_added', $this, 'condition_added' );
        $this->loader->add_action( 'wccs_condition_deleted', $this, 'condition_deleted' );
        $this->loader->add_action( 'wccs_condition_updated', $this, 'condition_updated' );
        $this->loader->add_action( 'wccs_conditions_ordering_updated', $this, 'conditions_ordering_updated' );
        $this->loader->add_action( 'wccs_condition_duplicated', $this, 'condition_duplicated' );
    }

    public function condition_added( $condition ) {
        if ( ! $condition || ! $condition->id ) {
            return;
        }

        if ( 'pricing' === $condition->type ) {
            WCCS()->WCCS_Clear_Cache->clear_pricing_caches();
            if ( $this->auto_update_prices ) {
                WCCS()->WCCS_Background_Batch_Price_Updater->maybe_update_prices();
            }
        }
    }

    public function condition_deleted( $condition ) {
        if ( ! $condition || ! $condition->id ) {
            return;
        }

        if ( 'pricing' === $condition->type ) {
            WCCS()->WCCS_Clear_Cache->clear_pricing_caches();
            if ( $this->auto_update_prices ) {
                WCCS()->WCCS_Background_Batch_Price_Updater->maybe_update_prices();
            }
        }
    }

    public function condition_updated( $condition ) {
        if ( ! $condition || ! $condition->id ) {
            return;
        }

        if ( 'pricing' === $condition->type ) {
            WCCS()->WCCS_Clear_Cache->clear_pricing_caches();
            if ( $this->auto_update_prices ) {
                WCCS()->WCCS_Background_Batch_Price_Updater->maybe_update_prices();
            }
        }
    }

    public function condition_duplicated( $condition_id ) {
        if ( ! $condition_id ) {
            return;
        }

        $condition = WCCS()->conditions->get_condition( $condition_id );
        if ( 'pricing' === $condition->type ) {
            WCCS()->WCCS_Clear_Cache->clear_pricing_caches();
            if ( $this->auto_update_prices ) {
                WCCS()->WCCS_Background_Batch_Price_Updater->maybe_update_prices();
            }
        }
    }

    public function conditions_ordering_updated( $type ) {
        if ( 'pricing' === $type ) {
            WCCS()->WCCS_Clear_Cache->clear_pricing_caches();
            if ( $this->auto_update_prices ) {
                WCCS()->WCCS_Background_Batch_Price_Updater->maybe_update_prices();
            }
        }
    }

}
