<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Admin_Select_Data_Provider {

	public static function get_customers( array $args = array() ) {
		$users = get_users( $args );
		if ( empty( $users ) ) {
			return array();
		}

		$data = array();
		foreach ( $users as $user ) {
			$data[] = (object) array(
				'id'   => $user->ID,
				'text' => $user->user_nicename,
			);
		}

		return $data;
	}

	public static function get_roles() {
		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			get_role( 'administrator' );
		}

		$roles = $wp_roles->get_names();
		if ( empty( $roles ) ) {
			return array();
		}

		$data = array();
		foreach ( $roles as $key => $value ) {
			$data[] = (object) array(
				'id'   => $key,
				'text' => $value,
			);
		}

		return $data;
	}

	public static function get_coupons( array $args = array() ) {
		$data = array();

		$coupon_ids   = [];
		$discount_ids = [];
		if ( ! empty( $args['include'] ) ) {
			foreach ( $args['include'] as $id ) {
				if ( is_numeric( $id ) ) {
					$coupon_ids[] = absint( $id );
				} elseif ( 0 === strpos( $id, 'asnp_ewd_d_' ) ) {
					$id = str_replace( 'asnp_ewd_d_', '', $id );
					if ( is_numeric( $id ) ) {
						$discount_ids[] = absint( $id );
					}
				}
			}
			$args['include'] = $coupon_ids;
		}

		if ( ! empty( $args['s'] ) || ! empty( $args['include'] ) ) {
			$coupons = WCCS_Coupon_Helpers::get_coupons( $args );
			if ( ! empty( $coupons ) ) {
				foreach ( $coupons as $coupon ) {
					$data[] = (object) array(
						'id'   => $coupon->ID,
						'text' => $coupon->post_title,
					);
				}
			}
		}

		$args['include'] = $discount_ids;

		$discounts = static::get_cart_discounts( $args );

		$data = array_merge( $data, $discounts );

		return $data;
	}

	public static function get_cart_discounts( array $args = [] ) {
		$args = array_merge( [
			'prefix' => 'asnp_ewd_d_',
		], $args );

		$discounts = [];
		if ( ! empty( $args['s'] ) ) {
			$discounts = WCCS()->conditions->get_conditions( [
				'type'    => 'cart-discount',
				'number'  => -1,
				'orderby' => 'ordering',
				'order'   => 'ASC',
				'name'    => $args['s'],
			] );
		} elseif ( ! empty( $args['include'] ) ) {
			$discounts = WCCS()->conditions->get_conditions( [
				'type'    => 'cart-discount',
				'number'  => -1,
				'orderby' => 'ordering',
				'order'   => 'ASC',	
				'id'      => $args['include'],
			] );
		}

		$discounts = array_map( function( $discount ) use ( $args ) {
			return (object) [
				'id'   => $args['prefix'] . absint( $discount->id ),
				'text' =>  html_entity_decode( $discount->name ) . ' - ' . __( 'Cart discount', 'easy-woocommerce-discounts' ),
			];
		}, $discounts );

		return $discounts;
	}

	public static function get_url_coupons( array $args = array() ) {
		if ( ! defined( 'EWD_URL_COUPONS_VERSION' ) ) {
			return array();
		}

		$coupons = AsanaPlugins\WooCommerce\Discounts\UrlCoupons\Models\Coupons::get_url_coupons( $args );
		if ( empty( $coupons ) ) {
			return array();
		}

		$data = array();
		foreach ( $coupons as $coupon ) {
			$data[] = (object) array(
				'id'   => $coupon->id,
				'text' => html_entity_decode( $coupon->name ),
			);
		}

		return $data;
	}

	public static function get_rules( array $args = [] ) {
		$args = array_merge( [
			'number'  => 20,
			'orderby' => 'ordering',
			'order'   => 'ASC',
		], $args );
		
		$conditions = WCCS()->conditions->get_conditions( $args );
		if ( empty( $conditions ) ) {
			return [];
		}

		$data = array();
		foreach ( $conditions as $condition ) {
			$data[] = (object) array(
				'id'   => $condition->id,
				'text' => html_entity_decode( $condition->name ),
			);
		}

		return $data;
	}

	public static function get_capabilities() {
		$capabilities = WCCS()->WCCS_Roles_Helpers->get_capabilities();
		if ( empty( $capabilities ) ) {
			return array();
		}

		$data = array();
		foreach ( $capabilities as $capability ) {
			$data[] = (object) array(
				'id'   => $capability,
				'text' => $capability,
			);
		}

		return $data;
	}

	public static function search_products( array $args = array() ) {
		if ( empty( $args['search'] ) ) {
			throw new Exception( 'Search term is required to search products.' );
		}

		$data_store = WC_Data_Store::load( 'product' );

		if ( WCCS_Helpers::wc_version_check( '3.5.0', '>=' ) && ! empty( $args['limit'] ) && 0 < (int) $args['limit'] ) {
			$products = $data_store->search_products( wc_clean( wp_unslash( $args['search'] ) ), '', false, true, (int) $args['limit'] );
		} else {
			$products = $data_store->search_products( wc_clean( wp_unslash( $args['search'] ) ), '', false, true );
		}

		$products = array_filter( $products );

		return ! empty( $products ) ? static::prepare_product_select( $products ) : array();
	}

	public static function get_products( array $args = array() ) {
		$args = wp_parse_args( $args, array( 'limit' => -1 ) );
		if ( empty( $args['include'] ) && empty( $args['post_id']  ) ) {
			return array();
		}

		$products = WCCS()->products->get_products( $args );
		if ( empty( $products ) ) {
			return array();
		}

		return static::prepare_product_select( $products );
	}

	public static function search_variations( array $args = array() ) {
		if ( empty( $args['search'] ) ) {
			throw new Exception( 'Search term is required to search products.' );
		}

		$data_store = WC_Data_Store::load( 'product' );

		if ( WCCS_Helpers::wc_version_check( '3.5.0', '>=' ) && ! empty( $args['limit'] ) && 0 < (int) $args['limit'] ) {
			$products = $data_store->search_products( wc_clean( wp_unslash( $args['search'] ) ), '', true, true, (int) $args['limit'] );
		} else {
			$products = $data_store->search_products( wc_clean( wp_unslash( $args['search'] ) ), '', true, true );
		}

		$products = array_filter( $products );

		return ! empty( $products ) ? static::prepare_product_select( $products, array( 'variation' ) ) : array();
	}

	public static function get_variations( array $args = array() ) {
		$args = wp_parse_args( $args, array( 'type' => 'variation', 'limit' => -1 ) );
		if ( empty( $args['include'] ) && empty( $args['post_id']  ) ) {
			return array();
		}

		$products = WCCS()->products->get_products( $args );
		if ( empty( $products ) ) {
			return array();
		}

		return static::prepare_product_select( $products, array( 'variation' ) );
	}

	public static function get_product_attributes( array $args = array() ) {
		if ( ! empty( $args['include'] ) ) {
			$args['include'] = WCCS_Helpers::maybe_get_exact_attributes( $args['include'] );
			if ( empty( $args['include'] ) ) {
				return array();
			}
		}

		$attributes = WCCS()->WCCS_Attribute_Helpers->get_attributes( $args );
		if ( empty( $attributes ) ) {
			return $attributes;
		}

		foreach ( $attributes as $attribute ) {
			if ( empty( $attribute->id ) || empty( $attribute->taxonomy ) ) {
				continue;
			}
			$attribute->id = sanitize_text_field( $attribute->taxonomy )  . ',' . absint( $attribute->id );
		}

		return $attributes;
	}

	public static function get_countries() {
		$data = array();
		foreach ( WC()->countries->countries as $code => $label ) {
			$data[] = (object) array(
				'id'   => $code,
				'text' => html_entity_decode( $label ),
			);
		}

		return $data;
	}

	public static function get_states() {
		$data = array();
		foreach ( WC()->countries->countries as $code => $label ) {
			$states = WC()->countries->get_states( $code );
			if ( $states ) {
				foreach ( $states as $state_code => $state_label ) {
					$data[] = (object) array(
						'id'   => $code . ':' . $state_code,
						'text' => html_entity_decode( $label ) . ' - ' . html_entity_decode( $state_label ),
					);
				}
			} else {
				$data[] = (object) array(
					'id'   => $code,
					'text' => html_entity_decode( $label ),
				);
			}
		}

		return $data;
	}

	public static function get_shipping_zones() {
		$rest_of_the_world = WC_Shipping_Zones::get_zone_by( 'zone_id', 0 );

		$zones = WC_Shipping_Zones::get_zones();
		array_unshift( $zones, $rest_of_the_world->get_data() );

		$data = array();
		foreach ( $zones as $zone ) {
			$data[] = (object) array(
				'id'   => $zone['id'],
				'text' => html_entity_decode( $zone['zone_name'] ),
			);
		}

		return $data;
	}

	public static function get_shipping_methods( array $args = array() ) {
		if ( ! empty( $args['search_by'] ) && 'title' === $args['search_by'] ) {
			return static::get_shipping_methods_by_title( $args );
		}
		return static::get_shipping_methods_by_type( $args );
	}

	public static function get_shipping_methods_by_type( array $args = array() ) {
		$data = array();
		// Get WooCommerce shipping methods.
		foreach ( WC()->shipping->get_shipping_methods() as $id => $shipping_method ) {
			// Do not consider the plugin shipping methods here.
			if ( 'dynamic_shipping' === $id ) {
				continue;
			}

			if ( ! empty( $args['name'] ) ) {
				if ( false !== strpos( strtolower( $shipping_method->method_title ), strtolower( trim( $args['name'] ) ) ) ) {
					$data[] = (object) array(
						'id'   => $id,
						'text' => html_entity_decode( $shipping_method->method_title ),
					);
				}
			} elseif ( ! empty( $args['id'] ) ) {
				if ( in_array( $id, $args['id'] ) ) {
					$data[] = (object) array(
						'id'   => $id,
						'text' => html_entity_decode( $shipping_method->method_title ),
					);
				}
			} else {
				$data[] = (object) array(
					'id'   => $id,
					'text' => html_entity_decode( $shipping_method->method_title ),
				);
			}
		}

		$plugin_shipping_methods = static::get_plugin_shipping_methods( $args );
		if ( ! empty( $plugin_shipping_methods ) ) {
			$data = array_merge( $data, $plugin_shipping_methods );
		}

		return $data;
	}

	public static function get_shipping_methods_by_title( array $args = array() ) {
		$args = array_merge( array( 'context' => 'admin' ), $args );

		$zones   = array();
		$zone    = new WC_Shipping_Zone( 0 );
		$methods = $zone->get_shipping_methods( false, $args['context'] );
		if ( ! empty( $methods ) ) {
			$zones[ $zone->get_id() ] = array(
				'name'    => $zone->get_zone_name(),
				'methods' => $methods,
			);
		}

		$data_store = WC_Data_Store::load( 'shipping-zone' );
		$raw_zones  = $data_store->get_zones();
		foreach ( $raw_zones as $raw_zone ) {
			$zone    = new WC_Shipping_Zone( $raw_zone );
			$methods = $zone->get_shipping_methods( false, $args['context'] );
			if ( ! empty( $methods ) ) {
				$zones[ $zone->get_id() ] = array(
					'name'    => $zone->get_zone_name(),
					'methods' => $methods,
				);
			}
		}

		$shipping_methods = array();
		foreach ( $zones as $data ) {
			foreach ( $data['methods'] as $shipping_method ) {
				if ( ! empty( $args['name'] ) ) {
					if ( false !== strpos( strtolower( $shipping_method->title ), strtolower( trim( $args['name'] ) ) ) ) {
						$shipping_methods[] = (object) array(
							'id'   => $shipping_method->id . ':' . $shipping_method->instance_id,
							'text' => html_entity_decode( $data['name'] . ':' . $shipping_method->title ),
						);
					}
				} elseif ( ! empty( $args['id'] ) ) {
					if ( in_array( $shipping_method->id . ':' . $shipping_method->instance_id, $args['id'] ) ) {
						$shipping_methods[] = (object) array(
							'id'   => $shipping_method->id . ':' . $shipping_method->instance_id,
							'text' => html_entity_decode( $data['name'] . ':' . $shipping_method->title ),
						);
					}
				} else {
					$shipping_methods[] = (object) array(
						'id'   => $shipping_method->id . ':' . $shipping_method->instance_id,
						'text' => html_entity_decode( $data['name'] . ':' . $shipping_method->title ),
					);
				}
			}
		}

		$plugin_shipping_methods = static::get_plugin_shipping_methods( $args );
		if ( ! empty( $plugin_shipping_methods ) ) {
			$shipping_methods = array_merge( $shipping_methods, $plugin_shipping_methods );
		}

		return $shipping_methods;
	}

	public static function get_plugin_shipping_methods( array $args = array() ) {
		if ( ! empty( $args['id'] ) ) {
			$ids = array();
			foreach ( $args['id'] as $id ) {
				if ( false !== strpos( $id, 'dynamic_shipping:' ) ) {
					$ids[] = str_replace( 'dynamic_shipping:', '', $id );
				}
			}
			$args['id'] = $ids;
		}
		if ( empty( $args['id'] ) && empty( $args['name'] ) ) {
			return array();
		}

		$shipping_methods = WCCS_Conditions_Provider::get_shippings( $args );
		if ( ! empty( $shipping_methods ) ) {
			$data = array();
			foreach ( $shipping_methods as $shipping_method ) {
				$data[] = (object) array(
					'id'   => 'dynamic_shipping:' . $shipping_method->id,
					'text' => html_entity_decode( $shipping_method->name ),
				);
			}
			return $data;
		}

		return array();
	}

	public static function get_shipping_classes() {
		$data = array();
		foreach ( WC()->shipping->get_shipping_classes() as $shipping_class ) {
			$data[] = (object) array(
				'id'   => $shipping_class->term_id,
				'text' => html_entity_decode( $shipping_class->name ),
			);
		}

		return $data;
	}

	public static function get_payment_methods() {
		$data = array();
		foreach ( WC()->payment_gateways->payment_gateways() as $gateway ) {
			$method_title = $gateway->get_method_title() ? $gateway->get_method_title() : $gateway->get_title();
			$custom_title = $gateway->get_title();

			$data[] = (object) array(
				'id'   => $gateway->id,
				'text' => html_entity_decode( wp_kses_post( $method_title ) ) . ( $method_title !== $custom_title ? ' - ' . html_entity_decode( wp_kses_post( $custom_title ) ) : '' ),
			);
		}

		return $data;
	}

	protected static function prepare_product_select( array $products, $allowed_types = array() ) {
		$products_select = array();
		foreach ( $products as $product ) {
			if ( is_numeric( $product ) ) {
				$product = wc_get_product( $product );
			}
			if ( ! $product ) {
				continue;
			}

			if ( ! empty( $allowed_types ) && ! in_array( $product->get_type(), $allowed_types ) ) {
				continue;
			}

			$id = WCCS_Helpers::maybe_get_exact_item_id( $product->get_id() );
			if ( isset( $products_select[ $id ] ) ) {
				continue;
			}

			if ( $product->get_sku() ) {
				$identifier = $product->get_sku();
			} else {
				$identifier = '#' . $product->get_id();
			}

			if ( 'variation' === $product->get_type() ) {
				$formatted_variation_list = wc_get_formatted_variation( $product, true );
				$text = sprintf( '%2$s (%1$s)', $identifier, $product->get_title() ) . ' ' . $formatted_variation_list;
			} else {
				$text = sprintf( '%2$s (%1$s)', $identifier, $product->get_title() );
			}

			$products_select[ $id ] = (object) array(
				'id'   => $product->get_id(),
				'text' => $text,
			);
		}

		return array_values( $products_select );
	}

}
