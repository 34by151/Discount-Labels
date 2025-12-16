<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Helpers {

	/**
	 * Checking a version against WooCommerce version.
	 *
	 * @since  1.1.0
	 *
	 * @param  string $version
	 * @param  string $operator
	 *
	 * @return boolean
	 */
	public static function wc_version_check( $version = '3.0', $operator = '>=' ) {
		return version_compare( WC_VERSION, $version, $operator );
	}

	/**
	 * Wrapper for wc_get_logger function.
	 *
	 * @since  1.1.0
	 *
	 * @return WC_Logger
	 */
	public static function wc_get_logger() {
		return self::wc_version_check() ? wc_get_logger() : new WC_Logger();
	}

	/**
	 * Format a price range for display.
	 * Wrapper method for WooCommerce wc_format_price_range function.
	 *
	 * @since  1.1.0
	 *
	 * @param  string $from Price from.
	 * @param  string $to   Price to.
	 *
	 * @return string
	 */
	public static function wc_format_price_range( $from, $to ) {
		if ( self::wc_version_check() ) {
			return wc_format_price_range( $from, $to );
		}

		/* translators: 1: price from 2: price to */
		$price = sprintf( _x( '%1$s &ndash; %2$s', 'Price range: from-to', 'woocommerce' ), is_numeric( $from ) ? wc_price( $from ) : $from, is_numeric( $to ) ? wc_price( $to ) : $to );
		return apply_filters( 'woocommerce_format_price_range', $price, $from, $to );
	}

	/**
	 * Checking is product in given include and exclude products.
	 *
	 * @since  1.1.0
	 *
	 * @param  array   $include
	 * @param  array   $exclude
	 * @param  integer $product_id
	 * @param  integer $variation_id
	 *
	 * @return boolean
	 */
	public static function is_product_in_items( $include, $exclude, $product_id, $variation_id = 0 ) {
		if ( empty( $include ) && empty( $exclude ) ) {
			return false;
		}

		if ( isset( $include['all_products'] ) || isset( $include['all_categories'] ) ) {
			if ( ! empty( $exclude ) ) {
				if ( isset( $exclude[ $product_id ] ) || ( 0 < (int) $variation_id && isset( $exclude[ $variation_id ] ) ) ) {
					return false;
				}
			}
			return true;
		} elseif ( isset( $include[ $product_id ] ) || ( 0 < (int) $variation_id && isset( $include[ $variation_id ] ) ) ) {
			if ( ! empty( $exclude ) ) {
				if ( isset( $exclude[ $product_id ] ) || ( 0 < (int) $variation_id && isset( $exclude[ $variation_id ] ) ) ) {
					return false;
				}
			}
			return true;
		} elseif ( empty( $include ) && ! empty( $exclude ) ) {
			if ( ! isset( $exclude[ $product_id ] ) && ( 0 >= (int) $variation_id || ! isset( $exclude[ $variation_id ] ) ) ) {
				return true;
			}
			return false;
		}

		return false;
	}

	/**
	 * Getting term hierarchy name.
	 *
	 * @since  2.0.0
	 *
	 * @param  int|WP_Term|object $term_id
	 * @param  string             $taxonomy
	 * @param  string             $separator
	 * @param  boolean            $nicename
	 * @param  array              $visited
	 *
	 * @return string
	 */
	public static function get_term_hierarchy_name( $term_id, $taxonomy, $separator = '/', $nicename = false, $visited = array() ) {
		$chain = '';
		$term = get_term( $term_id, $taxonomy );

		if ( is_wp_error( $term ) ) {
			return '';
		}

		$name = $term->name;
		if ( $nicename ) {
			$name = $term->slug;
		}

		if ( $term->parent && ( $term->parent != $term->term_id ) && ! in_array( $term->parent, $visited ) ) {
			$visited[] = $term->parent;
			$chain     .= self::get_term_hierarchy_name( $term->parent, $taxonomy, $separator, $nicename, $visited );
		}

		$chain .= $name . $separator;

		return $chain;
	}

	/**
	 * Get rounding precision for internal WC calculations.
	 * Will increase the precision of wc_get_price_decimals by 2 decimals, unless WC_ROUNDING_PRECISION is set to a higher number.
	 *
	 * @since  2.2.2
	 *
	 * @return int
	 */
	public static function wc_get_rounding_precision() {
		if ( function_exists( 'wc_get_rounding_precision' ) ) {
			return wc_get_rounding_precision();
		}

		$precision = wc_get_price_decimals() + 2;
		if ( absint( WC_ROUNDING_PRECISION ) > $precision ) {
			$precision = absint( WC_ROUNDING_PRECISION );
		}
		return $precision;
	}

	/**
	 * Add precision to a number and return a number.
	 *
	 * @since  2.2.2
	 *
	 * @param  float $value Number to add precision to.
	 * @param  bool  $round If should round after adding precision.
	 *
	 * @return int|float
	 */
	public static function wc_add_number_precision( $value, $round = true ) {
		if ( function_exists( 'wc_add_number_precision' ) ) {
			return wc_add_number_precision( $value, $round );
		}

		$cent_precision = pow( 10, wc_get_price_decimals() );
		$value          = $value * $cent_precision;
		return $round ? round( $value, self::wc_get_rounding_precision() - wc_get_price_decimals() ) : $value;
	}

	/**
	 * Remove precision from a number and return a float.
	 *
	 * @since  2.2.2
	 *
	 * @param  float $value Number to add precision to.
	 * @return float
	 */
	public static function wc_remove_number_precision( $value ) {
		if ( function_exists( 'wc_remove_number_precision' ) ) {
			return wc_remove_number_precision( $value );
		}

		$cent_precision = pow( 10, wc_get_price_decimals() );
		return $value / $cent_precision;
	}

	/**
	 * Add precision to an array of number and return an array of int.
	 *
	 * @since  2.2.2
	 *
	 * @param  array $value Number to add precision to.
	 * @param  bool  $round Should we round after adding precision?.
	 *
	 * @return int|array
	 */
	public static function wc_add_number_precision_deep( $value, $round = true ) {
		if ( function_exists( 'wc_add_number_precision_deep' ) ) {
			return wc_add_number_precision_deep( $value, $round );
		}

		if ( ! is_array( $value ) ) {
			return self::wc_add_number_precision( $value, $round );
		}

		foreach ( $value as $key => $sub_value ) {
			$value[ $key ] = self::wc_add_number_precision_deep( $sub_value, $round );
		}

		return $value;
	}

	/**
	 * Remove precision from an array of number and return an array of int.
	 *
	 * @since  5.2.0
	 * @param  array $value Number to add precision to.
	 * @return int|array
	 */
	public static function wc_remove_number_precision_deep( $value ) {
		if ( function_exists( 'wc_remove_number_precision_deep' ) ) {
			return wc_remove_number_precision_deep( $value );
		}

		if ( ! is_array( $value ) ) {
			return self::wc_remove_number_precision( $value );
		}

		foreach ( $value as $key => $sub_value ) {
			$value[ $key ] = self::wc_remove_number_precision_deep( $sub_value );
		}

		return $value;
	}

	/**
	 * Returns true if the request is a non-legacy REST API request.
	 *
	 * Legacy REST requests should still run some extra code for backwards compatibility.
	 *
	 * @todo: replace this function once core WP function is available: https://core.trac.wordpress.org/ticket/42061.
	 *
	 * @return bool
	 */
	public static function wc_is_rest_api_request() {
		if ( is_callable( array( WC(), 'is_rest_api_request' ) ) ) {
			return WC()->is_rest_api_request();
		}

		if ( empty( $_SERVER['REQUEST_URI'] ) ) {
			return false;
		}

		$rest_prefix         = trailingslashit( rest_get_url_prefix() );
		$is_rest_api_request = ( false !== strpos( $_SERVER['REQUEST_URI'], $rest_prefix ) ); // phpcs:disable WordPress.Security.ValidatedSanitizedInput.MissingUnslash, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized

		return apply_filters( 'woocommerce_is_rest_api_request', $is_rest_api_request );
	}

	/**
	 * Is limit enabled for pricing rules.
	 *
	 * @since 6.10.0
	 *
	 * @return boolean
	 */
	public static function is_pricing_discount_limit_enabled() {
		$limit_type = WCCS()->settings->get_setting( 'product_pricing_limit_type', 'no_limit' );
		if ( in_array( $limit_type, array( 'price_price_limit', 'price_percentage_limit' ) ) ) {
			$discount_limit = WCCS()->settings->get_setting( 'product_pricing_discount_limit', '' );
			if ( '' !== $discount_limit && 0 <= (float) $discount_limit ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get product pricing discount limit.
	 *
	 * @since  3.0.0
	 *
	 * @param  float $base_price
	 *
	 * @return string|float
	 */
	public static function get_pricing_discount_limit( $base_price ) {
		$limit_type     = WCCS()->settings->get_setting( 'product_pricing_limit_type', 'no_limit' );
		$discount_limit = '';
		if ( in_array( $limit_type, array( 'price_price_limit', 'price_percentage_limit' ) ) ) {
			$discount_limit = WCCS()->settings->get_setting( 'product_pricing_discount_limit', '' );
			if ( '' !== $discount_limit && 0 <= (float) $discount_limit ) {
				$discount_limit = 'price_percentage_limit' === $limit_type ? (float) $discount_limit / 100 * $base_price : (float) $discount_limit;
			}
		}

		return $discount_limit;
	}

	/**
	 * Is limit enabled for cart discount rules.
	 *
	 * @since 6.10.0
	 *
	 * @return boolean
	 */
	public static function is_cart_discount_limit_enabled() {
		$limit_type = WCCS()->settings->get_setting( 'cart_discount_limit_type', 'no_limit' );
		if ( in_array( $limit_type, array( 'total_price_limit', 'total_percentage_limit' ) ) ) {
			$limit = WCCS()->settings->get_setting( 'cart_discount_limit', '' );
			if ( '' !== $limit && 0 < (float) $limit ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Get cart discount limit.
	 *
	 * @since  3.0.0
	 *
	 * @param  float $base_price
	 *
	 * @return string|float
	 */
	public static function get_cart_discount_limit( $base_price ) {
		$limit      = '';
		$limit_type = WCCS()->settings->get_setting( 'cart_discount_limit_type', 'no_limit' );
		if ( in_array( $limit_type, array( 'total_price_limit', 'total_percentage_limit' ) ) ) {
            $limit = WCCS()->settings->get_setting( 'cart_discount_limit', '' );
            if ( '' !== $limit ) {
                $limit = 'total_percentage_limit' === $limit_type ? (float) $limit / 100 * $base_price : (float) $limit;
            }
		}

		return $limit;
	}

	/**
	 * Check is the cart item automatically added by the plugin or no.
	 *
	 * @param  array $cart_item
	 *
	 * @return boolean
	 * @throws Exception
	 */
	public static function is_auto_added_product( $cart_item ) {
		if ( ! $cart_item ) {
			throw new Exception('Cart item is required.');
		}

		return isset( $cart_item[ WCCS_Public_Auto_Add_To_Cart::CART_ITEM_ID ] ) ||
			isset( $cart_item['_ewd_auto_added_product'] ) ||
			isset( $cart_item['_ewd_urlc_auto_added_product'] );
	}

	/**
	 * Round a number using the built-in `round` function, but unless the value to round is numeric
	 * (a number or a string that can be parsed as a number), apply 'floatval' first to it
	 * (so it will convert it to 0 in most cases).
	 *
	 * This is needed because in PHP 7 applying `round` to a non-numeric value returns 0,
	 * but in PHP 8 it throws an error. Specifically, in WooCommerce we have a few places where
	 * round('') is often executed.
	 *
	 * @since 5.2.0
	 *
	 * @param mixed $val The value to round.
	 * @param int   $precision The optional number of decimal digits to round to.
	 * @param int   $mode A constant to specify the mode in which rounding occurs.
	 *
	 * @return float The value rounded to the given precision as a float, or the supplied default value.
	 */
	public static function round( $val, $precision = 0, $mode = PHP_ROUND_HALF_UP ) {
		if ( ! is_numeric( $val ) ) {
			$val = floatval( $val );
		}
		return round( $val, $precision, $mode );
	}

	/**
	 * Maybe exchange price with multicurrency plugins.
	 *
	 * @param mixed  $price
	 * @param string $type
	 *
	 * @return mixed
	 */
	public static function maybe_exchange_price( $price, $type = 'product' ) {
		if ( empty( $price ) ) {
			return $price;
		}

		return apply_filters( 'wccs_maybe_exchange_price', $price, $type );
	}

	public static function round_weight( $val, array $args = array() ) {
		if ( ! is_numeric( $val ) ) {
			$val = floatval( $val );
		}

		$args = array_merge( array(
			'precision' => 0,
			'method'    => 'round',
			'mode'      => PHP_ROUND_HALF_UP,
		), $args );

		$round = $val;
		if ( 'round' === $args['method'] ) {
			$round = self::round( $val, $args['precision'], $args['mode'] );
		} elseif ( 'floor' === $args['method'] ) {
			$round = floor( $val );
		} elseif ( 'ceil' === $args['method'] ) {
			$round = ceil( $val );
		}

		return apply_filters( 'wccs_helpers_' . __FUNCTION__, $round, $val, $args );
	}

	public static function should_change_display_price() {
		if ( ! WCCS()->pricing ) {
			return false;
		}

		if ( 'none' === WCCS()->settings->get_setting( 'change_display_price', 'simple' ) ) {
			return false;
		}

		$simples = WCCS()->pricing->get_simple_pricings();
		return ! empty( $simples );
	}

	public static function should_change_display_price_html( $type = 'simple' ) {
		if ( ! WCCS()->pricing ) {
			return false;
		}
		
		$change = WCCS()->settings->get_setting( 'change_display_price', 'all' );
		if ( 'all' !== $change && 'simple' !== $change ) {
			return false;
		}
		
		$simples = WCCS()->pricing->get_simple_pricings();
		if ( ! empty( $simples ) ) {
			return true;
		}

		if ( 'all' !== $type ) {
			return false;
		}

		$bulks = WCCS()->pricing->get_bulk_pricings();

		return ! empty( $bulks );
	}

	public static function is_allowed_auto_add_product_type( $type ) {
		if ( empty( $type ) ) {
			return false;
		}

		$types = apply_filters(
			'wccs_not_allowed_auto_add_product_types',
			array(
				'variable',
				'composite',
				'booking',
			)
		);

		foreach ( $types as $not_allowed ) {
			if (
				$type === $not_allowed ||
				false !== strpos( $type, $not_allowed )
			) {
				return false;
			}
		}

		return true;
	}

	public static function maybe_get_exact_item_id( $id, $type = 'product' ) {
		if ( ! is_numeric( $id ) || 0 >= $id ) {
			return absint( $id );
		}

		return apply_filters( 'wccs_exact_item_id', absint( $id ), $type );
	}

	public static function maybe_get_exact_category_id( $id ) {
		return self::maybe_get_exact_item_id( $id, 'product_cat' );
	}

	public static function maybe_get_exact_tag_id( $id ) {
		return self::maybe_get_exact_item_id( $id, 'product_tag' );
	}

	public static function maybe_get_exact_product( $product ) {
		if ( ! $product ) {
			return $product;
		}

		if ( $product instanceof WC_Product ) {
			return apply_filters( 'wccs_exact_product', $product );
		} elseif ( is_numeric( $product ) ) {
			return self::maybe_get_exact_item_id( $product, 'product' );
		}

		return $product;
	}

	public static function maybe_get_exact_attribute_id( $attribute ) {
		if ( empty( $attribute ) ) {
			return $attribute;
		}

		if ( is_numeric( $attribute ) && 0 < (int) $attribute ) {
			return (int) $attribute;
		} elseif ( ! empty( $attribute ) ) {
			$attribute = explode( ',', $attribute );
			if (
				2 === count( $attribute ) &&
				! empty( $attribute[0] ) &&
				! empty( $attribute[1] ) &&
				is_numeric( $attribute[1] ) &&
				0 < (int) $attribute[1]
			) {
				return self::maybe_get_exact_item_id( (int) $attribute[1], $attribute[0] );
			}
		}

		return (int) $attribute;
	}

	public static function maybe_get_exact_attributes( $attributes ) {
		if ( empty( $attributes ) ) {
			return $attributes;
		}

		return array_filter( array_map( 'WCCS_Helpers::maybe_get_exact_attribute_id', $attributes ) );
	}

	public static function is_product_page() {
		if ( is_product() ) {
			return true;
		}

		global $post;
		return ! empty( $post->post_content ) && false !== strpos( $post->post_content, '[product_page' );
	}

	public static function get_applying_coupon() {
		// If it's a WooCommerce REST request (Cart Block), retrieve the 'code' parameter from JSON payload
		if ( 
			! empty( $_SERVER['REQUEST_URI'] ) &&
			false !== strpos( $_SERVER['REQUEST_URI'], '/wc/store' ) 
		) {
			$data = file_get_contents( 'php://input' );
			$data = json_decode( $data, true );
	
			if ( 
				is_array( $data ) && 
				isset( $data['requests'][0]['path'] ) &&
				false !== strpos( $data['requests'][0]['path'], '/apply-coupon' ) && 
				isset( $data['requests'][0]['data']['code'] ) 
			) {
				return wc_format_coupon_code( wp_unslash( $data['requests'][0]['data']['code'] ) );
			}
		}

		if ( ! check_ajax_referer( 'apply-coupon', 'security', false ) ) {
			return '';
		}
	
		// Fallback for traditional WooCommerce cart form submission
		return ! empty( $_POST['coupon_code'] ) ? wc_format_coupon_code( wp_unslash( $_POST['coupon_code'] ) ) : '';
	}

	public static function get_billing_email() {
        if ( empty( $_GET['wc-ajax'] ) || ! in_array( $_GET['wc-ajax'], array( 'checkout', 'update_order_review' ) ) ) {
            return '';
        }

        $email = '';
        if ( ! empty( $_POST['billing_email'] ) ) {
            $email = strtolower( sanitize_email( $_POST['billing_email'] ) );
        } elseif ( ! empty( $_POST['post_data'] ) ) {
            parse_str( $_POST['post_data'], $post );
            if ( ! empty( $post['billing_email'] ) ) {
                $email = strtolower( sanitize_email( $post['billing_email'] ) );
            }
        }
        return is_email( $email ) ? $email : '';
    }

	public static function get_used_by() {
		$used_by = get_current_user_id();
		$used_by = empty( $used_by ) ? static::get_billing_email() : $used_by;
		return $used_by;
	}

	public static function register_polyfills( $react = false ) {
		static $registered;
		if ( $registered ) {
			return;
		}

		global $wp_version;

		$handles = array(
			'wp-i18n'      => array( '6.0', array() ),
			'wp-hooks'     => array( '6.0', array() ),
			'wp-api-fetch' => array( '6.0', array() ),
			'moment'       => array( '2.29.4', array() ),
			'lodash'       => array( '4.17.21', array() ),
		);
		if ( $react ) {
			$handles['react']     = array( '17.0.2', array() );
			$handles['react-dom'] = array( '17.0.2', array( 'react' ) );
		}

		foreach ( $handles as $handle => $value ) {
			if ( ! version_compare( $wp_version, '5.9', '>=' ) && in_array( $handle, array( 'react', 'react-dom' ) ) ) {
				wp_deregister_script( $handle );
			}

			if ( ! wp_script_is( $handle, 'registered' ) ) {
				wp_register_script(
					$handle,
					plugins_url( 'admin/js/vendor/' . $handle . '.js', WCCS_PLUGIN_FILE ),
					$value[1],
					$value[0],
					true
				);
			}
		}

		$registered = true;
	}

	public static function get_review() {
		return get_option( 'asnp_ewd_review', array() );
	}

	public static function set_review( $review ) {
		return update_option( 'asnp_ewd_review', $review );
	}

	public static function maybe_show_review() {
		$review = self::get_review();
		if ( isset( $review['dismissed'] ) ) {
			return false;
		}

		if ( 0 >= WCCS()->conditions->count() ) {
			return false;
		}

		$schedule = strtotime( '+7 days' );
		if ( empty( $review['schedule'] ) ) {
			$review['schedule'] = $schedule;
			self::set_review( $review );
		} else {
			$schedule = (int) $review['schedule'];
		}

		if ( empty( $schedule ) || time() < $schedule ) {
			return false;
		}

		return true;
	}

}
