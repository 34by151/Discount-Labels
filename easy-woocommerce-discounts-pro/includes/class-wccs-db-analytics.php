<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_DB_Analytics extends WCCS_DB {

    /**
	 * Constructor.
	 *
	 * @since 9.0.0
	 */
	public function __construct() {
		global $wpdb;

		$this->table_name  = $wpdb->prefix . 'wccs_asnp_analytics';
		$this->primary_key = 'rule_id';
		$this->version     = '1.0';
	}

	/**
	 * Get columns and formats
	 *
	 * @since   9.0.0
	 *
	 * @return  array
	 */
	public function get_columns() {
		return [
			'rule_id'          => '%d',
			'date'             => '%s',
			'impressions'      => '%d',
			'add_to_cart'      => '%d',
			'checkouts'        => '%d',
			'orders'           => '%d',
			'rejections'       => '%d',
			'free_shippings'   => '%d',
			'items_discounted' => '%d',
			'revenue'          => '%f',
			'discounts'        => '%f',
		];
	}

	/**
	 * Get default column values.
	 *
	 * @since  9.0.0
	 *
	 * @return array
	 */
	public function get_column_defaults() {
		return [];
	}

	/**
     * Log an event (impression, add_to_cart, order, checkout, rejection).
     *
     * @param int    $rule_id
     * @param string $event_type (impression|add_to_cart|order|checkout|rejection)
     * @param array  $args       (optional: revenue, discounts, items_discounted, free_shippings)
     */
    public function log_event( $rule_id, $event_type, $count = 1, $args = [] ) {
        global $wpdb;

        $date = current_time( 'Y-m-d' );

        // Defaults
        $revenue            = isset( $args['revenue'] ) ? floatval( $args['revenue'] ) : 0;
        $discounts          = isset( $args['discounts'] ) ? floatval( $args['discounts'] ) : 0;
		$shipping_discounts = isset( $args['shipping_discounts'] ) ? floatval( $args['shipping_discounts'] ) : 0;
		$fees               = isset( $args['fees'] ) ? floatval( $args['fees'] ) : 0;
		$items_discounted   = isset( $args['items_discounted'] ) ? absint( $args['items_discounted'] ) : 0;
		$free_shippings     = isset( $args['free_shippings'] ) ? absint( $args['free_shippings'] ) : 0;

        $columns = [
            'impression'    => 'impressions',
            'add_to_cart'   => 'add_to_cart',
            'order'         => 'orders',
			'checkout'      => 'checkouts',
            'rejection'     => 'rejections',
        ];

        if ( ! isset( $columns[ $event_type ] ) ) {
            return; // invalid type
        }

        $col = $columns[ $event_type ];

        // Insert or update (atomic)
        $wpdb->query( $wpdb->prepare("
            INSERT INTO {$this->table_name}
                (rule_id, date, {$col}, revenue, discounts, shipping_discounts, fees, items_discounted, free_shippings)
            VALUES (%d, %s, %d, %f, %f, %f, %f, %d, %d)
            ON DUPLICATE KEY UPDATE
                {$col} = {$col} + VALUES({$col}),
                revenue = revenue + VALUES(revenue),
                discounts = discounts + VALUES(discounts),
				shipping_discounts = shipping_discounts + VALUES(shipping_discounts),
				fees = fees + VALUES(fees),
				items_discounted = items_discounted + VALUES(items_discounted),
				free_shippings = free_shippings + VALUES(free_shippings)
        ", $rule_id, $date, absint( $count ), $revenue, $discounts, $shipping_discounts, $fees, $items_discounted, $free_shippings ) );
    }

	public function log_events( $event_type, $rules, $args = [] ) {
		if ( empty( $rules ) ) {
			return;
		}

		global $wpdb;

        $date = current_time( 'Y-m-d' );

        $columns = [
            'impression'    => 'impressions',
            'add_to_cart'   => 'add_to_cart',
            'order'         => 'orders',
			'checkout'      => 'checkouts',
            'rejection'     => 'rejections',
        ];

        if ( ! isset( $columns[ $event_type ] ) ) {
            return; // invalid type
        }

        $col = $columns[ $event_type ];

		$values = [];
		$values_sql = [];

		foreach ( $rules as $rule_id => $count ) {
			$revenue            = isset( $args[ $rule_id ]['revenue'] ) ? floatval( $args[ $rule_id ]['revenue'] ) : 0;
        	$discounts          = isset( $args[ $rule_id ]['discounts'] ) ? floatval( $args[ $rule_id ]['discounts'] ) : 0;
			$shipping_discounts = isset( $args[ $rule_id ]['shipping_discounts'] ) ? floatval( $args[ $rule_id ]['shipping_discounts'] ) : 0;
			$fees               = isset( $args[ $rule_id ]['fees'] ) ? floatval( $args[ $rule_id ]['fees'] ) : 0;
			$items_discounted   = isset( $args[ $rule_id ]['items_discounted'] ) ? absint( $args[ $rule_id ]['items_discounted'] ) : 0;
			$free_shippings     = isset( $args[ $rule_id ]['free_shippings'] ) ? absint( $args[ $rule_id ]['free_shippings'] ) : 0;

			$values_sql[] = '(%d, %s, %d, %f, %f, %f, %f, %d, %d)';

			$values[] = absint( $rule_id );
			$values[] = $date;
			$values[] = absint( $count );
			$values[] = $revenue;
			$values[] = $discounts;
			$values[] = $shipping_discounts;
			$values[] = $fees;
			$values[] = $items_discounted;
			$values[] = $free_shippings;
		}

		$wpdb->query( $wpdb->prepare("
            INSERT INTO {$this->table_name}
                (rule_id, date, {$col}, revenue, discounts, shipping_discounts, fees, items_discounted, free_shippings)
            VALUES " . implode( ', ', $values_sql ) . "
            ON DUPLICATE KEY UPDATE
                {$col} = {$col} + VALUES({$col}),
                revenue = revenue + VALUES(revenue),
                discounts = discounts + VALUES(discounts),
				shipping_discounts = shipping_discounts + VALUES(shipping_discounts),
				fees = fees + VALUES(fees),
				items_discounted = items_discounted + VALUES(items_discounted),
				free_shippings = free_shippings + VALUES(free_shippings)
        ", $values ) );
	}

    /**
	 * Create the table
	 *
	 * @since 9.0.0
	 */
	public function create_table() {
		global $wpdb;

		$collate = '';
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$sql = "CREATE TABLE {$this->table_name} (
        rule_id BIGINT(20) UNSIGNED NOT NULL,
		date DATE NOT NULL,
		impressions BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		add_to_cart BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		checkouts BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		orders BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		rejections BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		free_shippings BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		items_discounted BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
		revenue double NOT NULL DEFAULT 0,
		discounts double NOT NULL DEFAULT 0,
		shipping_discounts double NOT NULL DEFAULT 0,
		fees double NOT NULL DEFAULT 0, 
        PRIMARY KEY (rule_id, date),
        KEY rule_id (rule_id),
		KEY date (date)
		) $collate;";

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}

}
