<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_DB_User_Usage_Logs extends WCCS_DB {

    /**
	 * Constructor.
	 *
	 * @since 8.19.0
	 */
	public function __construct() {
		global $wpdb;

		$this->table_name  = $wpdb->prefix . 'wccs_user_usage_logs';
		$this->primary_key = 'id';
		$this->version     = '1.0';
	}

	/**
	 * Get columns and formats
	 *
	 * @since   8.19.0
	 *
	 * @return  array
	 */
	public function get_columns() {
		return array(
			'id'          => '%d',
			'rule_id'     => '%d',
			'used_by'     => '%s',
			'usage_count' => '%d',
			'last_used'   => '%s',
		);
	}

	/**
	 * Get default column values.
	 *
	 * @since  8.19.0
	 *
	 * @return array
	 */
	public function get_column_defaults() {
		return array();
	}

	public function log_user_usage( $rule_id, $used_by ) {
		if ( empty( $rule_id ) || empty( $used_by ) ) {
			throw new Exception( 'Rule ID and Used by is required.' );
		}

		global $wpdb;
	
		// Check if the user or guest already has a log for this rule
		$existing_log = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM {$this->table_name} WHERE rule_id = %d AND used_by = %s",
			$rule_id, $used_by
		) );
	
		if ( $existing_log ) {
			// Update usage count for existing log
			$wpdb->query( $wpdb->prepare(
				"UPDATE {$this->table_name} SET usage_count = usage_count + 1, last_used = NOW() WHERE id = %d",
				$existing_log
			) );
		} else {
			// Insert new log for the user or guest
			$this->insert( [
				'rule_id'     => $rule_id,
				'used_by'     => $used_by,
				'usage_count' => 1,
				'last_used'   => current_time( 'mysql' )
			], 'usage_user_log' );
		}
	}

	public function get_user_usage_count( $rule_id, $used_by ) {
		if ( empty( $rule_id ) || empty( $used_by ) ) {
			throw new Exception( 'Rule ID and Used by is required.' );
		}

		global $wpdb;

		$usage_count = $wpdb->get_var( $wpdb->prepare(
			"SELECT SUM(usage_count) FROM {$this->table_name} WHERE rule_id = %d AND used_by = %s",
			$rule_id, $used_by
		) );

		return (int) $usage_count;
	}

    /**
	 * Create the table
	 *
	 * @since 8.19.0
	 */
	public function create_table() {
		global $wpdb;

		$collate = '';
		if ( $wpdb->has_cap( 'collation' ) ) {
			$collate = $wpdb->get_charset_collate();
		}

		$sql = "CREATE TABLE " . $this->table_name . " (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        rule_id BIGINT(20) UNSIGNED NOT NULL,
        used_by VARCHAR(300) NOT NULL,
        usage_count INT(11) DEFAULT 1,
        last_used DATETIME DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY rule_id (rule_id),
        KEY used_by (used_by)
		) $collate;";

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}

}
