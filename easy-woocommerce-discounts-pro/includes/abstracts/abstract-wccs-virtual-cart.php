<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

abstract class WCCS_Abstract_Virtual_Cart {

	protected $cart;

    public function __construct( $cart = null ) {
		$this->cart = null !== $cart ? $cart : clone WC()->cart;
	}

	public function &__get( $key ) {
		if ( property_exists( $this, $key ) ) {
			return $this->$key;
		}

		return $this->cart->$key;
	}

	public function __set( $name, $value ) {
		if ( property_exists( $this, $name ) ) {
			$this->{$name} = $value;
		} else {
			$this->cart->{$name} = $value;
		}
	}

	public function __call( $name, $arguments ) {
		if ( method_exists( $this, $name ) ) {
			return call_user_func_array( array( $this, $name ), $arguments );
		} elseif ( is_callable( array( $this->cart, $name ) ) ) {
			return call_user_func_array( array( $this->cart, $name ), $arguments );
		}
	}

	/**
	 * Return whether or not the cart is displaying prices including tax, rather than excluding tax.
	 *
	 * @since  2.2.0
	 *
	 * @return bool
	 */
	public function display_prices_including_tax() {
		if ( is_callable( array( $this->cart, 'display_prices_including_tax' ) ) ) {
			return $this->cart->display_prices_including_tax();
		}

		return apply_filters( 'woocommerce_cart_' . __FUNCTION__, 'incl' === $this->cart->tax_display_cart );
	}

	/**
	 * Get the product row price per item.
	 *
	 * @param WC_Product $product Product object.
	 * @param Boolean    $formated_price if true return formated price.
	 * @param array      $args
	 *
	 * @return string formatted price
	 */
	public function get_product_price( $product, $formated_price = true, array $args = array() ) {
		if ( $this->display_prices_including_tax() ) {
			$product_price = WCCS()->product_helpers->wc_get_price_including_tax( $product, $args );
		} else {
			$product_price = WCCS()->product_helpers->wc_get_price_excluding_tax( $product, $args );
		}

		if ( $formated_price ) {
			return apply_filters( 'woocommerce_cart_product_price', wc_price( $product_price ), $product );
		}

		return apply_filters( 'woocommerce_cart_product_price', $product_price, $product );
	}

	 /**
	 * Set the quantity for an item in the cart.
	 *
	 * @since 2.2.0
	 *
	 * @param string $cart_item_key	contains the id of the cart item.
	 * @param int    $quantity contains the quantity of the item.
	 * @param bool   $refresh_totals whether or not to calculate totals after setting the new qty.
	 *
	 * @return bool
	 */
	public function set_quantity( $cart_item_key, $quantity = 1, $refresh_totals = true ) {
		if ( 0 === $quantity || $quantity < 0 ) {
			unset( $this->cart_contents[ $cart_item_key ] );
		} else {
			$old_quantity = $this->cart_contents[ $cart_item_key ]['quantity'];
			$this->cart_contents[ $cart_item_key ]['quantity'] = $quantity;
		}

		if ( $refresh_totals ) {
			$this->calculate_totals();
		}

		return true;
	}

    /**
	 * Add a product to the cart.
	 *
	 * @since 2.2.0
	 *
	 * @param int   $product_id contains the id of the product to add to the cart.
	 * @param int   $quantity contains the quantity of the item to add.
	 * @param int   $variation_id ID of the variation being added to the cart.
	 * @param array $variation attribute values.
	 * @param array $cart_item_data extra cart item data we want to pass into the item.
	 * @return string|bool $cart_item_key
	 */
	public function add_to_cart( $product_id = 0, $quantity = 1, $variation_id = 0, $variation = array(), $cart_item_data = array() ) {
		$product_id   = absint( $product_id );
		$variation_id = absint( $variation_id );

		// Ensure we don't add a variation to the cart directly by variation ID.
		if ( 'product_variation' === get_post_type( $product_id ) ) {
			$variation_id = $product_id;
			$product_id   = wp_get_post_parent_id( $variation_id );
		}

		$product_data = wc_get_product( $variation_id ? $variation_id : $product_id );
		$quantity     = apply_filters( 'woocommerce_add_to_cart_quantity', $quantity, $product_id );

		if ( $quantity <= 0 || ! $product_data || 'trash' === ( is_callable( array( $product_data, 'get_status' ) ) ? $product_data->get_status() : $product_data->post->post_status ) ) {
			return false;
		}

		// Load cart item data - may be added by other plugins.
		$cart_item_data = (array) apply_filters( 'woocommerce_add_cart_item_data', $cart_item_data, $product_id, $variation_id, $quantity );

		// Generate a ID based on product ID, variation ID, variation data, and other cart item data.
		$cart_id        = $this->cart->generate_cart_id( $product_id, $variation_id, $variation, $cart_item_data );

		// Find the cart item key in the existing cart.
		$cart_item_key  = $this->cart->find_product_in_cart( $cart_id );

		// Force quantity to 1 if sold individually and check for existing item in cart.
		if ( $product_data->is_sold_individually() ) {
			$quantity      = apply_filters( 'woocommerce_add_to_cart_sold_individually_quantity', 1, $quantity, $product_id, $variation_id, $cart_item_data );
			$found_in_cart = apply_filters( 'woocommerce_add_to_cart_sold_individually_found_in_cart', $cart_item_key && $this->cart_contents[ $cart_item_key ]['quantity'] > 0, $product_id, $variation_id, $cart_item_data, $cart_id );

			if ( $found_in_cart ) {
				/* translators: %s: product name */
				return false;
			}
		}

		if ( ! $product_data->is_purchasable() ) {
			return false;
		}

		// Stock check - only check if we're managing stock and backorders are not allowed.
		if ( ! $product_data->is_in_stock() ) {
			return false;
		}

		if ( ! $product_data->has_enough_stock( $quantity ) ) {
			/* translators: 1: product name 2: quantity in stock */
			return false;
		}

		// Stock check - this time accounting for whats already in-cart.
		if ( $managing_stock = $product_data->managing_stock() ) {
			$products_qty_in_cart = $this->get_cart_item_quantities();

			if ( ! is_callable( array( $product_data, 'get_stock_managed_by_id' ) ) ) {
				if ( $product_data->is_type( 'variation' ) && true === $managing_stock ) {
					$check_qty = isset( $products_qty_in_cart[ $variation_id ] ) ? $products_qty_in_cart[ $variation_id ] : 0;
				} else {
					$check_qty = isset( $products_qty_in_cart[ $product_id ] ) ? $products_qty_in_cart[ $product_id ] : 0;
				}

				if ( ! $product_data->has_enough_stock( $check_qty + $quantity ) ) {
					return false;
				}
			} else {
				if ( isset( $products_qty_in_cart[ $product_data->get_stock_managed_by_id() ] ) && ! $product_data->has_enough_stock( $products_qty_in_cart[ $product_data->get_stock_managed_by_id() ] + $quantity ) ) {
					return false;
				}
			}
		}

		// If cart_item_key is set, the item is already in the cart.
		if ( $cart_item_key ) {
			$new_quantity = $quantity + $this->cart_contents[ $cart_item_key ]['quantity'];
			$this->set_quantity( $cart_item_key, $new_quantity, false );
		} else {
			$cart_item_key = $cart_id;

			// Adding a flag to specify that it is a cart item.
			WCCS()->custom_props->set_prop( $product_data, 'wccs_is_cart_item', true );

			// Add item after merging with $cart_item_data - hook to allow plugins to modify cart item.
			$this->cart_contents[ $cart_item_key ] = apply_filters( 'woocommerce_add_cart_item', array_merge( $cart_item_data, array(
				'key'          => $cart_item_key,
				'product_id'   => $product_id,
				'variation_id' => $variation_id,
				'variation'    => $variation,
				'quantity'     => $quantity,
				'data'         => $product_data,
			) ), $cart_item_key );
		}

		$this->cart_contents[ $cart_item_key ] = apply_filters(
			'wccs_virtual_cart_add_to_cart_item_data',
			$this->cart_contents[ $cart_item_key ],
			$cart_item_key,
			$product_id,
			$quantity,
			$variation_id,
			$variation
		);

		$this->calculate_totals();

		return $cart_item_key;
    }

    /**
     * Calculate totals for the items in the cart.
     *
     * @since 2.2.2
     */
    abstract public function calculate_totals();

}
