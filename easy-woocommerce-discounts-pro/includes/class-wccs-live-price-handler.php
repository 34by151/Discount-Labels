<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Live_Price_Handler {

	public $data;

	protected $cart;

	protected $pricing_item;

	/**
	 * Cart contents before adding an item with live price.
	 *
	 * @var array
	 */
	protected $base_cart_contents;

	/**
	 * Constructor
	 *
	 * @param array   $data
	 * @param boolean $clone_cart when true it clones WooCommerce cart and its content otherwise creates a new instance.
	 */
    public function __construct( $data, $clone_cart = true ) {
		$this->data = $data;

		if ( $clone_cart ) {
			$this->cart = new WCCS_Virtual_Cart();
		} else {
			$this->cart = new WCCS_Virtual_Cart( new WC_Cart() );
		}
    }

    /**
	 * Add to cart.
	 *
	 * Checks for a valid request, does validation (via hooks) and then redirects if valid.
     *
     * @since 2.0.0
	 *
	 * @return array|false false on failur.
	 */
    public function add_to_cart() {
        if ( empty( $this->data['add-to-cart'] ) || ! is_numeric( $this->data['add-to-cart'] ) ) {
            return false;
        }

        $product_id     = apply_filters( 'woocommerce_add_to_cart_product_id', absint( $this->data['add-to-cart'] ) );
		$cart_item_key  = false;
        $adding_to_cart = wc_get_product( $product_id );

        if ( ! $adding_to_cart ) {
			return false;
		}

		WCCS()->set_live_price_running( true );

		$this->base_cart_contents = $this->cart->get_cart();

		$add_to_cart_handler = WCCS_Helpers::wc_version_check() ? $adding_to_cart->get_type() : $adding_to_cart->product_type;

        if ( 'variable' === $add_to_cart_handler || 'variation' === $add_to_cart_handler ) {
            $cart_item_key = $this->add_to_cart_handler_variable( $product_id );
		} elseif ( 'grouped' === $add_to_cart_handler ) {
            $cart_item_key = $this->add_to_cart_handler_grouped( $product_id );
		} else {
            $cart_item_key = $this->add_to_cart_handler_simple( $product_id );
        }

		$price = false;
        if ( ! empty( $cart_item_key ) ) {
			$price = $this->get_price( $cart_item_key );
		}

		WCCS()->set_live_price_running( false );

        return $price;
    }

    /**
	 * Handle adding variable products to the cart.
	 *
	 * @since 2.2.0 Split from add_to_cart_action.
     *
	 * @param int $product_id Product ID to add to the cart.
     *
	 * @return bool success or not
	 */
    protected function add_to_cart_handler_variable( $product_id ) {
		$variation_id       = empty( $this->data['variation_id'] ) ? '' : absint( wp_unslash( $this->data['variation_id'] ) );
		$quantity           = empty( $this->data['quantity'] ) ? 1 : wc_stock_amount( wp_unslash( $this->data['quantity'] ) ); // WPCS: sanitization ok.
		$missing_attributes = array();
		$variations         = array();
		$adding_to_cart     = wc_get_product( $product_id );

		if ( ! $adding_to_cart ) {
			return false;
		}

		// If the $product_id was in fact a variation ID, update the variables.
		if ( $adding_to_cart->is_type( 'variation' ) ) {
			$variation_id   = $product_id;
			$product_id     = WCCS()->product_helpers->get_parent_id( $adding_to_cart );
			$adding_to_cart = wc_get_product( $product_id );

			if ( ! $adding_to_cart ) {
				return false;
			}
		}

		// Gather posted attributes.
		$posted_attributes = array();

		foreach ( $adding_to_cart->get_attributes() as $attribute ) {
			if ( ! $attribute['is_variation'] ) {
				continue;
			}
			$attribute_key = 'attribute_' . sanitize_title( $attribute['name'] );

			if ( isset( $this->data[ $attribute_key ] ) ) {
				if ( $attribute['is_taxonomy'] ) {
					// Don't use wc_clean as it destroys sanitized characters.
					$value = sanitize_title( wp_unslash( $this->data[ $attribute_key ] ) );
				} else {
					$value = html_entity_decode( wc_clean( wp_unslash( $this->data[ $attribute_key ] ) ), ENT_QUOTES, get_bloginfo( 'charset' ) ); // WPCS: sanitization ok.
				}

				$posted_attributes[ $attribute_key ] = $value;
			}
		}

		// If no variation ID is set, attempt to get a variation ID from posted attributes.
		if ( empty( $variation_id ) ) {
			if ( WCCS_Helpers::wc_version_check() ) {
				$data_store   = WC_Data_Store::load( 'product' );
				$variation_id = $data_store->find_matching_product_variation( $adding_to_cart, $posted_attributes );
			} else {
				$variation_id = $adding_to_cart->get_matching_variation( $posted_attributes );
			}
		}

		// Do we have a variation ID?
		if ( empty( $variation_id ) ) {
			return false;
		}

		// Check the data we have is valid.
		$variation_data = wc_get_product_variation_attributes( $variation_id );

		foreach ( $adding_to_cart->get_attributes() as $attribute ) {
			if ( ! $attribute['is_variation'] ) {
				continue;
			}

			// Get valid value from variation data.
			$attribute_key = 'attribute_' . sanitize_title( $attribute['name'] );
			$valid_value   = isset( $variation_data[ $attribute_key ] ) ? $variation_data[ $attribute_key ]: '';

			/**
			 * If the attribute value was posted, check if it's valid.
			 *
			 * If no attribute was posted, only error if the variation has an 'any' attribute which requires a value.
			 */
			if ( isset( $posted_attributes[ $attribute_key ] ) ) {
				$value = $posted_attributes[ $attribute_key ];

				// Allow if valid or show error.
				if ( $valid_value === $value ) {
					$variations[ $attribute_key ] = $value;
				} elseif ( is_object( $attribute ) && '' === $valid_value && in_array( $value, $attribute->get_slugs() ) ) {
					// If valid values are empty, this is an 'any' variation so get all possible values.
					$variations[ $attribute_key ] = $value;
				} elseif ( ! is_object( $attribute ) && '' === $valid_value ) {
					$variations[ $attribute_key ] = $value;
				} else {
					return false;
				}
			} elseif ( '' === $valid_value ) {
				$missing_attributes[] = wc_attribute_label( $attribute['name'] );
			}
		}
		if ( ! empty( $missing_attributes ) ) {
			return false;
		}

		$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity, $variation_id, $variations );
		if ( ! $passed_validation ) {
			wc_clear_notices();
		}

		return $this->cart->add_to_cart( $product_id, $quantity, $variation_id, $variations );
    }

    /**
	 * Handle adding grouped products to the cart.
	 *
	 * @since 2.2.0 Split from add_to_cart_action.
     *
	 * @param int $product_id Product ID to add to the cart.
     *
	 * @return bool success or not
	 */
	protected function add_to_cart_handler_grouped( $product_id ) {
		$was_added_to_cart = false;
        $added_to_cart     = array();
        $cart_item_keys    = array();

		if ( ! empty( $this->data['quantity'] ) && is_array( $this->data['quantity'] ) ) {
			$quantity_set = false;

			foreach ( $this->data['quantity'] as $item => $quantity ) {
				if ( $quantity <= 0 ) {
					continue;
				}
				$quantity_set = true;

				// Add to cart validation.
				$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $item, $quantity );
				if ( ! $passed_validation ) {
					wc_clear_notices();
				}

				$cart_item_keys[] = $this->cart->add_to_cart( $item, $quantity );

				if ( false !== $cart_item_keys[ count( $cart_item_keys ) - 1 ] ) {
					$was_added_to_cart = true;
					$added_to_cart[ $item ] = $quantity;
				}
			}

			if ( ! $was_added_to_cart && ! $quantity_set ) {
                return false;
			} elseif ( $was_added_to_cart ) {
				return $cart_item_keys;
			}
		} elseif ( $product_id ) {
            return false;
		}
		return false;
    }

    /**
	 * Handle adding simple products to the cart.
	 *
	 * @since 2.2.0 Split from add_to_cart_action.
     *
	 * @param int $product_id Product ID to add to the cart.
     *
	 * @return bool success or not
	 */
	protected function add_to_cart_handler_simple( $product_id ) {
		$quantity          = empty( $this->data['quantity'] ) ? 1 : wc_stock_amount( $this->data['quantity'] );
		$passed_validation = apply_filters( 'woocommerce_add_to_cart_validation', true, $product_id, $quantity );
		if ( ! $passed_validation ) {
			wc_clear_notices();
		}
		return $this->cart->add_to_cart( $product_id, $quantity );
	}

	/**
	 * Get price of given cart_item_keys.
	 *
	 * @since 2.2.0
	 *
	 * @param  string|array $cart_item_key
	 *
	 * @return void
	 */
	protected function get_price( $cart_item_key ) {
		if ( empty( $cart_item_key ) ) {
			return false;
		}

		$base_price    = WCCS()->settings->get_setting( 'pricing_product_base_price', 'cart_item_price' );
		$cart_contents = $this->cart->get_cart();
		$cart          = new WCCS_Cart( $this->cart );
		$pricing       = new WCCS_Pricing(
			WCCS_Conditions_Provider::get_pricings( array( 'status' => 1 ) ),
			new WCCS_Pricing_Condition_Validator( null, null, $cart )
		);

		if ( is_array( $cart_item_key ) ) {
			$discounted = false;
			$prices     = array();
			foreach ( $cart_item_key as $item_key ) {
				$cart_item          = apply_filters( 'wccs_live_price_cart_item', $cart_contents[ $item_key ], $this->data, $item_key, $cart_contents );
				$this->pricing_item = new WCCS_Public_Cart_Item_Pricing( $item_key, $cart_item, $pricing, '', $cart );
				$discounted_price   = $this->pricing_item->get_price();
				if ( false !== $discounted_price && 0 <= $discounted_price ) {
					$discounted_price = apply_filters( 'wccs_live_price_cart_item_discounted_price', $discounted_price, $cart_item );
					if ( apply_filters( 'wccs_live_price_apply_discounted_price_on_cart_item', true, $discounted_price, $cart_item['data'], $cart_item ) ) {
						$prices[ $item_key ] = $discounted_price;
						$discounted          = true;
					}
				}
			}

			if ( ! $discounted ) {
				return false;
			}

			$price      = 0;
			$main_price = 0;
			$quantity   = 0;
			foreach ( $cart_item_key as $item_key ) {
				$price += $this->cart->get_product_price(
					$cart_contents[ $item_key ]['data'],
					false,
					( isset( $prices[ $item_key ] ) ? array( 'price' => $prices[ $item_key ] ) : array() )
				);
				$main_price += 'cart_item_price' === $base_price ?
					$this->cart->get_product_price( $cart_contents[ $item_key ]['data'], false ) :
					$this->cart->get_product_price( wc_get_product( $cart_contents[ $item_key ]['data']->get_id() ), false );
				$quantity   += $cart_contents[ $cart_item_key ]['quantity'];
			}

			return apply_filters(
				'wccs_live_price_get_price',
				array(
					'discounted_price' => $price,
					'price'            => wc_price( $price ) . $cart_item['data']->get_price_suffix( $price ),
					'quantity'         => $quantity,
					'total_price'      => wc_price( $discounted_price * $quantity ) . $cart_item['data']->get_price_suffix( $discounted_price, $quantity ),
					'main_price'       => wc_price( $main_price ) . $cart_item['data']->get_price_suffix( $main_price ),
					'main_total_price' => wc_price( $main_price * $quantity ) . $cart_item['data']->get_price_suffix( $main_price, $quantity ),
				),
				$cart_item_key,
				$cart_contents
			);
		}

		$cart_item          = apply_filters( 'wccs_live_price_cart_item', $cart_contents[ $cart_item_key ], $this->data, $cart_item_key, $cart_contents );
		$this->pricing_item = new WCCS_Public_Cart_Item_Pricing( $cart_item_key, $cart_item, $pricing, '', $cart );
		$discounted_price   = $this->pricing_item->get_price();
		$prices_quantities  = array();
		if ( false !== $discounted_price && 0 <= $discounted_price ) {
			$discounted_price = apply_filters( 'wccs_live_price_cart_item_discounted_price', $discounted_price, $cart_item );
			if ( apply_filters( 'wccs_live_price_apply_discounted_price_on_cart_item', true, $discounted_price, $cart_item['data'], $cart_item ) ) {
				$prices_quantities = $this->get_prices_quantities( $cart_item_key );
			} else {
				$discounted_price = false;
			}
		}

		$main_price = 'cart_item_price' === $base_price ?
			apply_filters(
				'wccs_live_price_cart_item_main_price',
				$this->cart->get_product_price( $cart_item['data'], false ),
				$cart_item
			) :
			apply_filters(
				'wccs_live_price_cart_item_main_price',
				$this->cart->get_product_price( wc_get_product( $cart_item['data']->get_id() ), false ),
				$cart_item
			);

		$display_countdown_timer = WCCS()->settings->get_setting( 'display_countdown_timer', 0 );
		$remaining_time          = false;
		// Display countdown timer based on all available pricing rules that have a date-time condition.
		if ( 1 == $display_countdown_timer ) {
			$remaining_time = $this->pricing_item->get_nearest_end_time();
		} // Display countdown timer when a pricing rule discount that has a date-time codition can apply.
		elseif ( 2 == $display_countdown_timer ) {
			$remaining_time = $this->pricing_item->get_nearest_end_time( false );
		}
		if ( false !== $remaining_time ) {
			$remaining_time = $this->check_threshold_time( $remaining_time ) ?
				strtotime( $remaining_time ) - current_time( 'timestamp' ) : false;
		}

		return apply_filters(
			'wccs_live_price_get_price',
			array(
				'discounted_price'      => false !== $discounted_price && 0 <= $discounted_price ? $this->cart->get_product_price( $cart_item['data'], false, array( 'price' => $discounted_price ) ) : false,
				'price'                 => false !== $discounted_price && 0 <= $discounted_price ? wc_price( $this->cart->get_product_price( $cart_item['data'], false, array( 'price' => $discounted_price ) ) ) . $cart_item['data']->get_price_suffix( $discounted_price ) : false,
				'quantity'              => $cart_item['quantity'],
				'total_price'           => false !== $discounted_price && 0 <= $discounted_price ? wc_price( apply_filters( 'wccs_live_price_total_price', $this->cart->get_product_price( $cart_item['data'], false, array( 'price' => $discounted_price * $cart_item['quantity'] ) ), $discounted_price, $cart_item ) ) . $cart_item['data']->get_price_suffix( $discounted_price, $cart_item['quantity'] ) : false,
				'main_price'            => wc_price( $main_price ) . $cart_item['data']->get_price_suffix( $main_price ),
				'main_total_price'      => wc_price( apply_filters( 'wccs_live_price_main_total_price', $main_price * $cart_item['quantity'], $main_price, $cart_item ) ) . $cart_item['data']->get_price_suffix( $main_price, $cart_item['quantity'] ),
				'prices_quantities'     => ! empty( $prices_quantities ) ? $this->format_prices_quantities( $prices_quantities, $cart_item ) : array(),
				'prices_quantities_sum' => ! empty( $prices_quantities ) ? $this->get_sum_of_prices_quantities( $prices_quantities, $cart_item ) : '',
				'remaining_time'        => $remaining_time,
			),
			$cart_item_key,
			$cart_contents
		);
	}

	public function get_prices() {
		if ( false === $this->add_to_cart() || ! isset( $this->pricing_item ) ) {
			return array();
		}

		WCCS()->set_live_price_running( true );
		$prices = apply_filters( 'wccs_live_price_cart_item_prices', $this->pricing_item->get_prices(), $this->pricing_item->item );
		WCCS()->set_live_price_running( false );
		return $prices;
	}

	protected function check_threshold_time( $end_time ) {
		if ( empty( $end_time ) ) {
			return false;
		}

		$end_time = strtotime( $end_time );
		if ( false === $end_time || -1 === $end_time ) {
			return false;
		}

		$threshold_time = (int) WCCS()->settings->get_setting( 'countdown_timer_threshold_time', 1 );
		$threshold_type = WCCS()->settings->get_setting( 'countdown_timer_threshold_time_type', 'no_limit' );

		if ( 'no_limit' === $threshold_type ) {
			return true;
		} elseif ( 0 >= $threshold_time ) {
			return false;
		}

		$threshold_time = strtotime( "-{$threshold_time} {$threshold_type}", $end_time );
		if ( false === $threshold_time || -1 === $threshold_time ) {
			return false;
		}

		if ( current_time( 'timestamp' ) < $threshold_time ) {
			return false;
		}

		return true;
	}

	protected function get_prices_quantities( $cart_item_key ) {
		if ( ! isset( $this->pricing_item ) ) {
			return array();
		}

		$prices = $this->pricing_item->get_prices();
		if ( empty( $prices ) ) {
			return $prices;
		}

		ksort( $prices );

		if ( empty( $this->base_cart_contents[ $cart_item_key ] ) || empty( $this->base_cart_contents[ $cart_item_key ]['_wccs_prices_main'] ) ) {
			return apply_filters( 'wccs_live_price_' . __FUNCTION__, $prices, $this->pricing_item->item );
		} elseif ( $prices === $this->base_cart_contents[ $cart_item_key ]['_wccs_prices_main'] ) {
			return apply_filters( 'wccs_live_price_' . __FUNCTION__, $prices, $this->pricing_item->item );
		}

		/**
		 * Find differences.
		 * It will find differences between quantities that alrady in the cart and quantities add by the live price
		 * to find prices of quantities added by the live price.
		 */
		$differences = array();
		foreach ( $prices as $price => $quantity ) {
			if ( ! isset( $this->base_cart_contents[ $cart_item_key ]['_wccs_prices_main'][ $price ] ) ) {
				$differences[ $price ] = $quantity;
				continue;
			}

			if ( $quantity == $this->base_cart_contents[ $cart_item_key ]['_wccs_prices_main'][ $price ] ) {
				continue;
			}

			$differences[ $price ] = $quantity > $quantity - $this->base_cart_contents[ $cart_item_key ]['_wccs_prices_main'][ $price ] ?
				$quantity - $this->base_cart_contents[ $cart_item_key ]['_wccs_prices_main'][ $price ] :
				$this->base_cart_contents[ $cart_item_key ]['_wccs_prices_main'][ $price ] - $quantity;
		}
		return apply_filters( 'wccs_live_price_' . __FUNCTION__, $differences, $this->pricing_item->item );
	}

	protected function format_prices_quantities( array $prices_quantities, $cart_item ) {
		if ( empty( $prices_quantities ) ) {
			return $prices_quantities;
		}

		foreach ( $prices_quantities as $price => $quantity ) {
			$formated_price                       = apply_filters( 'wccs_live_price_prices_quantities_formated_price', wc_price( $price ) . $cart_item['data']->get_price_suffix( $price ), $price, $cart_item );
			$prices_quantities[ $formated_price ] = $quantity;
			unset( $prices_quantities[ $price ] );
		}
		return $prices_quantities;
	}

	protected function get_sum_of_prices_quantities( array $prices_quantities, $cart_item ) {
		if ( empty( $prices_quantities ) ) {
			return '';
		}

		$sum = 0;
		foreach ( $prices_quantities as $price => $quantity ) {
			$sum += (float) $price * $quantity;
		}

		return apply_filters( 'wccs_live_price_' . __FUNCTION__, wc_price( $sum ), $sum, $cart_item );
	}

}
