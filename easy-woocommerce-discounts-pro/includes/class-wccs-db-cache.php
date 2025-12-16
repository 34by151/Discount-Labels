<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_DB_Cache extends WCCS_DB {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;

		$this->table_name  = $wpdb->prefix . 'wccs_cache';
		$this->primary_key = 'id';
		$this->version     = '1.0';
	}

	/**
	 * Get columns and formats
	 *
	 * @since   1.0.0
	 *
	 * @return  array
	 */
	public function get_columns() {
		return array(
			'id'         => '%d',
			'product_id' => '%d',
			'cache_type' => '%s',
			'value'      => '%s',
		);
	}

	/**
	 * Get default column values.
	 *
	 * @since  1.0.0
	 *
	 * @return array
	 */
	public function get_column_defaults() {
		return array();
	}

	/**
	 * Add a condition.
	 * Update condition if exists otherwise insert new one.
	 *
	 * @since  1.0.0
	 *
	 * @param  array $data
	 *
	 * @return false|int
	 */
	public function add( array $args = array() ) {
		if ( isset( $args['id'] ) ) {
			$item = $this->get_item( $args['id'] );
			unset( $args['id'] );
			if ( $item ) {
				$this->update( $item->id, $args );
				return $item->id;
			}
		} elseif ( ! empty( $args['product_id'] ) && ! empty( $args['cache_type'] ) ) {
			$item = $this->get_item_by_product( $args['product_id'], $args['cache_type'] );
			if ( $item ) {
				unset( $args['product_id'], $args['cache_type'] );
				$this->update( $item->id, $args );
				return $item->id;
			}
		}

		$args = wp_parse_args( $args, $this->get_column_defaults() );
		$id   = $this->insert( $args, 'cache' );

		return $id ? $id : false;
	}

	/**
	 * Retrieves a single condition from the database;
	 *
	 * @since  1.0.0
	 *
	 * @param  int     $id
	 * @param  string  $output
	 * @param  boolean $include_meta
	 *
	 * @return Object|Array|false  False on failure
	 */
	public function get_item( $id, $output = OBJECT ) {
		$id = absint( $id );
		if ( 0 >= $id ) {
			return false;
		}

		global $wpdb;

		$item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE id = %d LIMIT 1", $id ), $output );

		if ( $item ) {
			if ( is_object( $item ) ) {
				$item->value = maybe_unserialize( $item->value );
			} elseif ( isset( $item['value'] ) ) {
				$item['value'] = maybe_unserialize( $item['value'] );
			}
		}

		return $item ? $item : false;
	}
	
	public function get_item_by_product( $product_id, $type, $output = OBJECT ) {
		$product_id = absint( $product_id );
		if ( 0 >= $product_id ) {
			return false;
		}

		if ( '' === trim( $type ) ) {
			return false;
		}

		global $wpdb;

		$item = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE product_id = %d AND cache_type = %s LIMIT 1", $product_id, sanitize_text_field( $type ) ), $output );

		if ( $item ) {
			if ( is_object( $item ) ) {
				$item->value = maybe_unserialize( $item->value );
			} elseif ( isset( $item['value'] ) ) {
				$item['value'] = maybe_unserialize( $item['value'] );
			}
		}

		return $item ? $item : false;
	}

	/**
	 * Deleting a condition by it's id.
	 *
	 * @since  1.0.0
	 *
	 * @param  int $id
	 *
	 * @return boolean
	 */
	public function delete( $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}

		$item = $this->get_item( $id );

		if ( $item && ! empty( $item->id ) ) {
			global $wpdb;
			return $wpdb->delete( $this->table_name, array( 'id' => $item->id ), array( '%d' ) );
		}

		return false;
	}

	public function delete_item_by_product( $product_id, $type = '' ) {
		$product_id = absint( $product_id );
		if ( 0 >= $product_id ) {
			return false;
		}

		global $wpdb;

		if ( '' === trim( $type ) ) {
			return $wpdb->delete( $this->table_name, array( 'product_id' => $product_id ), array( '%d' ) );
		}

		return $wpdb->delete( $this->table_name, array( 'product_id' => $product_id, 'cache_type' => sanitize_text_field( $type ) ), array( '%d', '%s' ) );
	}

	public function delete_items_by_type( $type ) {
		if ( '' === trim( $type ) ) {
			return false;
		}

		global $wpdb;
		return $wpdb->delete( $this->table_name, array( 'cache_type' => sanitize_text_field( $type ) ), array( '%s' ) );
	}

	public function delete_product_price( $product_id ) {
		return $this->delete_item_by_product( $product_id, 'price' );
	}

	public function clear_cache() {
		return $this->delete_all_data();        
	}

	/**
	 * Counting number of conditions.
	 *
	 * @since  1.0.0
	 *
	 * @return int
	 */
	public function count() {
		global $wpdb;

		$count = $wpdb->get_var( "SELECT COUNT( $this->primary_key ) FROM $this->table_name" );

		return absint( $count );
	}

	/**
	 * Create the table
	 *
	 * @since 1.0.0
	 */
	public function create_table() {
		global $wpdb;

		$collate = '';
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$sql = "CREATE TABLE " . $this->table_name . " (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		product_id bigint(20) NOT NULL,
		cache_type varchar(20) NOT NULL DEFAULT '',
		value longtext NOT NULL DEFAULT '',
		PRIMARY KEY (id)
		) $collate;";

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}

}
