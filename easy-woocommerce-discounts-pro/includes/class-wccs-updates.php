<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Updates {

	public static function clear_pricing_caches() {
		WCCS()->WCCS_Clear_Cache->clear_pricing_caches();
	}

	public static function update_110_conditions() {
		global $wpdb;

		$condition_meta = WCCS()->condition_meta;

		// Adding apply_mode for pricing and cart_discount conditions.
		$results = $wpdb->get_col( "SELECT `id` FROM {$wpdb->prefix}wccs_conditions WHERE `type` IN ('pricing','cart-discount') AND `id` NOT IN ( SELECT DISTINCT `wccs_condition_id` FROM {$wpdb->prefix}wccs_condition_meta WHERE `meta_key` = 'apply_mode' )" );
		if ( ! empty( $results ) ) {
			foreach ( $results as $condition_id ) {
				$condition_meta->update_meta( $condition_id, 'apply_mode', 'all' );
			}
		}

		// Adding exclude_items for pricing conditions.
		$results = $wpdb->get_col( "SELECT `id` FROM {$wpdb->prefix}wccs_conditions WHERE `type` = 'pricing' AND `id` NOT IN ( SELECT DISTINCT `wccs_condition_id` FROM {$wpdb->prefix}wccs_condition_meta WHERE `meta_key` = 'exclude_items' )" );
		if ( ! empty( $results ) ) {
			foreach ( $results as $condition_id ) {
				$condition_meta->update_meta( $condition_id, 'exclude_items', array() );
			}
		}

		// Adding private_note for cart_discount conditions.
		$results = $wpdb->get_col( "SELECT `id` FROM {$wpdb->prefix}wccs_conditions WHERE `type` = 'cart-discount' AND `id` NOT IN ( SELECT DISTINCT `wccs_condition_id` FROM {$wpdb->prefix}wccs_condition_meta WHERE `meta_key` = 'private_note' )" );
		if ( ! empty( $results ) ) {
			foreach ( $results as $condition_id ) {
				$condition_meta->update_meta( $condition_id, 'private_note', '' );
			}
		}

		// Adding date_times_match_mode to conditions.
		$results = $wpdb->get_col( "SELECT `id` FROM {$wpdb->prefix}wccs_conditions WHERE `type` IN ('pricing','cart-discount','products-list') AND `id` NOT IN ( SELECT DISTINCT `wccs_condition_id` FROM {$wpdb->prefix}wccs_condition_meta WHERE `meta_key` = 'date_times_match_mode' )" );
		if ( ! empty( $results ) ) {
			foreach ( $results as $condition_id ) {
				$condition_meta->update_meta( $condition_id, 'date_times_match_mode', 'one' );
			}
		}

		// Adding conditions_match_mode to conditions.
		$results = $wpdb->get_col( "SELECT `id` FROM {$wpdb->prefix}wccs_conditions WHERE `type` IN ('pricing','cart-discount','products-list') AND `id` NOT IN ( SELECT DISTINCT `wccs_condition_id` FROM {$wpdb->prefix}wccs_condition_meta WHERE `meta_key` = 'conditions_match_mode' )" );
		if ( ! empty( $results ) ) {
			foreach ( $results as $condition_id ) {
				$condition_meta->update_meta( $condition_id, 'conditions_match_mode', 'all' );
			}
		}

		// Updating pricing products to adding quantity.
		$results = $wpdb->get_results( "SELECT `conditions`.id, `conditions_meta`.meta_value FROM {$wpdb->prefix}wccs_conditions AS `conditions` JOIN {$wpdb->prefix}wccs_condition_meta AS `conditions_meta` ON `conditions`.id = `conditions_meta`.wccs_condition_id WHERE `conditions`.type = 'pricing' AND `conditions_meta`.meta_key = 'items' AND `conditions_meta`.meta_value != ''" );
		if ( ! empty( $results ) ) {
			foreach ( $results as $result ) {
				if ( ! empty( $result->meta_value ) ) {
					$update = false;
					$items = maybe_unserialize( $result->meta_value );
					foreach ( $items as &$item ) {
						if ( ! isset( $item['quantity'] ) ) {
							$update = true;
							$item['quantity'] = '';
						}
					}
					unset( $item );

					if ( $update ) {
						$condition_meta->update_meta( $result->id, 'items', $items );
					}
				}
			}
		}
	}

	public static function update_110_db_version() {
		WCCS_Activator::update_db_version( '1.1.0' );
	}

	public static function update_301() {
		WCCS()->WCCS_Clear_Cache->clear_pricing_caches();
	}

	public static function update_460() {
		WCCS()->WCCS_Clear_Cache->clear_pricing_caches();

		if ( wp_using_ext_object_cache() ) {
            return;
		}

		global $wpdb;

        $wpdb->query(
            $wpdb->prepare(
                "DELETE FROM {$wpdb->options}
                WHERE option_name LIKE %s OR option_name LIKE %s",
                $wpdb->esc_like( '_transient_wccs-product-' ) . '%',
                $wpdb->esc_like( '_transient_timeout_wccs-product-' ) . '%'
            )
        );
	}

	public static function update_800() {
		global $wpdb;

		$results = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}wccs_condition_meta WHERE meta_key IN ( 'conditions', 'date_time', 'items', 'exclude_items', 'purchased_items' )" );
		if ( empty( $results ) ) {
			return;
		}

		foreach ( $results as $row ) {
			switch ( $row->meta_key ) {
				case 'conditions':
					self::add_or_condition( $row );
					break;

				case 'date_time':
					self::add_or_date_time( $row );
					break;

				case 'items':
					self::add_or_item( $row );
					break;

				default:
					self::add_or_rule( $row, $row->meta_key );
					break;
			}
		}

		self::clear_pricing_caches();
	}

	public static function update_850() {
		$settings = get_option( 'wccs_settings' );
		if ( empty( $settings ) || ! isset( $settings['change_display_price'] ) ) {
			return;
		}

		if ( 'simple' === $settings['change_display_price'] ) {
			$settings['change_display_price'] = 'all';
			update_option( 'wccs_settings', $settings );
		}
	}

	public static function update_880() {
		WCCS()->WCCS_Product_Price_Cache->clear_cache_deprecated();
        WCCS()->WCCS_Product_Quantity_Table_Cache->clear_cache_deprecated();
        WCCS()->WCCS_Product_Purchase_Message_Cache->clear_cache_deprecated();
        WCCS()->WCCS_Product_Onsale_Cache->clear_cache_deprecated();
	}

	public static function update_required() {
		$current_db_version = get_option( 'woocommerce_conditions_db_version' );
		$upgraded_from      = get_option( 'wccs_version_upgraded_from' );
		return false !== $upgraded_from && ( false === $current_db_version || version_compare( $current_db_version, '8.0.0', '<' ) );
	}

	protected static function add_or_condition( $row ) {
		if ( empty( $row ) || empty( $row->wccs_condition_id ) ) {
			return;
		}

		$rules = maybe_unserialize( $row->meta_value );
		if ( empty( $rules ) || isset( $rules[0][0] ) ) {
			return;
		}

		global $wpdb;
		$type = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}wccs_condition_meta WHERE wccs_condition_id = %d AND meta_key = 'conditions_match_mode'", (int) $row->wccs_condition_id ) );
		$type = ! empty( $type ) ? strtolower( $type ) : 'all';

		$update = array();
		if ( 'all' === $type ) {
			$update[] = $rules;
		} elseif ( 'one' === $type ) {
			foreach ( $rules as $rule ) {
				$update[] = array( $rule );
			}
		}

		WCCS()->condition_meta->update_meta( (int) $row->wccs_condition_id, 'conditions', $update );
	}

	protected static function add_or_date_time( $row ) {
		if ( empty( $row ) || empty( $row->wccs_condition_id ) ) {
			return;
		}

		$rules = maybe_unserialize( $row->meta_value );
		if ( empty( $rules ) || isset( $rules[0][0] ) ) {
			return;
		}

		global $wpdb;
		$type = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}wccs_condition_meta WHERE wccs_condition_id = %d AND meta_key = 'date_times_match_mode'", (int) $row->wccs_condition_id ) );
		$type = ! empty( $type ) ? strtolower( $type ) : 'one';

		$update = array();
		if ( 'all' === $type ) {
			$update[] = $rules;
		} elseif ( 'one' === $type ) {
			foreach ( $rules as $rule ) {
				$update[] = array( $rule );
			}
		}

		WCCS()->condition_meta->update_meta( (int) $row->wccs_condition_id, 'date_time', $update );
	}

	protected static function add_or_item( $row ) {
		if ( empty( $row ) || empty( $row->wccs_condition_id ) ) {
			return;
		}

		$rules = maybe_unserialize( $row->meta_value );
		if ( empty( $rules ) || isset( $rules[0][0] ) ) {
			return;
		}

		global $wpdb;

		$type = $wpdb->get_var( $wpdb->prepare( "SELECT `type` FROM {$wpdb->prefix}wccs_conditions WHERE id = %d", (int) $row->wccs_condition_id ) );
		if ( empty( $type ) || 'auto-add-products' === $type ) {
			return;
		}

		if ( 'pricing' === $type ) {
			$mode = $wpdb->get_var( $wpdb->prepare( "SELECT meta_value FROM {$wpdb->prefix}wccs_condition_meta WHERE wccs_condition_id = %d AND meta_key = 'mode'", (int) $row->wccs_condition_id ) );
			// Do not update pricing products-group mode items.
			if ( ! empty( $mode ) && 'products_group' === $mode ) {
				return;
			}
		}

		WCCS()->condition_meta->update_meta( (int) $row->wccs_condition_id, 'items', array( $rules ) );
	}

	protected static function add_or_rule( $row, $type ) {
		if ( empty( $row ) || empty( $type )|| empty( $row->wccs_condition_id ) ) {
			return;
		}

		$rules = maybe_unserialize( $row->meta_value );
		if ( empty( $rules ) || isset( $rules[0][0] ) ) {
			return;
		}

		WCCS()->condition_meta->update_meta( (int) $row->wccs_condition_id, sanitize_text_field( $type ), array( $rules ) );
	}

}
