<?php

defined( 'ABSPATH' ) || exit;

class WCCS_Pubilc_Analytics_Hooks {

	public $analytics;

	protected $background_analytics;

	protected $cart_updated = false;

	public function __construct( $analytics = null ) {
		$this->analytics = null !== $analytics ? $analytics : WCCS()->container()->get( WCCS_DB_Analytics::class);
		$this->background_analytics = new WCCS_Background_Analytics( $this->analytics );
	}

	public function init() {
		add_action( 'template_redirect', [ $this, 'on_checkout' ] );
		add_action( 'woocommerce_checkout_update_order_meta', [ $this, 'on_order' ] );
		add_action( 'woocommerce_store_api_checkout_order_processed', [ $this, 'on_order' ] );
		add_action( 'woocommerce_after_calculate_totals', [ $this, 'on_cart' ], 999999 );
		add_action( 'woocommerce_remove_cart_item', [ $this, 'on_remove_cart_item' ], 10, 2 );
		add_action( 'woocommerce_cart_item_removed', [ $this, 'log_cart_rules' ] );
		add_action( 'woocommerce_checkout_order_processed', [ $this, 'clear_logged_rules' ] );

		// Impression hooks.
		add_action( 'wp_ajax_asnp_wccs_product_tracks', [ $this, 'impression' ] );
		add_action( 'wp_ajax_nopriv_asnp_wccs_product_tracks', [ $this, 'impression' ] );

		// Cart updated.
		add_action( 'woocommerce_add_to_cart', function () {
			$this->cart_updated = true;
		} );
		add_action( 'woocommerce_after_cart_item_quantity_update', function () {
			$this->cart_updated = true;
		} );
		add_action( 'wc_ajax_update_shipping_method', function () {
			$this->cart_updated = true;
		}, 8 );
		add_action( 'woocommerce_applied_coupon', function () {
			$this->cart_updated = true;
		} );
		add_action( 'woocommerce_removed_coupon', [ $this, 'on_removed_coupon' ] );
		add_action( 'woocommerce_store_api_cart_select_shipping_rate', function () {
			$this->log_cart_rules( 'checkout' );
		} );
	}

	public function on_checkout() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return; // don't run in admin
		}

		if ( is_cart() ) {
			return;
		}

		// If it is the checkout page.
		if (
			( is_checkout() && ! is_checkout_pay_page() && ! is_order_received_page() ) ||
			( is_callable( 'WC_Blocks_Utils::has_block_in_page' ) && WC_Blocks_Utils::has_block_in_page( get_the_ID(), 'woocommerce/checkout' ) )
		) {
			if ( ! $this->is_cart_updated() ) {
				return;
			}

			$this->log_pricing_rules( 'checkout' );
			$this->log_cart_rules( 'checkout' );

			if ( ! headers_sent() ) {
				$cart_hash = isset( $_COOKIE['woocommerce_cart_hash'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['woocommerce_cart_hash'] ) ) : '';
				if ( ! empty( $cart_hash ) ) {
					wc_setcookie( 'asnp_wccs_analytics_cart_hash', $cart_hash, time() + WEEK_IN_SECONDS );
				}
			}
		}
	}

	public function on_order( $order ) {
		$order = is_numeric( $order ) ? wc_get_order( $order ) : $order;
		if ( ! $order ) {
			return;
		}

		$this->log_pricing_rules( 'order' );
		$this->log_cart_rules( 'order' );
	}

	public function on_cart() {
		if ( is_admin() && ! defined( 'DOING_AJAX' ) ) {
			return; // don't run in admin
		}

		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		if ( ! $this->cart_updated ) {
			return;
		}

		$this->log_pricing_rules( 'add_to_cart' );
		$this->log_cart_rules( 'add_to_cart' );

		$this->cart_updated = false;
	}

	public function on_remove_cart_item( $cart_item_key, $cart ) {
		$cart_contents = $cart->get_cart();
		if ( ! isset( $cart_contents[ $cart_item_key ] ) || empty( $cart_contents[ $cart_item_key ]['_wccs_applied_rules'] ) ) {
			return;
		}

		$rule_usage = [];
		foreach ( $cart_contents[ $cart_item_key ]['_wccs_applied_rules'] as $rule ) {
			$rule_id = $rule['id'];
			$rule_usage[ $rule_id ] = isset( $rule_usage[ $rule_id ] )
				? $rule_usage[ $rule_id ] + 1
				: 1;
		}

		if ( empty( $rule_usage ) ) {
			return;
		}

		$this->analytics->log_events( 'rejection', $rule_usage );

		$cart_rules = WC()->session->get( 'wccs_analytics_add_to_cart_pricing_rules', [] );
		$checkout_rules = WC()->session->get( 'wccs_analytics_checkout_pricing_rules', [] );

		if ( empty( $cart_rules ) && empty( $checkout_rules ) ) {
			return;
		}

		foreach ( $rule_usage as $rule_id => $count ) {
			if ( isset( $cart_rules[ $rule_id ] ) ) {
				$cart_rules[ $rule_id ] -= $count;
				if ( 0 >= $cart_rules[ $rule_id ] ) {
					unset( $cart_rules[ $rule_id ] );
				}
			}

			if ( isset( $checkout_rules[ $rule_id ] ) ) {
				$checkout_rules[ $rule_id ] -= $count;
				if ( 0 >= $checkout_rules[ $rule_id ] ) {
					unset( $checkout_rules[ $rule_id ] );
				}
			}
		}

		WC()->session->set( 'wccs_analytics_add_to_cart_pricing_rules', $cart_rules );
		WC()->session->set( 'wccs_analytics_checkout_pricing_rules', $checkout_rules );
	}

	public function log_cart_rules( $event_type = 'add_to_cart' ) {
		if ( ! WC()->cart ) {
			return;
		}

		if ( WC()->cart->is_empty() ) {
			$this->clear_logged_rules();
			return;
		}

		if ( ! WC()->session ) {
			return;
		}

		$session_keys = [
			'wccs_applied_coupons',
			'wccs_applied_fees',
			'wccs_applied_shipping_discounts',
		];

		$include_tax = wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_cart' );
		$rule_args = [];
		$rule_usage = [];
		foreach ( $session_keys as $session_key ) {
			$applied_rules = WC()->session->get( $session_key );
			if ( empty( $applied_rules ) ) {
				continue;
			}

			foreach ( $applied_rules as $rule ) {
				if ( isset( $rule['id'] ) && 0 < (int) $rule['id'] ) {
					$rule_usage[ (int) $rule['id'] ] = 1;

					if ( 'order' !== $event_type ) {
						continue;
					}

					if ( isset( $rule['discount_amount'] ) && 0 < (float) $rule['discount_amount'] ) {
						$discount = 0;
						if ( isset( $rule['code'] ) ) {
							$discount = WC()->cart->get_coupon_discount_amount( $rule['code'], ! $include_tax );
						}

						$discount = 0 < $discount ? $discount : (float) $rule['discount_amount'];

						if ( 'wccs_applied_shipping_discounts' === $session_key ) {
							$rule_args[ (int) $rule['id'] ]['shipping_discounts'] = wc_format_decimal( $discount, wc_get_price_decimals() );
						} else {
							$rule_args[ (int) $rule['id'] ]['discounts'] = wc_format_decimal( $discount, wc_get_price_decimals() );
						}
					}

					if ( isset( $rule['fee_amount'] ) ) {
						if ( 0 < (float) $rule['fee_amount'] ) {
							$rule_args[ (int) $rule['id'] ]['discounts'] = wc_format_decimal( $rule['fee_amount'], wc_get_price_decimals() );
						} else {
							$rule_args[ (int) $rule['id'] ]['fees'] = wc_format_decimal( $rule['fee_amount'], wc_get_price_decimals() );
						}
					}
				}
			}
		}

		$shipping_methods = WC()->session->get( 'chosen_shipping_methods' );

		if ( is_array( $shipping_methods ) ) {
			$packages = WC()->shipping()->get_packages();

			foreach ( $shipping_methods as $package_index => $shipping_method ) {
				if ( false !== strpos( $shipping_method, 'dynamic_shipping:' ) ) {
					$rule_id = (int) str_replace( 'dynamic_shipping:', '', $shipping_method );
					if ( 0 < (int) $rule_id ) {
						$rule_usage[ (int) $rule_id ] = 1;

						if ( 'order' !== $event_type ) {
							continue;
						}

						// Check if it's a free shipping method
						if ( isset( $packages[ $package_index ]['rates'][ $shipping_method ] ) ) {
							$rate = $packages[ $package_index ]['rates'][ $shipping_method ];

							if ( 'dynamic_shipping' === $rate->method_id && (float) $rate->get_cost() <= 0 ) {
								$rule_args[ (int) $rule_id ]['free_shippings'] = 1;
							}
						}
					}
				}
			}
		}

		// Check if rules logged already.
		if ( 'add_to_cart' === $event_type || 'checkout' === $event_type ) {
			$cart_logged = WC()->session->get( 'wccs_analytics_add_to_cart_rules', [] );
			$checkout_logged = WC()->session->get( 'wccs_analytics_checkout_rules', [] );
			$logged_rules = ( 'add_to_cart' === $event_type ) ? $cart_logged : $checkout_logged;
			$impression_usage = $rule_usage;

			foreach ( $rule_usage as $rule_id => $count ) {
				$already_logged = isset( $logged_rules[ $rule_id ] ) ? $logged_rules[ $rule_id ] : 0;
				$diff = $count - $already_logged;

				$already_impression = isset( $cart_logged[ $rule_id ] ) ? $cart_logged[ $rule_id ] : 0;
				if ( ! $already_impression && isset( $checkout_logged[ $rule_id ] ) ) {
					$already_impression = $checkout_logged[ $rule_id ];
				}

				$impression_diff = $count - $already_impression;

				if ( $diff > 0 ) {
					$rule_usage[ $rule_id ] = $diff;
					$logged_rules[ $rule_id ] = $already_logged + $diff;
				} elseif ( $diff <= 0 ) {
					unset( $rule_usage[ $rule_id ] );
				}

				if ( $impression_diff > 0 ) {
					$impression_usage[ $rule_id ] = $impression_diff;
				} elseif ( $impression_diff <= 0 ) {
					unset( $impression_usage[ $rule_id ] );
				}
			}

			WC()->session->set( "wccs_analytics_{$event_type}_rules", $logged_rules );

			if ( ! empty( $impression_usage ) ) {
				$this->analytics->log_events( 'impression', $impression_usage );
			}
		}

		if ( ! empty( $rule_usage ) ) {
			$this->analytics->log_events( $event_type, $rule_usage, $rule_args );
		}
	}

	public function impression() {
		check_ajax_referer( 'wccs_single_product_nonce', 'nonce' );

		$product = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
		if ( 0 >= $product ) {
			return;
		}

		$product = wc_get_product( $product );
		if ( ! $product ) {
			return;
		}

		$this->background_analytics->push_to_queue( [ 'product' => $product->get_id() ] );

		// Lets dispatch the queue to start processing.
		$this->background_analytics->save()->dispatch();

		wp_send_json_success();
	}

	protected function log_pricing_rules( $event_type ) {
		if ( empty( $event_type ) ) {
			return;
		}

		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		}

		$include_tax = wc_tax_enabled() && 'incl' === get_option( 'woocommerce_tax_display_cart' );
		$rule_usage = [];
		$rule_args = [];
		$impression_usage = [];

		foreach ( WC()->cart->get_cart() as $cart_item ) {
			if ( ! empty( $cart_item['_wccs_applied_rules'] ) ) {
				foreach ( $cart_item['_wccs_applied_rules'] as $rule ) {
					$rule_id = $rule['id'];

					if ( 'order' === $event_type ) {
						$rule_usage[ $rule_id ] = 1;
					} else {
						$rule_usage[ $rule_id ] = isset( $rule_usage[ $rule_id ] )
							? $rule_usage[ $rule_id ] + 1
							: 1;
					}

					if ( isset( $cart_item['_ewd_auto_added_product'] ) || isset( $cart_item['_ewd_urlc_auto_added_product'] ) ) {
						$impression_usage[ $rule_id ] = isset( $impression_usage[ $rule_id ] )
							? $impression_usage[ $rule_id ] + 1
							: 1;
					}

					if ( 'order' === $event_type ) {
						if ( ! apply_filters( 'asnp_wccs_total_discounts_process_cart_item', true, $cart_item ) ) {
							continue;
						}

						// Compatibility with the Custom Product Boxes plugin.
						if ( '' === $cart_item['line_subtotal'] || ! empty( $cart_item['cpb_custom_parent_id'] ) ) {
							continue;
						}

						$revenue = $include_tax ?
							apply_filters( 'wccs_cart_item_line_subtotal', (float) $cart_item['line_subtotal'], $cart_item ) +
							apply_filters( 'wccs_cart_item_line_subtotal_tax', (float) $cart_item['line_subtotal_tax'], $cart_item ) :
							apply_filters( 'wccs_cart_item_line_subtotal', (float) $cart_item['line_subtotal'], $cart_item );

						$main_line_subtotal = isset( $cart_item['_wccs_main_price'] ) &&
							( ! isset( $cart_item['_wccs_main_sale_price'] ) || $cart_item['_wccs_main_sale_price'] != $cart_item['_wccs_main_price'] ) ?
							(float) WCCS_Cart_Item_Helpers::get_main_price( $cart_item ) * $cart_item['quantity'] :
							(float) $cart_item['data']->get_regular_price() * $cart_item['quantity'];
						if ( $include_tax ) {
							$main_line_subtotal = wc_get_price_including_tax( $cart_item['data'], array( 'qty' => 1, 'price' => $main_line_subtotal ) );
						} else {
							$main_line_subtotal = wc_get_price_excluding_tax( $cart_item['data'], array( 'qty' => 1, 'price' => $main_line_subtotal ) );
						}

						$discount = wc_format_decimal( $main_line_subtotal - $revenue, wc_get_price_decimals() );
						$revenue = wc_format_decimal( $revenue, wc_get_price_decimals() );

						if ( ! isset( $rule_args[ $rule_id ] ) ) {
							$rule_args[ $rule_id ] = [
								'revenue' => $revenue,
								'discounts' => 0 < $discount ? $discount : 0,
								'fees' => 0 > $discount ? $discount * -1 : 0,
								'items_discounted' => 1,
							];
						} else {
							$rule_args[ $rule_id ]['revenue'] += $revenue;
							$rule_args[ $rule_id ]['items_discounted'] += 1;

							if ( 0 < $discount ) {
								$rule_args[ $rule_id ]['discounts'] += $discount;
							} else {
								$rule_args[ $rule_id ]['fees'] += $discount * -1;
							}
						}
					}
				}
			}
		}

		// Check if rules logged already.
		if ( 'add_to_cart' === $event_type || 'checkout' === $event_type ) {
			$cart_logged = WC()->session->get( 'wccs_analytics_add_to_cart_pricing_rules', [] );
			$checkout_logged = WC()->session->get( 'wccs_analytics_checkout_pricing_rules', [] );
			$logged_rules = ( 'add_to_cart' === $event_type ) ? $cart_logged : $checkout_logged;

			foreach ( $rule_usage as $rule_id => $count ) {
				$already_logged = isset( $logged_rules[ $rule_id ] ) ? $logged_rules[ $rule_id ] : 0;
				$diff = $count - $already_logged;

				if ( $diff > 0 ) {
					$rule_usage[ $rule_id ] = $diff;
					$logged_rules[ $rule_id ] = $already_logged + $diff;
				} elseif ( $diff <= 0 ) {
					unset( $rule_usage[ $rule_id ] );
				}

				if ( isset( $impression_usage[ $rule_id ] ) ) {
					$already_impression = isset( $cart_logged[ $rule_id ] ) ? $cart_logged[ $rule_id ] : 0;
					if ( ! $already_impression && isset( $checkout_logged[ $rule_id ] ) ) {
						$already_impression = $checkout_logged[ $rule_id ];
					}

					$impression_diff = $count - $already_impression;
					if ( $impression_diff > 0 ) {
						$impression_usage[ $rule_id ] = $impression_diff;
					} elseif ( $impression_diff <= 0 ) {
						unset( $impression_usage[ $rule_id ] );
					}
				}
			}

			WC()->session->set( "wccs_analytics_{$event_type}_pricing_rules", $logged_rules );

			if ( ! empty( $impression_usage ) ) {
				$this->analytics->log_events( 'impression', $impression_usage );
			}
		}

		if ( ! empty( $rule_usage ) ) {
			$this->analytics->log_events( $event_type, $rule_usage, $rule_args );
		}
	}

	public function on_removed_coupon( $coupon_code ) {
		$applied_coupons = WC()->session->get( 'wccs_applied_coupons', [] );
		if ( empty( $applied_coupons ) || ! isset( $applied_coupons[ $coupon_code ] ) ) {
			return;
		}

		$coupon = $applied_coupons[ $coupon_code ];
		if ( ! isset( $coupon['id'] ) || 0 >= (int) $coupon['id'] ) {
			return;
		}

		// Do not log rejections for automatic coupons.
		if ( isset( $coupon['manual'] ) && 0 == $coupon['manual'] ) {
			return;
		}

		$rule_id = (int) $coupon['id'];

		$this->analytics->log_events( 'rejection', [ $rule_id => 1 ] );

		$cart_rules = WC()->session->get( 'wccs_analytics_add_to_cart_rules', [] );
		$checkout_rules = WC()->session->get( 'wccs_analytics_checkout_rules', [] );

		if ( empty( $cart_rules ) && empty( $checkout_rules ) ) {
			return;
		}

		if ( isset( $cart_rules[ $rule_id ] ) ) {
			$cart_rules[ $rule_id ] -= 1;
			if ( 0 >= $cart_rules[ $rule_id ] ) {
				unset( $cart_rules[ $rule_id ] );
			}
		}

		if ( isset( $checkout_rules[ $rule_id ] ) ) {
			$checkout_rules[ $rule_id ] -= 1;
			if ( 0 >= $checkout_rules[ $rule_id ] ) {
				unset( $checkout_rules[ $rule_id ] );
			}
		}

		WC()->session->set( 'wccs_analytics_add_to_cart_rules', $cart_rules );
		WC()->session->set( 'wccs_analytics_checkout_rules', $checkout_rules );
	}

	public function clear_logged_rules() {
		if ( ! WC()->session ) {
			return;
		}

		WC()->session->set( 'wccs_analytics_add_to_cart_rules', [] );
		WC()->session->set( 'wccs_analytics_add_to_cart_pricing_rules', [] );
		WC()->session->set( 'wccs_analytics_checkout_rules', [] );
		WC()->session->set( 'wccs_analytics_checkout_pricing_rules', [] );

		if ( ! headers_sent() ) {
			wc_setcookie( 'asnp_wccs_analytics_cart_hash', 0, time() - HOUR_IN_SECONDS );
			unset( $_COOKIE['asnp_wccs_analytics_cart_hash'] );
		}
	}

	protected function is_cart_updated() {
		$cart_hash = isset( $_COOKIE['woocommerce_cart_hash'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['woocommerce_cart_hash'] ) ) : '';
		$analytics_hash = isset( $_COOKIE['asnp_wccs_analytics_cart_hash'] ) ? sanitize_text_field( wp_unslash( $_COOKIE['asnp_wccs_analytics_cart_hash'] ) ) : '';

		return $analytics_hash != $cart_hash;
	}

}
