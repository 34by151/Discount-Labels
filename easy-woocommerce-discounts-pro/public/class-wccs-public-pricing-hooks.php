<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Public_Pricing_Hooks extends WCCS_Public_Controller {

	protected $loader;

	protected $pricing;

	protected $product_pricing;

	protected $discounted_products;

	public $applied_pricings = false;

	/**
	 * An array contains cart_item_key and its associated discounted prices.
	 *
	 * @var array
	 */
	public $applied_discounts = array();

	public function __construct( WCCS_Loader $loader ) {
		$this->loader = $loader;
	}

	public function enable_hooks() {
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'remove_pricings' ) );
		add_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_pricings' ), 9999 );
	}

	public function disable_hooks() {
		remove_action( 'woocommerce_before_calculate_totals', array( $this, 'remove_pricings' ) );
		remove_action( 'woocommerce_before_calculate_totals', array( $this, 'apply_pricings' ) );
	}

	public function init() {
		$this->loader->add_action( 'woocommerce_cart_loaded_from_session', $this, 'cart_loaded_from_session' );
		$this->loader->add_action( 'woocommerce_cart_loaded_from_session', $this, 'enable_price_and_badge_hooks' );
		$this->loader->add_action( 'woocommerce_init', $this, 'rest_api' );
		$this->loader->add_action( 'wccs_virtual_cart_before_calculate_totals', $this, 'remove_pricings' );
		$this->loader->add_action( 'woocommerce_before_calculate_totals', $this, 'remove_pricings' );
		$this->loader->add_action( 'woocommerce_before_calculate_totals', $this, 'apply_pricings', 9999 );
		$this->loader->add_action( 'woocommerce_check_cart_items', $this, 'show_discount_page' );
		$this->loader->add_action( 'woocommerce_add_to_cart', $this, 'reset_applied_pricings' );
		$this->loader->add_action( 'woocommerce_cart_item_removed', $this, 'reset_applied_pricings' );
		$this->loader->add_action( 'woocommerce_checkout_update_order_review', $this, 'reset_applied_pricings' );
		$this->loader->add_action( 'woocommerce_after_cart_item_quantity_update', $this, 'reset_applied_pricings' );
		$this->loader->add_filter( 'woocommerce_cart_item_price', $this, 'cart_item_price', 10, 3 );
		$this->loader->add_filter( 'woocommerce_widget_cart_item_quantity', $this, 'widget_cart_item_quantity', 10, 3 );

		// Fix mini cart issue with subtotal conditions of simple pricing rules.
		$this->loader->add_action( 'woocommerce_before_mini_cart_contents', $this, 'calculate_cart_totals', 10 );

		if ( (int) WCCS()->settings->get_setting( 'display_quantity_table', 1 ) ) {
			$this->quantity_table_hooks();
		}

		if ( (int) WCCS()->settings->get_setting( 'purchase_x_receive_y_message_display', 1 ) ) {
			$this->purchase_message_hooks();
		}

		if ( (int) WCCS()->settings->get_setting( 'display_countdown_timer', 0 ) ) {
			$this->countdown_timer_hooks();
		}

		if ( (int) WCCS()->settings->get_setting( 'live_pricing_display', 1 ) || (int) WCCS()->settings->get_setting( 'live_pricing_total_display', 1 ) ) {
			$this->live_pricing_hooks();
		}

		if ( (int) WCCS()->settings->get_setting( 'set_min_quantity', 0 ) ) {
			$this->loader->add_filter( 'woocommerce_quantity_input_args', $this, 'set_product_min_quantity', 10, 2 );
			$this->loader->add_filter( 'woocommerce_available_variation', $this, 'set_variation_min_quantity', 10, 3 );
		}

		$apply_mode = WCCS()->settings->get_setting( 'product_pricing_discount_apply_method', 'sum' );
		if (
			(int) WCCS()->settings->get_setting( 'product_pricing_consider_sale_price', 0 ) &&
			'regular_price' === WCCS()->settings->get_setting( 'on_sale_products_price', 'on_sale_price' ) &&
			( 'max' === $apply_mode || 'min' === $apply_mode )
		) {
			$this->loader->add_filter( 'wccs_apply_discounted_price_on_cart_item', $this, 'use_discounted_price_or_sale_price', 10, 4 );
			$this->loader->add_filter( 'wccs_live_price_apply_discounted_price_on_cart_item', $this, 'use_discounted_price_or_sale_price', 10, 4 );
			$this->loader->add_filter( 'wccs_public_product_pricing_apply_adjusted_price', $this, 'use_discounted_price_or_sale_price', 10, 3 );
		}

		if ( (int) WCCS()->settings->get_setting( 'show_free_gift_badge', 1 ) ) {
			$this->loader->add_filter( 'woocommerce_cart_item_name', $this, 'cart_item_name', 999, 2 );
		}
	}

	public function cart_loaded_from_session( $cart ) {
		if ( ! WCCS()->is_request( 'frontend' ) ) {
			return;
		}

		$cart_contents = $cart->get_cart();
		foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			// It is a cart item product so do not override its price.
			if ( isset( $cart->cart_contents[ $cart_item_key ]['data'] ) ) {
				WCCS()->custom_props->set_prop( $cart->cart_contents[ $cart_item_key ]['data'], 'wccs_is_cart_item', true );
			}
		}
	}

	public function enable_change_price_hooks() {
		if ( ! WCCS_Helpers::should_change_display_price() ) {
			return;
		}

		WCCS()->WCCS_Product_Price_Replace->set_should_replace_prices( true )
			->set_change_regular_price( WCCS_Helpers::should_change_display_price_html() ? false : true )
			->enable_hooks();
	}

	public function enable_price_and_badge_hooks() {
		if ( ! WCCS()->is_request( 'frontend' ) ) {
			return;
		}

		$this->enable_change_price_hooks();

		if ( WCCS_Helpers::should_change_display_price_html( 'all' ) ) {
			add_filter( 'woocommerce_get_price_html', array( &$this, 'get_price_html' ), 10, 2 );
		}

		$sale_badge = WCCS()->settings->get_setting( 'sale_badge', array( 'simple' => '1' ) );
		if ( empty( $sale_badge['simple'] ) ) {
			add_filter( 'woocommerce_product_is_on_sale', array( &$this, 'remove_sale_flash' ), 10, 999 );
		}

		if ( ! empty( $sale_badge ) ) {
			if ( ! empty( $sale_badge['simple'] ) && WCCS_Helpers::should_change_display_price_html() ) {
				if ( 'sale' === WCCS()->settings->get_setting( 'sale_badge_type', 'sale' ) ) {
					add_filter( 'woocommerce_product_is_on_sale', array( &$this, 'woocommerce_product_is_on_sale' ), 10, 2 );
					unset( $sale_badge['simple'] );
				} elseif ( 'discount' === WCCS()->settings->get_setting( 'sale_badge_type', 'sale' ) ) {
					add_filter( 'woocommerce_sale_flash', array( &$this, 'percentage_sale_badge' ), 10, 3 );
				}
			}

			if ( ! empty( $sale_badge ) ) {
				$this->sale_badge_hooks();
			}
		}
	}

	public function rest_api() {
		if ( ! WCCS_Helpers::wc_is_rest_api_request() ) {
			return;
		}

		$this->enable_change_price_hooks();
	}

	public function get_price_html( $price, $product ) {
		if ( empty( $price ) ) {
			return $price;
		}

		if ( ! apply_filters( 'asnp_wccs_apply_get_price_html', true, $price, $product ) ) {
			return $price;
		}

		$product_pricing = $this->get_product_pricing( $product );
		return $product_pricing->get_price_html( $price );
	}

	public function apply_pricings( $cart = null ) {
		if ( ! $this->should_apply_pricing() ) {
			return;
		}

		$this->applied_discounts = array();

		$cart = $cart && is_a( $cart, 'WC_Cart' ) ? $cart : WC()->cart;
		if ( $cart->is_empty() ) {
			return;
		}

		$this->pricing = isset( $this->pricing ) ? $this->pricing : WCCS()->pricing;
		$this->pricing->reset_cache();

		$cart_contents = $cart->get_cart();
		if ( ! empty( $cart_contents ) ) {
			$pricing_cache = new WCCS_Cart_Pricing_Cache();
		}

		do_action( 'wccs_public_pricing_hooks_before_apply_pricings', $this );

		foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			// It is a cart item product so do not override its price.
			if ( isset( $cart->cart_contents[ $cart_item_key ]['data'] ) ) {
				WCCS()->custom_props->set_prop( $cart->cart_contents[ $cart_item_key ]['data'], 'wccs_is_cart_item', true );
			}

			$product = $cart_item['data'];
			if ( isset( $cart_item['_wccs_main_price'] ) ) {
				$product->set_price( $cart_item['_wccs_main_price'] );
				if ( isset( $cart_item['_wccs_main_sale_price'] ) ) {
					$product->set_sale_price( $cart_item['_wccs_main_sale_price'] );
				}
				unset( $cart->cart_contents[ $cart_item_key ]['_wccs_main_price'] );
				unset( $cart->cart_contents[ $cart_item_key ]['_wccs_main_sale_price'] );
				unset( $cart->cart_contents[ $cart_item_key ]['_wccs_main_display_price'] );
				unset( $cart->cart_contents[ $cart_item_key ]['_wccs_before_discounted_price'] );
				unset( $cart->cart_contents[ $cart_item_key ]['_wccs_discounted_price'] );
				unset( $cart->cart_contents[ $cart_item_key ]['_wccs_prices'] );
				unset( $cart->cart_contents[ $cart_item_key ]['_wccs_prices_main'] );
				unset( $cart->cart_contents[ $cart_item_key ]['_wccs_applied_rules'] );
			}

			if ( ! apply_filters( 'wccs_apply_pricing_on_cart_item', true, $cart_item ) ) {
				continue;
			}

			$pricing_item          = new WCCS_Public_Cart_Item_Pricing( $cart_item_key, $cart_item, $this->pricing, '', null, $pricing_cache );
			$item_discounted_price = $pricing_item->get_price();
			if ( false === $item_discounted_price || $item_discounted_price < 0 ) {
				continue;
			}

			$item_discounted_price = apply_filters( 'wccs_cart_item_discounted_price', $item_discounted_price, $cart_item );

			if ( ! apply_filters( 'wccs_apply_discounted_price_on_cart_item', true, $item_discounted_price, $cart_item['data'], $cart_item ) ) {
				continue;
			}

			do_action( 'wccs_apply_pricing_before_set_item_prices', $cart_item, $pricing_item, $item_discounted_price );

			$cart->cart_contents[ $cart_item_key ]['_wccs_main_price']              = $this->get_cart_item_main_price( $cart_item, $pricing_item->product );
			$cart->cart_contents[ $cart_item_key ]['_wccs_main_display_price']      = $this->get_cart_item_main_display_price( $cart_item, $pricing_item->product );
			$cart->cart_contents[ $cart_item_key ]['_wccs_before_discounted_price'] = $this->get_cart_item_before_discounted_price( $cart_item, $product );
			$cart->cart_contents[ $cart_item_key ]['_wccs_discounted_price']        = wc_format_decimal( $item_discounted_price );
			$cart->cart_contents[ $cart_item_key ]['_wccs_prices']                  = apply_filters( 'wccs_cart_item_prices', $pricing_item->get_prices(), $cart_item );
			// Do not apply any filter on _wccs_prices_main.
			$cart->cart_contents[ $cart_item_key ]['_wccs_prices_main']             = $pricing_item->get_prices();

			// Set applied rules.
			$applied_rules = $pricing_item->get_applied_rules();
			if ( ! empty( $applied_rules ) ) {
				$applied_rules = array_map( function( $rule ) {
					return [
						'id'            => $rule['id'],
						'name'          => $rule['name'],
						'description'   => ! empty( $rule['description'] ) ? $rule['description'] : '',
						'type'          => isset( $rule['type'] ) ? $rule['type'] : 'discount',
						'discount_type' => isset( $rule['discount_type'] ) ? $rule['discount_type'] : '',
						'discount'      => isset( $rule['discount'] ) ? $rule['discount'] : 0,
						'apply_mode'    => isset( $rule['apply_mode'] ) ? $rule['apply_mode'] : '',
						'mode'          => isset( $rule['mode'] ) ? $rule['mode'] : '',
						'amount'        => isset( $rule['amount'] ) ? $rule['amount'] : 0,
					];
				}, $applied_rules );
			}
			$cart->cart_contents[ $cart_item_key ]['_wccs_applied_rules'] = $applied_rules;

			do_action( 'wccs_apply_pricing_after_set_item_prices', $cart_item, $pricing_item, $item_discounted_price );

			$this->applied_discounts[ $cart_item_key ] = array(
				'discounted_price' => $item_discounted_price,
			);

			// Setting sale price.
			if ( $item_discounted_price < $pricing_item->get_base_price() ) {
				$cart->cart_contents[ $cart_item_key ]['_wccs_main_sale_price'] = apply_filters( 'wccs_cart_item_main_sale_price', WCCS()->product_helpers->wc_get_sale_price( $pricing_item->product ), $cart_item );
				$product->set_sale_price( $item_discounted_price );
				$this->applied_discounts[ $cart_item_key ]['discounted_sale_price'] = $item_discounted_price;
			}

			$product->set_price( $item_discounted_price );
		}

		do_action( 'wccs_public_pricing_hooks_after_apply_pricings', $this );

		$this->applied_pricings = true;
	}

	/**
	 * Reset applied pricings.
	 *
	 * @since  2.8.0
	 *
	 * @return void
	 */
	public function reset_applied_pricings() {
		if ( $this->applied_pricings ) {
			$this->applied_pricings = false;
			$this->pricing->reset_cache();
			// Enable remove pricing hook after reset applied pricings.
			if ( ! has_action( 'woocommerce_before_calculate_totals', array( &$this, 'remove_pricings' ) ) ) {
				add_action( 'woocommerce_before_calculate_totals', array( &$this, 'remove_pricings' ) );
			}
		}
	}

	public function remove_pricings( $cart = null ) {
		$cart = $cart && is_a( $cart, 'WC_Cart' ) ? $cart : WC()->cart;
		if ( $cart->is_empty() ) {
			return;
		}

		$cart_contents = $cart->get_cart();
		foreach ( $cart_contents as $cart_item_key => $cart_item ) {
			if ( isset( $cart_item['_wccs_main_price'] ) ) {
				$cart_item['data']->set_price( $cart_item['_wccs_main_price'] );
				unset( $cart->cart_contents[ $cart_item_key ]['_wccs_main_price'] );
				unset( $cart->cart_contents[ $cart_item_key ]['_wccs_main_display_price'] );
				unset( $cart->cart_contents[ $cart_item_key ]['_wccs_before_discounted_price'] );
				unset( $cart->cart_contents[ $cart_item_key ]['_wccs_discounted_price'] );
				unset( $cart->cart_contents[ $cart_item_key ]['_wccs_prices'] );
				unset( $cart->cart_contents[ $cart_item_key ]['_wccs_prices_main'] );
				unset( $cart->cart_contents[ $cart_item_key ]['_wccs_applied_rules'] );
			}

			if ( isset( $cart_item['_wccs_main_sale_price'] ) ) {
				$cart_item['data']->set_sale_price( $cart_item['_wccs_main_sale_price'] );
				unset( $cart->cart_contents[ $cart_item_key ]['_wccs_main_sale_price'] );
			}

			// It is a cart item product so do not override its price.
			if ( isset( $cart->cart_contents[ $cart_item_key ]['data'] ) ) {
				WCCS()->custom_props->set_prop( $cart->cart_contents[ $cart_item_key ]['data'], 'wccs_is_cart_item', true );
			}
		}
	}

	protected function should_apply_pricing() {
		return apply_filters( 'wccs_should_apply_pricing', true, $this );
	}

	/**
	 * Showing discount page url in the cart and checkout page.
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	public function show_discount_page() {
		$wccs = WCCS();

		$show_discount_page = $wccs->settings->get_setting( 'show_discount_page', 'discounts_available' );
		if ( ! in_array( $show_discount_page, array( 'always', 'discounts_available' ) ) ) {
			return;
		}

		$discount_page = (int) $wccs->settings->get_setting( 'discount_page', 0 );
		$discount_page = $discount_page > 0 ? get_post( $discount_page ) : null;
		if ( ! $discount_page || 'publish' !== $discount_page->post_status ) {
			return;
		}

		$discount_page_message = sprintf( __( 'Visit our %1$s to view new discounted products based on your cart.', 'easy-woocommerce-discounts' ), '[discount_page]' );
		$discount_page_title   = __( 'Discount Page', 'easy-woocommerce-discounts' );
		if ( (int) $wccs->settings->get_setting( 'localization_enabled', 1 ) ) {
			$discount_page_message = $wccs->settings->get_setting( 'cart_discount_page_message', $discount_page_message );
			$discount_page_title   = $wccs->settings->get_setting( 'discount_page_title', $discount_page_title );
		}

		if ( ! empty( $discount_page_message ) && ! empty( $discount_page_title ) ) {
			$discount_page_message = str_replace( '[discount_page]', '<a href="' . esc_url( get_permalink( $discount_page->ID ) ) . '">' . sanitize_text_field( $discount_page_title ) . '</a>', $discount_page_message );
			if ( 'always' === $show_discount_page ) {
				wc_add_notice( $discount_page_message );
			} elseif ( 'discounts_available' === $show_discount_page ) {
				$this->pricing  = isset( $this->pricing ) ? $this->pricing : $wccs->pricing;
				$pricings = $this->pricing->get_pricings();
				if ( ! empty( $pricings['simple'] ) || ! empty( $pricings['bulk'] ) || ! empty( $pricings['purchase'] ) ) {
					wc_add_notice( $discount_page_message );
				}
			}
		}
	}

	public function cart_item_price( $price, $cart_item, $cart_item_key ) {
		if ( ! isset( $cart_item['_wccs_discounted_price'] ) || ! isset( $cart_item['_wccs_before_discounted_price'] ) || ! isset( $cart_item['_wccs_main_price'] ) ) {
			return $price;
		}

		if ( ! apply_filters( 'wccs_change_cart_item_price', true ) ) {
			return $price;
		}

		if ( isset( $cart_item['asnp_wepb_items'] ) || isset( $cart_item['asnp_wepb_parent_id'] ) ) {
			return $price;
		}

		if ( isset( $cart_item['_wccs_main_sale_price'] ) && $cart_item['_wccs_main_sale_price'] == $cart_item['_wccs_main_price'] ) {
			$before_discounted_price = apply_filters(
				'wccs_cart_item_price_before_discounted_price',
				WCCS()->cart->get_product_price( $cart_item['data'], array( 'price' => $cart_item['data']->get_regular_price(), 'qty' => 1 ) ),
				$cart_item,
				$cart_item_key,
				$price
			);
			$main_price = (float) $cart_item['data']->get_regular_price();
		} else {
			$before_discounted_price = apply_filters(
				'wccs_cart_item_price_before_discounted_price',
				$cart_item['_wccs_before_discounted_price'],
				$cart_item,
				$cart_item_key,
				$price
			);
			$main_price = (float) $cart_item['_wccs_main_price'];
		}

		if ( $main_price > (float) $cart_item['_wccs_discounted_price'] ) {
			$content = '<del>' . $before_discounted_price . '</del> <ins>' . $price . '</ins>';
			if ( ! empty( $cart_item['_wccs_prices'] ) && 1 < count( $cart_item['_wccs_prices'] ) ) {
				$content = '<div class="wccs_prices">';
				foreach ( $cart_item['_wccs_prices'] as $price_str => $quantity ) {
					if (
						(float) $price_str != (float) $cart_item['_wccs_main_display_price'] ||
						( isset( $cart_item['_wccs_main_sale_price'] ) && $cart_item['_wccs_main_sale_price'] == $cart_item['_wccs_main_price'] )
					) {
						$content .= '<div class="wccs_prices_price"><span class="wccs_prices_price_container"><del>' . $before_discounted_price . '</del> <ins>' . apply_filters( 'wccs_cart_item_price_prices_price', wc_price( $price_str ), $price_str, $cart_item, $cart_item_key, $price ) . '</ins></span>';
					} else {
						$content .= '<div class="wccs_prices_price"><span class="wccs_prices_price_container"><ins>' . $before_discounted_price . '</ins></span>';
					}

					$content .= '<span class="wccs_prices_quantity_container"><strong><span class="wccs_prices_times">&times;</span> <span class="wccs_prices_quantity">' . $quantity . '</span></strong></span></div>';
				}
				$content .= '</div>';
			}

			return apply_filters( 'wccs_cart_item_price', $content, $price, $cart_item, $cart_item_key );
		}

		return apply_filters( 'wccs_cart_item_price', $price, $price, $cart_item, $cart_item_key );
	}

	public function widget_cart_item_quantity( $quantity, $cart_item, $cart_item_key ) {
		if ( ! isset( $cart_item['_wccs_discounted_price'] ) || ! isset( $cart_item['_wccs_before_discounted_price'] ) || ! isset( $cart_item['_wccs_main_price'] ) ) {
			return $quantity;
		}

		if ( (float) $cart_item['_wccs_main_price'] > (float) $cart_item['_wccs_discounted_price'] ) {
			if ( ! empty( $cart_item['_wccs_prices'] ) && 1 < count( $cart_item['_wccs_prices'] ) ) {
				$quantity = str_replace( $cart_item['quantity'] . ' &times; ', '', $quantity );
			}
		}

		return $quantity;
	}

	public function calculate_cart_totals() {
		if ( ! WC()->cart || WC()->cart->is_empty() ) {
			return;
		} elseif ( (int) WCCS()->settings->get_setting( 'disable_calculate_cart_totals', 0 ) ) {
			return;
		} elseif ( ! apply_filters( 'wccs_calculate_cart_totals', true ) ) {
			return;
		}

		do_action( 'wccs_before_calculate_cart_totals' );
		WC()->cart->calculate_totals();
		do_action( 'wccs_after_calculate_cart_totals' );
	}

	protected function quantity_table_hooks() {
		$position = WCCS()->settings->get_setting( 'quantity_table_position', 'before_add_to_cart_button' );

		switch ( $position ) {
			case 'before_add_to_cart_button' :
			case 'after_add_to_cart_button' :
				$add_to_cart_priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart' );
				if ( 'before_add_to_cart_button' === $position ) {
					$add_to_cart_priority ?
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_bulk_pricing_table', $add_to_cart_priority - 1 ) :
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_bulk_pricing_table', 29 );
				} elseif ( 'after_add_to_cart_button' === $position ) {
					$add_to_cart_priority ?
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_bulk_pricing_table', $add_to_cart_priority + 1 ) :
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_bulk_pricing_table', 31 );
				}
				break;

			case 'before_add_to_cart_form':
				$this->loader->add_action( 'woocommerce_before_add_to_cart_form', $this, 'display_bulk_pricing_table' );
				break;

			case 'after_add_to_cart_form':
				$this->loader->add_action( 'woocommerce_after_add_to_cart_form', $this, 'display_bulk_pricing_table' );
				break;

			case 'before_excerpt' :
			case 'after_excerpt' :
				$excerpt_priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt' );
				if ( 'before_excerpt' === $position ) {
					$excerpt_priority ?
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_bulk_pricing_table', $excerpt_priority - 1 ) :
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_bulk_pricing_table', 19 );
				} elseif ( 'after_excerpt' === $position ) {
					$excerpt_priority ?
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_bulk_pricing_table', $excerpt_priority + 1 ) :
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_bulk_pricing_table', 21 );
				}
				break;

			case 'after_product_meta' :
				$meta_priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta' );
				$meta_priority ?
					$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_bulk_pricing_table', $meta_priority + 1 ) :
					$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_bulk_pricing_table', 41 );
				break;

			case 'in_modal' :
				break;

			default :
				break;
		}
	}

	public function display_bulk_pricing_table() {
		global $product;
		if ( ! $product ) {
			return;
		}

		$product_pricing = $this->get_product_pricing( $product );
		$product_pricing->bulk_pricing_table();
	}

	protected function purchase_message_hooks() {
		$position = WCCS()->settings->get_setting( 'purchase_x_receive_y_message_position', 'before_add_to_cart_button' );

		switch ( $position ) {
			case 'before_add_to_cart_button' :
			case 'after_add_to_cart_button' :
				$add_to_cart_priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart' );
				if ( 'before_add_to_cart_button' === $position ) {
					$add_to_cart_priority ?
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_purchase_message', $add_to_cart_priority - 1 ) :
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_purchase_message', 29 );
				} elseif ( 'after_add_to_cart_button' === $position ) {
					$add_to_cart_priority ?
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_purchase_message', $add_to_cart_priority + 1 ) :
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_purchase_message', 31 );
				}
				break;

			case 'before_add_to_cart_form':
				$this->loader->add_action( 'woocommerce_before_add_to_cart_form', $this, 'display_purchase_message' );
				break;

			case 'after_add_to_cart_form':
				$this->loader->add_action( 'woocommerce_after_add_to_cart_form', $this, 'display_purchase_message' );
				break;

			case 'before_excerpt' :
			case 'after_excerpt' :
				$excerpt_priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt' );
				if ( 'before_excerpt' === $position ) {
					$excerpt_priority ?
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_purchase_message', $excerpt_priority - 1 ) :
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_purchase_message', 19 );
				} elseif ( 'after_excerpt' === $position ) {
					$excerpt_priority ?
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_purchase_message', $excerpt_priority + 1 ) :
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_purchase_message', 21 );
				}
				break;

			case 'after_product_meta' :
				$meta_priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta' );
				$meta_priority ?
					$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_purchase_message', $meta_priority + 1 ) :
					$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_purchase_message', 41 );
				break;

			case 'in_modal' :
				break;

			default :
				break;
		}
	}

	public function display_purchase_message() {
		global $product;
		if ( ! $product ) {
			return;
		}

		if ( ! apply_filters( 'wccs_display_purchase_message', true, $product ) ) {
			return;
		}

		$product_pricing = $this->get_product_pricing( $product );
		$product_pricing->purchase_message();
	}

	protected function live_pricing_hooks() {
		$position = WCCS()->settings->get_setting( 'live_pricing_position', 'before_add_to_cart_button' );

		switch ( $position ) {
			case 'before_add_to_cart_button' :
			case 'after_add_to_cart_button' :
				$add_to_cart_priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart' );
				if ( 'before_add_to_cart_button' === $position ) {
					$add_to_cart_priority ?
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_live_pricing', $add_to_cart_priority - 1 ) :
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_live_pricing', 29 );
				} elseif ( 'after_add_to_cart_button' === $position ) {
					$add_to_cart_priority ?
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_live_pricing', $add_to_cart_priority + 1 ) :
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_live_pricing', 31 );
				}
				break;

			case 'before_add_to_cart_form':
				$this->loader->add_action( 'woocommerce_before_add_to_cart_form', $this, 'display_live_pricing' );
				break;

			case 'after_add_to_cart_form':
				$this->loader->add_action( 'woocommerce_after_add_to_cart_form', $this, 'display_live_pricing' );
				break;

			case 'before_excerpt' :
			case 'after_excerpt' :
				$excerpt_priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt' );
				if ( 'before_excerpt' === $position ) {
					$excerpt_priority ?
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_live_pricing', $excerpt_priority - 1 ) :
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_live_pricing', 19 );
				} elseif ( 'after_excerpt' === $position ) {
					$excerpt_priority ?
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_live_pricing', $excerpt_priority + 1 ) :
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_live_pricing', 21 );
				}
				break;

			case 'after_product_meta' :
				$meta_priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta' );
				$meta_priority ?
					$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_live_pricing', $meta_priority + 1 ) :
					$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_live_pricing', 41 );
				break;

			default :
				break;
		}
	}

	public function display_live_pricing() {
		$this->render_view( 'product-pricing.live-price', array( 'controller' => $this ) );
	}

	protected function countdown_timer_hooks() {
		$position = WCCS()->settings->get_setting( 'countdown_timer_position', 'before_add_to_cart_button' );

		switch ( $position ) {
			case 'before_add_to_cart_button' :
			case 'after_add_to_cart_button' :
				$add_to_cart_priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart' );
				if ( 'before_add_to_cart_button' === $position ) {
					$add_to_cart_priority ?
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_countdown_timer', $add_to_cart_priority - 1 ) :
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_countdown_timer', 29 );
				} elseif ( 'after_add_to_cart_button' === $position ) {
					$add_to_cart_priority ?
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_countdown_timer', $add_to_cart_priority + 1 ) :
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_countdown_timer', 31 );
				}
				break;

			case 'before_add_to_cart_form':
				$this->loader->add_action( 'woocommerce_before_add_to_cart_form', $this, 'display_countdown_timer' );
				break;

			case 'after_add_to_cart_form':
				$this->loader->add_action( 'woocommerce_after_add_to_cart_form', $this, 'display_countdown_timer' );
				break;

			case 'before_excerpt' :
			case 'after_excerpt' :
				$excerpt_priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_excerpt' );
				if ( 'before_excerpt' === $position ) {
					$excerpt_priority ?
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_countdown_timer', $excerpt_priority - 1 ) :
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_countdown_timer', 19 );
				} elseif ( 'after_excerpt' === $position ) {
					$excerpt_priority ?
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_countdown_timer', $excerpt_priority + 1 ) :
						$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_countdown_timer', 21 );
				}
				break;

			case 'after_product_meta' :
				$meta_priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_meta' );
				$meta_priority ?
					$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_countdown_timer', $meta_priority + 1 ) :
					$this->loader->add_action( 'woocommerce_single_product_summary', $this, 'display_countdown_timer', 41 );
				break;

			default :
				break;
		}
	}

	public function display_countdown_timer() {
		$this->render_view( 'product-pricing.countdown-timer', array( 'controller' => $this ) );
	}

	protected function sale_badge_hooks() {
		$loop_position = WCCS()->settings->get_setting( 'loop_sale_badge_position', 'before_shop_loop_item_thumbnail' );
		switch ( $loop_position ) {
			case 'before_shop_loop_item_thumbnail':
				$priority = has_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail' );
				if ( $priority ) {
					add_action( 'woocommerce_before_shop_loop_item_title', array( &$this, 'display_sale_badge' ), $priority - 1 );
				} else {
					add_action( 'woocommerce_before_shop_loop_item_title', array( &$this, 'display_sale_badge' ), 9 );
				}
				break;

			case 'after_shop_loop_item_thumbnail':
				$priority = has_action( 'woocommerce_before_shop_loop_item_title', 'woocommerce_template_loop_product_thumbnail' );
				if ( $priority ) {
					add_action( 'woocommerce_before_shop_loop_item_title', array( &$this, 'display_sale_badge' ), $priority + 1 );
				} else {
					add_action( 'woocommerce_before_shop_loop_item_title', array( &$this, 'display_sale_badge' ), 11 );
				}
				break;

			case 'before_shop_loop_item_title':
				$priority = has_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title' );
				if ( $priority ) {
					add_action( 'woocommerce_shop_loop_item_title', array( &$this, 'display_sale_badge' ), $priority - 1 );
				} else {
					add_action( 'woocommerce_shop_loop_item_title', array( &$this, 'display_sale_badge' ), 9 );
				}
				break;

			case 'after_shop_loop_item_title':
				$priority = has_action( 'woocommerce_shop_loop_item_title', 'woocommerce_template_loop_product_title' );
				if ( $priority ) {
					add_action( 'woocommerce_shop_loop_item_title', array( &$this, 'display_sale_badge' ), $priority + 1 );
				} else {
					add_action( 'woocommerce_shop_loop_item_title', array( &$this, 'display_sale_badge' ), 10 );
				}
				break;

			case 'before_shop_loop_item_rating':
				$priority = has_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating' );
				if ( $priority ) {
					add_action( 'woocommerce_after_shop_loop_item_title', array( &$this, 'display_sale_badge' ), $priority - 1 );
				} else {
					add_action( 'woocommerce_after_shop_loop_item_title', array( &$this, 'display_sale_badge' ), 4 );
				}
				break;

			case 'after_shop_loop_item_rating':
				$priority = has_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_rating' );
				if ( $priority ) {
					add_action( 'woocommerce_after_shop_loop_item_title', array( &$this, 'display_sale_badge' ), $priority + 1 );
				} else {
					add_action( 'woocommerce_after_shop_loop_item_title', array( &$this, 'display_sale_badge' ), 6 );
				}
				break;

			case 'before_shop_loop_item_price':
				$priority = has_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price' );
				if ( $priority ) {
					add_action( 'woocommerce_after_shop_loop_item_title', array( &$this, 'display_sale_badge' ), $priority - 1 );
				} else {
					add_action( 'woocommerce_after_shop_loop_item_title', array( &$this, 'display_sale_badge' ), 9 );
				}
				break;

			case 'after_shop_loop_item_price':
				$priority = has_action( 'woocommerce_after_shop_loop_item_title', 'woocommerce_template_loop_price' );
				if ( $priority ) {
					add_action( 'woocommerce_after_shop_loop_item_title', array( &$this, 'display_sale_badge' ), $priority + 1 );
				} else {
					add_action( 'woocommerce_after_shop_loop_item_title', array( &$this, 'display_sale_badge' ), 11 );
				}
				break;
		}

		$single_position = WCCS()->settings->get_setting( 'single_sale_badge_position', 'before_single_item_images' );
		switch ( $single_position ) {
			case 'before_single_item_images':
				$priority = has_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images' );
				if ( $priority ) {
					add_action( 'woocommerce_before_single_product_summary', array( &$this, 'display_sale_badge' ), $priority - 1 );
				} else {
					add_action( 'woocommerce_before_single_product_summary', array( &$this, 'display_sale_badge' ), 19 );
				}
				break;

			case 'after_single_item_images':
				$priority = has_action( 'woocommerce_before_single_product_summary', 'woocommerce_show_product_images' );
				if ( $priority ) {
					add_action( 'woocommerce_before_single_product_summary', array( &$this, 'display_sale_badge' ), $priority + 1 );
				} else {
					add_action( 'woocommerce_before_single_product_summary', array( &$this, 'display_sale_badge' ), 21 );
				}
				break;

			case 'before_single_item_title':
				$priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title' );
				if ( $priority ) {
					add_action( 'woocommerce_single_product_summary', array( &$this, 'display_sale_badge' ), $priority - 1 );
				} else {
					add_action( 'woocommerce_single_product_summary', array( &$this, 'display_sale_badge' ), 4 );
				}
				break;

			case 'after_single_item_title':
				$priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_title' );
				if ( $priority ) {
					add_action( 'woocommerce_single_product_summary', array( &$this, 'display_sale_badge' ), $priority + 1 );
				} else {
					add_action( 'woocommerce_single_product_summary', array( &$this, 'display_sale_badge' ), 6 );
				}
				break;

			case 'before_single_item_price':
				$priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price' );
				if ( $priority ) {
					add_action( 'woocommerce_single_product_summary', array( &$this, 'display_sale_badge' ), $priority - 1 );
				} else {
					add_action( 'woocommerce_single_product_summary', array( &$this, 'display_sale_badge' ), 9 );
				}
				break;

			case 'after_single_item_price':
				$priority = has_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_price' );
				if ( $priority ) {
					add_action( 'woocommerce_single_product_summary', array( &$this, 'display_sale_badge' ), $priority + 1 );
				} else {
					add_action( 'woocommerce_single_product_summary', array( &$this, 'display_sale_badge' ), 11 );
				}
				break;
		}
	}

	public function display_sale_badge() {
		$this->render_view( 'product-pricing.sale-flash', array( 'controller' => $this ) );
	}

	public function woocommerce_product_is_on_sale( $on_sale, $product ) {
		if ( $on_sale ) {
			return $on_sale;
		}

		return WCCS()->WCCS_Product_Onsale_Cache->is_onsale( $product, array( 'simple' => 1 ) );
	}

	/**
	 * Remove sale badge from simple pricing rules when it is disabled.
	 *
	 * @param  boolean    $on_sale
	 * @param  WC_Product $product
	 *
	 * @return boolean
	 */
	public function remove_sale_flash( $on_sale, $product ) {
		if ( ! $on_sale ) {
			return false;
		}

		if ( WCCS()->WCCS_Product_Onsale_Cache->is_onsale( $product, array( 'simple' => 1 ) ) ) {
			return false;
		}

		return $on_sale;
	}

	public function percentage_sale_badge( $sale_badge, $post, $product ) {
		if ( $discount = WCCS()->product_helpers->get_percentage_badge_value( $product ) ) {
			$html = '<span class="onsale wccs-onsale-badge wccs-onsale-badge-discount">';
			$html .= apply_filters( 'wccs_sale_flash_negative_symbol', '<span class="wccs-sale-flash-negative-symbol">-</span>' )
					. esc_html( apply_filters( 'wccs_sale_flash_percentage_value', round( $discount ), $discount ) )
					. apply_filters( 'wccs_sale_flash_percentage_symbol', '<span class="wccs-sale-flash-percentage-symbol">%</span>' );
			$html .= '</span>';
			$sale_badge = apply_filters( 'wccs_sale_flash_discount_value', $html, $discount, $product, $post );
		}
		return $sale_badge;
	}

	public function set_product_min_quantity( $args, $product ) {
		$product_pricing = $this->get_product_pricing( $product );
		return $product_pricing->set_min_quantity( $args );
	}

	public function set_variation_min_quantity( $args, $product, $variation ) {
		$product_pricing = $this->get_product_pricing( $variation );
		return $product_pricing->set_min_quantity( $args );
	}

	public function enable_price_hooks( $hooks = array() ) {
		if ( ! empty( $hooks ) ) {
			if ( in_array( 'woocommerce_get_price_html', $hooks ) && WCCS_Helpers::should_change_display_price_html( 'all' ) ) {
				add_filter( 'woocommerce_get_price_html', array( &$this, 'get_price_html' ), 10, 2 );
			}
		} else {
			if ( WCCS_Helpers::should_change_display_price_html( 'all' ) ) {
				add_filter( 'woocommerce_get_price_html', array( &$this, 'get_price_html' ), 10, 2 );
			}
		}
	}

	public function disable_price_hooks( $hooks = array() ) {
		if ( ! empty( $hooks ) ) {
			if ( in_array( 'woocommerce_get_price_html', $hooks ) ) {
				remove_filter( 'woocommerce_get_price_html', array( &$this, 'get_price_html' ) );
			}
		} else {
			remove_filter( 'woocommerce_get_price_html', array( &$this, 'get_price_html' ) );
		}
	}

	/**
	 * Hook method to use discounted price for product or product its own sale price.
	 * Should use discounted price for the product by comparing it to the product sale price that added in the WooCommerce.
	 * If the return value is false it will use the product on sale price in WooCommerce otherwise it will
	 * use the product discounted price by the plugin.
	 *
	 * @param boolean $value
	 * @param float $discounted_price
	 * @param WC_Product $product
	 * @param array|null $cart_item
	 * @return boolean
	 */
	public function use_discounted_price_or_sale_price( $value, $discounted_price, $product, $cart_item = null ) {
		if ( ! $product->is_on_sale( 'edit' ) ) {
			return $value;
		}

		$apply_mode = WCCS()->settings->get_setting( 'product_pricing_discount_apply_method', 'sum' );
		if ( 'max' !== $apply_mode && 'min' !== $apply_mode ) {
			return $value;
		}

		$sale_price = $product->get_sale_price( 'edit' );
		if ( $cart_item && isset( $cart_item['_wccs_main_sale_price'] ) && '' !== (string) $cart_item['_wccs_main_sale_price'] ) {
			$sale_price = $cart_item['_wccs_main_sale_price'];
		}

		if ( 'max' === $apply_mode ) {
			if ( '' !== (string) $sale_price && (float) $sale_price <= $discounted_price ) {
				$value = false;
			}
		} elseif ( 'min' === $apply_mode ) {
			if ( '' !== (string) $sale_price && (float) $sale_price >= $discounted_price ) {
				$value = false;
			}
		}

		return $value;
	}

	public function cart_item_name( $product_name, $cart_item ) {
		if ( ! isset( $cart_item['data'] ) || 0 != $cart_item['data']->get_price() ) {
			return $product_name;
		}

		// Is the cart item discounted by the discount plugin.
		if ( ! WCCS_Helpers::is_auto_added_product( $cart_item ) && ! isset( $cart_item['_wccs_discounted_price'] ) ) {
			return $product_name;
		}

		// Do not add gift badge to the bundle and bundle items.
		if ( isset( $cart_item['asnp_wepb_items'] ) || isset( $cart_item['asnp_wepb_parent_id'] ) ) {
			return $product_name;
		}

		if ( ! apply_filters( 'wccs_add_free_gift_badge', true, $cart_item ) ) {
			return $product_name;
		}

		// $badge = apply_filters( 'wccs_cart_free_badge', ' <span class="asnp-ewd-free-badge" style="background-color: #00a651; color: #fff; font-size: 15px; padding: 2px 6px; margin-left: 2px; border-radius: 4px;">' . __( 'Free', 'easy-woocommerce-discounts' ) . '</span>' );
		$badge = apply_filters( 'wccs_cart_free_badge', ' <img draggable="false" role="img" class="emoji" alt="🎁" style="float:none; display: inline; border: none; box-shadow: none; height: 1em; width: 1em; background: none; padding: 0; vertical-align: -0.1em; margin: 0 0.07em;" src="https://s.w.org/images/core/emoji/14.0.0/svg/1f381.svg"/>' );

		return $product_name . $badge;
	}

	protected function get_discounted_products( array $args = array() ) {
		if ( null !== $this->discounted_products ) {
			return $this->discounted_products;
		}

		return $this->discounted_products = WCCS()->products->get_discounted_products( $args );
	}

	protected function get_product_pricing( $product, $pricing = null ) {
		if ( null === $pricing ) {
			$pricing = $this->pricing = isset( $this->pricing ) ? $this->pricing : WCCS()->pricing;
		}

		if ( ! isset( $this->product_pricing ) ) {
			$this->product_pricing = new WCCS_Public_Product_Pricing( $product, $pricing );
		} elseif ( is_numeric( $product ) && $product != $this->product_pricing->product->get_id() ) {
			$this->product_pricing = new WCCS_Public_Product_Pricing( $product, $pricing );
		} elseif ( $product !== $this->product_pricing->product ) {
			$this->product_pricing = new WCCS_Public_Product_Pricing( $product, $pricing );
		}

		return $this->product_pricing;
	}

	protected function get_cart_item_main_price( $cart_item, $product ) {
		if ( 'cart_item_price' === WCCS()->settings->get_setting( 'pricing_product_base_price', 'cart_item_price' ) ) {
			return apply_filters(
				'wccs_cart_item_main_price',
				$cart_item['data']->get_price( 'edit' ),
				$cart_item,
				$product
			);
		}
		return apply_filters(
			'wccs_cart_item_main_price',
			$product->get_price( 'edit' ),
			$cart_item,
			$product
		);
	}

	protected function get_cart_item_main_display_price( $cart_item, $product ) {
		if ( 'cart_item_price' === WCCS()->settings->get_setting( 'pricing_product_base_price', 'cart_item_price' ) ) {
			return apply_filters(
				'wccs_cart_item_main_display_price',
				wc_get_price_to_display( $cart_item['data'], array( 'price' => $cart_item['data']->get_price( 'edit' ) ) ),
				$cart_item,
				$product
			);
		}
		return apply_filters(
			'wccs_cart_item_main_display_price',
			wc_get_price_to_display( $product, array( 'price' => $product->get_price( 'edit' ) ) ),
			$cart_item,
			$product
		);
	}

	protected function get_cart_item_before_discounted_price( $cart_item, $product ) {
		if ( 'cart_item_price' === WCCS()->settings->get_setting( 'pricing_product_base_price', 'cart_item_price' ) ) {
			return apply_filters(
				'wccs_cart_item_before_discounted_price',
				WC()->cart->get_product_price( $cart_item['data'] ),
				$cart_item,
				$product
			);
		}
		return apply_filters(
			'wccs_cart_item_before_discounted_price',
			WC()->cart->get_product_price( $product ),
			$cart_item,
			$product
		);
	}

}
