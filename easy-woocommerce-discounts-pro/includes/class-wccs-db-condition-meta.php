<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WCCS database Condition meta.
 *
 * @since      1.0.0
 * @package    WCCS
 * @subpackage WCCS/classes
 * @author     Taher Atashbar <taher.atashbar@gmail.com>
 */
class WCCS_DB_Condition_Meta extends WCCS_DB {

	/**
	 * Sets up the Condition Meta DB class.
	 *
	 * @access public
	 * @since  1.0.0
	 */
	public function __construct() {
		global $wpdb;

		$this->table_name  = $wpdb->prefix . 'wccs_condition_meta';
		$this->primary_key = 'meta_id';
		$this->version     = '1.0';

		$this->register_table();
	}

	/**
	 * Retrieves the table columns and data types.
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @return array List of condition meta table columns and their respective types.
	 */
	public function get_columns() {
		return array(
			'meta_id'           => '%d',
			'wccs_condition_id' => '%d',
			'meta_key'          => '%s',
			'meta_value'        => '%s',
		);
	}

	/**
	 * Registers the table with $wpdb so the metadata api can find it.
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @global wpdb $wpdb WordPress database abstraction object.
	 */
	public function register_table() {
		global $wpdb;
		$wpdb->wccs_conditionmeta = $this->table_name;
	}

	/**
	 * Retrieves a condition meta field for a condition.
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @param  int    $condition_id      Optional. Condition ID. Default 0.
	 * @param  string $meta_key     Optional. The meta key to retrieve. Default empty.
	 * @param  bool   $single       Optional. Whether to return a single value. Default false.
	 * @return mixed Will be an array if $single is false. Will be value of meta data field if $single is true.
	 */
	public function get_meta( $condition_id = 0, $meta_key = '', $single = false ) {
		return get_metadata( 'wccs_condition', $condition_id, $meta_key, $single );
	}

	/**
	 * Adds a meta data field to a condition.
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @param  int    $condition_id      Optional. Condition ID. Default 0.
	 * @param  string $meta_key     Optional. Meta data key. Default empty.
	 * @param  mixed  $meta_value   Optional. Meta data value. Default empty.
	 * @param  bool   $unique       Optional. Whether the same key should not be added. Default false.
	 * @return bool False for failure. True for success.
	 */
	public function add_meta( $condition_id = 0, $meta_key = '', $meta_value = '', $unique = false ) {
		return add_metadata( 'wccs_condition', $condition_id, $meta_key, $meta_value, $unique );
	}

	/**
	 * Updates a condition meta field based on condition ID.
	 *
	 * Use the $prev_value parameter to differentiate between meta fields with the
	 * same key and condition ID.
	 *
	 * If the meta field for the condition does not exist, it will be added.
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @param  int    $condition_id      Optional. Condition ID. Default 0.
	 * @param  string $meta_key     Optional. Meta data key. Default empty.
	 * @param  mixed  $meta_value   Optional. Meta data value. Default empty.
	 * @param  mixed  $prev_value   Optional. Previous value to check before removing. Default empty.
	 * @return bool False on failure, true if success.
	 */
	public function update_meta( $condition_id = 0, $meta_key = '', $meta_value = '', $prev_value = '' ) {
		return update_metadata( 'wccs_condition', $condition_id, $meta_key, $meta_value, $prev_value );
	}

	/**
	 * Removes metadata matching criteria from an condition.
	 *
	 * You can match based on the key, or key and value. Removing based on key and
	 * value, will keep from removing duplicate metadata with the same key. It also
	 * allows removing all metadata matching key, if needed.
	 *
	 * @access public
	 * @since  1.0.0
	 *
	 * @param  int    $condition_id      Optional. Condition ID. Default 0.
	 * @param  string $meta_key     Optional. Meta data key. Default empty.
	 * @param  mixed  $meta_value   Optional. Meta data value. Default empty.
	 * @return bool False for failure. True for success.
	 */
	public function delete_meta( $condition_id = 0, $meta_key = '', $meta_value = '' ) {
		return delete_metadata( 'wccs_condition', $condition_id, $meta_key, $meta_value );
	}

	/**
	 * Deleting all meta data of a condition.
	 *
	 * @since  1.0.0
	 *
	 * @param  $condition_id int     ID of an condition that it's meta data should be deleted.
	 *
	 * @return bool
	 */
	public function delete_all_meta( $condition_id ) {
		$condition_id = absint( $condition_id );

		if ( ! $condition_id ) {
			return false;
		}

		global $wpdb;

		$delete = $wpdb->delete( $this->table_name, array( 'wccs_condition_id' => $condition_id ), array( '%d' ) );

		return false !== $delete;
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
		
		$sql = "CREATE TABLE {$this->table_name} (
			meta_id bigint(20) NOT NULL AUTO_INCREMENT,
			wccs_condition_id bigint(20) NOT NULL,
			meta_key varchar(255) DEFAULT NULL,
			meta_value longtext,
			PRIMARY KEY  (meta_id),
			KEY wccs_condition_id (wccs_condition_id),
			KEY meta_key (meta_key)
			) $collate;";

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}

}
