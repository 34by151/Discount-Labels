<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Product_Price_Replace {

    const PRICE         = 'price';
    const REGULAR_PRICE = 'regular_price';
    const SALE_PRICE    = 'sale_price';
    const PRIORITY      = 9999;

    public $should_replace_prices = false;

    protected $change_regular_price;

    protected $read_variation_cached_prices = null;

    protected $prices = array();

    public function __construct( $should_replace_prices = false, $read_variation_cached_prices = null, $change_regular_price = false ) {
        $this->should_replace_prices        = $should_replace_prices;
        $this->read_variation_cached_prices = $read_variation_cached_prices;
        $this->change_regular_price         = $change_regular_price;
    }

    public function get_filters() {
        return array(
            /* array(
                'hook'          => 'woocommerce_get_variation_prices_hash',
                'component'     => $this,
                'callback'      => 'get_variation_prices_hash',
                'priority'      => self::PRIORITY,
                'accepted_args' => 3,
            ), */
            array(
                'hook'          => 'woocommerce_product_get_price',
                'component'     => $this,
                'callback'      => 'replace_price',
                'priority'      => self::PRIORITY,
                'accepted_args' => 2,
            ),
            array(
                'hook'          => 'woocommerce_product_get_sale_price',
                'component'     => $this,
                'callback'      => 'replace_sale_price',
                'priority'      => self::PRIORITY,
                'accepted_args' => 2,
            ),
            array(
                'hook'          => 'woocommerce_product_get_regular_price',
                'component'     => $this,
                'callback'      => 'replace_regular_price',
                'priority'      => self::PRIORITY,
                'accepted_args' => 2,
            ),
            array(
                'hook'          => 'woocommerce_product_variation_get_price',
                'component'     => $this,
                'callback'      => 'replace_price',
                'priority'      => self::PRIORITY,
                'accepted_args' => 2,
            ),
            array(
                'hook'          => 'woocommerce_product_variation_get_sale_price',
                'component'     => $this,
                'callback'      => 'replace_sale_price',
                'priority'      => self::PRIORITY,
                'accepted_args' => 2,
            ),
            array(
                'hook'          => 'woocommerce_product_variation_get_regular_price',
                'component'     => $this,
                'callback'      => 'replace_regular_price',
                'priority'      => self::PRIORITY,
                'accepted_args' => 2,
            ),
            array(
                'hook'          => 'woocommerce_variation_prices',
                'component'     => $this,
                'callback'      => 'replace_variation_prices',
                'priority'      => 999,
                'accepted_args' => 3,
            ),
        );
    }

    public function set_should_replace_prices( $value ) {
        $this->should_replace_prices = $value;
        return $this;
    }

    public function set_change_regular_price( $value ) {
        $this->change_regular_price = $value;
        return $this;
    }

    public function enable_hooks() {
        if ( ! $this->should_replace_prices ) {
            return;
        }

        foreach ( $this->get_filters() as $hook ) {
            add_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'], $hook['accepted_args'] );
        }
    }

    public function disable_hooks() {
        if ( ! $this->should_replace_prices ) {
            return;
        }

        foreach ( $this->get_filters() as $hook ) {
            remove_filter( $hook['hook'], array( $hook['component'], $hook['callback'] ), $hook['priority'] );
        }
    }

    public function replace( $price, $product, $price_type, $exchange_price = true ) {
        if ( ! $this->should_replace( $price, $product, $price_type ) ) {
            return apply_filters( 'wccs_product_price_replace_replace_price', $price, $price, $product, $price_type );
        }

        $cached_price = WCCS()->WCCS_Product_Price_Cache->get_price( $product, $price, $price_type );
        if ( ! is_numeric( $cached_price ) || 0 > $cached_price ) {
            return apply_filters( 'wccs_product_price_replace_replace_price', $price, $price, $product, $price_type );
        }

        if ( $cached_price != $price && $exchange_price ) {
            $cached_price = WCCS_Helpers::maybe_exchange_price( $cached_price );
        }

        return apply_filters( 'wccs_product_price_replace_replace_price', $cached_price, $price, $product, $price_type );
    }

    public function replace_price( $price, $product, $exchange_price = true ) {
        if ( ! isset( $this->prices[ $product->get_id() ] ) ) {
            $this->prices[ $product->get_id() ] = array( 'price' => array() );
        }

        return $this->maybe_cache_price(
            $product,
            $price,
            'price',
            $this->replace( $price, $product, self::PRICE, $exchange_price )
        );
    }

    public function replace_sale_price( $price, $product, $exchange_price = true ) {
        if ( ! isset( $this->prices[ $product->get_id() ] ) ) {
            $this->prices[ $product->get_id() ] = array( 'sale_price' => array() );
        }

        return $this->maybe_cache_price(
            $product,
            $price,
            'sale_price',
            $this->replace( $price, $product, self::SALE_PRICE, $exchange_price )
        );
    }

    public function replace_regular_price( $price, $product, $exchange_price = true ) {
        if ( ! isset( $this->prices[ $product->get_id() ] ) ) {
            $this->prices[ $product->get_id() ] = array( 'regular_price' => array() );
        }

        return $this->maybe_cache_price(
            $product,
            $price,
            'regular_price',
            $this->change_regular_price ? $this->replace( $price, $product, self::REGULAR_PRICE, $exchange_price ) : $price
        );
    }

    public function replace_variation_prices( $prices, $product, $for_display ) {
        if ( ! WCCS()->is_request( 'frontend' ) || ! is_a( WC()->cart, 'WC_Cart' ) ) {
            return $prices;
        }

        // Do not replace price till woocommerce_cart_loaded_from_session done.
        if ( ! did_action( 'woocommerce_cart_loaded_from_session' ) ) {
            return $prices;
        }

        if ( ! isset( $this->prices[ $product->get_id() ] ) ) {
            $this->prices[ $product->get_id() ] = array();
        }

        $price_hash         = $this->get_price_hash( $product, $for_display );
        $pricing_hash       = $this->get_variation_pricing_hash( $product, $for_display );
        $read_cached_prices = $this->can_read_variation_cached_prices( $product );

        if ( $read_cached_prices ) {
            $cache = WCCS()->WCCS_DB_Cache->get_item_by_product( $product->get_id(), 'price' );
            $value = ! empty( $cache->value ) && is_array( $cache->value ) ? $cache->value : array();
        }

        if ( $read_cached_prices && isset( $this->prices[ $product->get_id() ][ $price_hash ][ $pricing_hash ] ) ) {
            return $this->prices[ $product->get_id() ][ $price_hash ][ $pricing_hash ];
        }

        $transient_cached_prices_array = array();
        if ( $read_cached_prices ) {
            if ( empty( $this->prices[ $product->get_id() ][ $price_hash ][ $pricing_hash ] ) ) {
                $transient_cached_prices_array = $value;
            }
        }

        // If the prices are not stored for this hash, generate them and add to the transient.
        if ( empty( $transient_cached_prices_array[ $price_hash ][ $pricing_hash ] ) ) {
            $prices_array = array(
                'price'         => array(),
                'regular_price' => array(),
                'sale_price'    => array(),
            );

            $variation_ids = $product->get_visible_children();

            foreach ( $variation_ids as $variation_id ) {
                $variation = wc_get_product( $variation_id );

                if ( $variation ) {
                    $price         = $this->get_replaced_price( $variation->get_price( 'edit' ), $variation, false );
                    $regular_price = $this->get_replaced_regular_price( $variation->get_regular_price( 'edit' ), $variation, false );
                    $sale_price    = $this->get_replaced_sale_price( $variation->get_sale_price( 'edit' ), $variation, false );

                    // Skip empty prices.
                    if ( '' === $price ) {
                        continue;
                    }

                    // If sale price does not equal price, the product is not yet on sale.
                    if ( $sale_price === $regular_price || $sale_price !== $price ) {
                        $sale_price = $regular_price;
                    }

                    // If we are getting prices for display, we need to account for taxes.
                    if ( $for_display ) {
                        if ( 'incl' === get_option( 'woocommerce_tax_display_shop' ) ) {
                            $price         = '' === $price ? '' : wc_get_price_including_tax(
                                $variation,
                                array(
                                    'qty'   => 1,
                                    'price' => $price,
                                )
                            );
                            $regular_price = '' === $regular_price ? '' : wc_get_price_including_tax(
                                $variation,
                                array(
                                    'qty'   => 1,
                                    'price' => $regular_price,
                                )
                            );
                            $sale_price    = '' === $sale_price ? '' : wc_get_price_including_tax(
                                $variation,
                                array(
                                    'qty'   => 1,
                                    'price' => $sale_price,
                                )
                            );
                        } else {
                            $price         = '' === $price ? '' : wc_get_price_excluding_tax(
                                $variation,
                                array(
                                    'qty'   => 1,
                                    'price' => $price,
                                )
                            );
                            $regular_price = '' === $regular_price ? '' : wc_get_price_excluding_tax(
                                $variation,
                                array(
                                    'qty'   => 1,
                                    'price' => $regular_price,
                                )
                            );
                            $sale_price    = '' === $sale_price ? '' : wc_get_price_excluding_tax(
                                $variation,
                                array(
                                    'qty'   => 1,
                                    'price' => $sale_price,
                                )
                            );
                        }
                    }

                    $prices_array['price'][ $variation_id ]         = wc_format_decimal( $price, wc_get_price_decimals() );
                    $prices_array['regular_price'][ $variation_id ] = wc_format_decimal( $regular_price, wc_get_price_decimals() );
                    $prices_array['sale_price'][ $variation_id ]    = wc_format_decimal( $sale_price, wc_get_price_decimals() );
                }
            }

            // Add all pricing data to the transient array.
            foreach ( $prices_array as $key => $values ) {
                $transient_cached_prices_array[ $price_hash ][ $pricing_hash ][ $key ] = $values;
            }

            // Important: Cache prices only when read prices from cache is available.
            if ( $read_cached_prices ) {
                if ( $cache ) {
                    WCCS()->WCCS_DB_Cache->update( $cache->id, array( 'value' => maybe_serialize( $transient_cached_prices_array ) ) ); 
                } else {
                    WCCS()->WCCS_DB_Cache->add( array( 'product_id' => $product->get_id(), 'cache_type' => 'price', 'value' => maybe_serialize( $transient_cached_prices_array ) ) );
                }
            }
        }

        $this->prices[ $product->get_id() ][ $price_hash ][ $pricing_hash ] = $transient_cached_prices_array[ $price_hash ][ $pricing_hash ];

        return $this->prices[ $product->get_id() ][ $price_hash ][ $pricing_hash ];
    }

    protected function should_replace( $price, $product, $price_type ) {
        if ( ! $this->should_replace_prices ) {
            return apply_filters( 'wccs_product_price_replace_should_replace', false, $price, $product, $price_type, $this  );
        }

        if ( ! WCCS()->is_request( 'frontend' ) ) {
            return apply_filters( 'wccs_product_price_replace_should_replace', false, $price, $product, $price_type, $this  );
        }

        // Do not replace price till woocommerce_cart_loaded_from_session done.
        if ( ! did_action( 'woocommerce_cart_loaded_from_session' ) && ! WCCS_Helpers::wc_is_rest_api_request() ) {
            return apply_filters( 'wccs_product_price_replace_should_replace', false, $price, $product, $price_type, $this  );
        }

        // Do not replace variable products price.
        if ( $product->is_type( 'variable' ) ) {
            return apply_filters( 'wccs_product_price_replace_should_replace', false, $price, $product, $price_type, $this  );
        }

        // Do not replace product price when it is empty.
        if ( '' === $price && self::PRICE === $price_type ) {
            return apply_filters( 'wccs_product_price_replace_should_replace', false, $price, $product, $price_type, $this  );
        }

        // Do not replace price for cart items.
        if ( null !== WCCS()->custom_props->get_prop( $product, 'wccs_is_cart_item' ) ) {
            return apply_filters( 'wccs_product_price_replace_should_replace', false, $price, $product, $price_type, $this  );
        }

        return apply_filters( 'wccs_product_price_replace_should_replace', true, $price, $product, $price_type, $this );
    }

    public function get_replaced_price( $price, $product, $exchange_price = true ) {
        if ( isset( $this->prices[ $product->get_id() ]['price'][ strval( $price ) ] ) ) {
            return $this->prices[ $product->get_id() ]['price'][ strval( $price ) ];
        }

        return $this->replace_price( $price, $product, $exchange_price );
    }

    public function get_replaced_sale_price( $price, $product, $exchange_price = true ) {
        if ( isset( $this->prices[ $product->get_id() ]['sale_price'][ strval( $price ) ] ) ) {
            return $this->prices[ $product->get_id() ]['sale_price'][ strval( $price ) ];
        }

        return $this->replace_sale_price( $price, $product, $exchange_price );
    }

    public function get_replaced_regular_price( $price, $product, $exchange_price = true ) {
        if ( isset( $this->prices[ $product->get_id() ]['regular_price'][ strval( $price ) ] ) ) {
            return $this->prices[ $product->get_id() ]['regular_price'][ strval( $price ) ];
        }

        return $this->replace_regular_price( $price, $product, $exchange_price );
    }

    public function can_read_variation_cached_prices( $product ) {
        if ( null !== $this->read_variation_cached_prices ) {
            return $this->read_variation_cached_prices;
        }

        $value = true;

        $pricing_rules = WCCS()->pricing->get_simple_pricings();
        $exclude_rules = WCCS()->pricing->get_exclude_rules();

        $hash = md5( wp_json_encode( array( $pricing_rules, $exclude_rules ) ) );

        $saved_hash = get_transient( 'wccs_can_read_variation_cached_prices_hash' );
        if ( $saved_hash != $hash ) {
            if ( is_user_logged_in() ) {
                // Compare with use specific transient.
                $user_id = get_current_user_id();
                $user_transient = get_transient( 'wccs_can_read_variation_cached_prices_hash_' . $user_id );
                if ( $user_transient != $hash ) {
                    $value = false;
                    if ( $this->can_save_variation_cached_prices_transient( $pricing_rules ) ) {
                        set_transient( 'wccs_can_read_variation_cached_prices_hash_' . $user_id, $hash, DAY_IN_SECONDS );
                    }
                }

                // Set general transient.
            } else {
                $value = false;
                if ( $this->can_save_variation_cached_prices_transient( $pricing_rules ) ) {
                    set_transient( 'wccs_can_read_variation_cached_prices_hash', $hash );
                }
            }
        }

        $this->read_variation_cached_prices = apply_filters( 'wccs_product_price_replace_read_variation_cached_prices', $value, $product );

        return $this->read_variation_cached_prices;
    }

    public function get_variation_prices_hash( $price_hash, $product, $for_display ) {
        $price_hash[] = WCCS()->WCCS_Product_Price_Cache->get_transient_name( array( 'is_user_logged_in' => is_user_logged_in() ) );
        return $price_hash;
    }

    protected function maybe_cache_price( $product, $price, $price_type, $replaced_price ) {
        // Do not cache the price while woocommerce_cart_loaded_from_session done.
        if ( ! did_action( 'woocommerce_cart_loaded_from_session' ) ) {
            return $replaced_price;
        } elseif ( ! $product || ! $product->get_id() ) {
            return $replaced_price;
        } elseif ( null !== WCCS()->custom_props->get_prop( $product, 'wccs_is_cart_item' ) ) {
            return $replaced_price;
        } elseif ( empty( $price_type ) ) {
            return $replaced_price;
        }

        $this->prices[ $product->get_id() ][ $price_type ][ strval( $price ) ] = $replaced_price;

        return $replaced_price;
    }

     /**
	 * Create unique cache key based on the tax location (affects displayed/cached prices), product version and active price filters.
	 * DEVELOPERS should filter this hash if offering conditional pricing to keep it unique.
	 *
	 * @param WC_Product $product Product object.
	 * @param bool       $for_display If taxes should be calculated or not.
	 *
	 * @return string
	 */
	protected function get_price_hash( &$product, $for_display = false ) {
		global $wp_filter;

		$price_hash   = $for_display && wc_tax_enabled() ? array( get_option( 'woocommerce_tax_display_shop', 'excl' ), WC_Tax::get_rates() ) : array( false );
		$filter_names = array( 'woocommerce_variation_prices_price', 'woocommerce_variation_prices_regular_price', 'woocommerce_variation_prices_sale_price' );

		foreach ( $filter_names as $filter_name ) {
			if ( ! empty( $wp_filter[ $filter_name ] ) ) {
				$price_hash[ $filter_name ] = array();

				foreach ( $wp_filter[ $filter_name ] as $priority => $callbacks ) {
					$price_hash[ $filter_name ][] = array_values( wp_list_pluck( $callbacks, 'function' ) );
				}
			}
		}

		return md5( wp_json_encode( apply_filters( 'woocommerce_get_variation_prices_hash', $price_hash, $product, $for_display ) ) );
    }

    protected function get_variation_pricing_hash( $product, $for_display = false )  {
        $hash = array(
            'rules'         => WCCS()->pricing->get_simple_pricings(),
            'exclude_rules' => WCCS()->pricing->get_exclude_rules(),
        );

        return md5( wp_json_encode( apply_filters( 'wccs_' . __FUNCTION__, $hash, $product, $for_display ) ) );
    }

    protected function can_save_variation_cached_prices_transient( $rules ) {
        if ( empty( $rules ) ) {
            return false;
        }

        if ( WC()->cart && WC()->cart->is_empty() ) {
            return true;
        }

        return ! WCCS_Rules_Helpers::has_cart_conditions( $rules );
    }

}
