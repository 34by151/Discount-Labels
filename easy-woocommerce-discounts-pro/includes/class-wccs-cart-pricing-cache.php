<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * The class responsible for caching activities related to the cart pricing.
 *
 * @since      2.6.0
 * @package    WC_Conditions
 * @subpackage WC_Conditions/includes
 *
 * @author     Asana Plugins <asanaplugins@gmail.com>
 */
class WCCS_Cart_Pricing_Cache {

    public $cache;

    /**
     * Constructor.
     *
     * @since 2.6.0
     */
    public function __construct() {
        $this->reset_cache();
    }

    /**
     * Get the cache.
     *
     * @since  2.6.0
     *
     * @return array
     */
    public function get_cache() {
        return $this->cache;
    }

    /**
     * Reset the cache.
     *
     * @since  2.6.0
     *
     * @return void
     */
    public function reset_cache() {
        $this->cache = array(
            'applied_pricings' => array(),
        );
    }

    /**
     * Adding an applied pricing rule to the cache.
     *
     * @since  2.6.0
     *
     * @param  int   $pricing_id
     * @param  array $content
     *
     * @return void
     */
    public function add_applied_pricing( $pricing_id, $content ) {
        $this->cache['applied_pricings'][ $pricing_id ] = $content;
    }

    /**
     * Getting cart applied pricings.
     *
     * @since  2.6.0
     *
     * @return void
     */
    public function get_applied_pricings() {
        return ! empty( $this->cache['applied_pricings'] ) ? $this->cache['applied_pricings'] : array();
    }

}
