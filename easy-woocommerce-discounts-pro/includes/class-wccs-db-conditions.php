<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_DB_Conditions extends WCCS_DB {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 */
	public function __construct() {
		global $wpdb;

		$this->table_name  = $wpdb->prefix . 'wccs_conditions';
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
			'id'       => '%d',
			'name'     => '%s',
			'type'     => '%s',
			'ordering' => '%d',
			'status'   => '%d',
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
	public function add( array $data = array() ) {
		$defaults = $this->get_column_defaults();

		$args = wp_parse_args( $data, $defaults );

		if ( empty( $args['id'] ) && empty( $args['type'] ) ) {
			return false;
		}

		if ( isset( $args['id'] ) ) {
			$condition = $this->get_condition( $args['id'] );
			if ( $condition ) {
				$this->update( $condition->id, $args );
				return $condition->id;
			}
		}

		// setting ordering.
		if ( empty( $args['ordering'] ) ) {
			$args['ordering'] = $this->get_great_order( $args['type'] ) + 1;
		}

		$condition_id = $this->insert( $args, 'condition' );

		return $condition_id ? $condition_id : false;
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
	public function get_condition( $id, $output = OBJECT, $include_meta = true ) {
		global $wpdb;

		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}

		$condition = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $this->table_name WHERE id = %d LIMIT 1", $id ), $output );

		if ( $condition && $include_meta ) {
			$meta_data = WCCS()->condition_meta->get_meta( $condition->id, '', true );
			if ( ! empty( $meta_data ) ) {
				foreach ( $meta_data as $key => $value ) {
					if ( is_object( $condition ) ) {
						if ( ! isset( $condition->{$key} ) ) {
							$condition->{$key} = maybe_unserialize( $value[0] );
						}
					} elseif ( isset( $condition['id'] ) ) {
						if ( ! isset( $condition[ $key ] ) ) {
							$condition[ $key ] = maybe_unserialize( $value[0] );
						}
					}
				}
			}
		}

		return $condition ? $condition : false;
	}

	public function get_conditions_by_name( $name, array $args = [] ) {
		$args = array_merge( [
			'title'  => $name,
			'number' => -1,
		], $args );

		if ( is_null( $args['title'] ) || '' === $args['title'] || ctype_space( $args['title'] ) ) {
			return false;
		}

		return $this->get_conditions( $args );
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

		$condition = $this->get_condition( $id );

		if ( $condition->id > 0 ) {
			global $wpdb;
			$delete = $wpdb->delete( $this->table_name, array( 'id' => $condition->id ), array( '%d' ) );

			if ( $delete ) {
				WCCS()->condition_meta->delete_all_meta( $condition->id );
			}

			return $delete;
		}

		return false;
	}

	/**
	 * Retrieve conditions from the database
	 *
	 * @since  1.0.0
	 *
	 * @param  array  $args
	 *
	 * @return array
	 */
	public function get_conditions( array $args = array() ) {
		global $wpdb;

		$defaults = array(
			'number'       => 20,
			'offset'       => 0,
			'orderby'      => 'id',
			'order'        => 'DESC',
			'output'       => OBJECT,
			'include_meta' => true,
		);

		$args = wp_parse_args( $args, $defaults );

		if ( $args['number'] < 1 ) {
			$args['number'] = 999999999999;
		}

		$args['orderby'] = ! array_key_exists( $args['orderby'], $this->get_columns() ) ? 'id' : $args['orderby'];
		$args['orderby'] = esc_sql( $args['orderby'] );
		$args['order']   = esc_sql( $args['order'] );

		$select_args = array();
		$where       = ' WHERE 1=1';

		// Specific conditions.
		if ( ! empty( $args['id'] ) ) {
			if ( is_array( $args['id'] ) ) {
				$ids = implode( ',', array_map( 'absint', $args['id'] ) );
			} else {
				$ids = absint( $args['id'] );
			}
			$where .= " AND `id` IN( {$ids} )";
		}

		// Specific type.
		if ( ! empty( $args['type'] ) ) {
			if ( is_array( $args['type'] ) ) {
				$types = implode( "','", array_map( 'esc_sql', $args['type'] ) );
			} else {
				$types = esc_sql( $args['type'] );
			}
			$where .= " AND `type` IN( '{$types}' )";
		}

		// Search by name.
		if ( ! empty( $args['name'] ) ) {
			$where         .= ' AND LOWER(`name`) LIKE %s';
			$select_args[] = '%' . $wpdb->esc_like( strtolower( sanitize_text_field( $args['name'] ) ) ) . '%';
			// Exact search by name.
		} elseif ( ! empty( $args['title'] ) ) {
			$where .= ' AND LOWER(`name`) = %s';
			$select_args[] = esc_sql( sanitize_text_field( $args['title'] ) );
		}

		// Status.
		if ( isset( $args['status'] ) ) {
			$where .= " AND `status` = " . intval( $args['status'] );
		}

		$select_args[] = absint( $args['offset'] );
		$select_args[] = absint( $args['number'] );

		$conditions = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM $this->table_name $where ORDER BY {$args['orderby']} {$args['order']} LIMIT %d,%d;", $select_args ), $args['output'] );

		if ( ! empty( $conditions ) && $args['include_meta'] ) {
			$wccs = WCCS();
			foreach ( $conditions as &$condition ) {
				if ( is_object( $condition ) ) {
					$meta_data = $wccs->condition_meta->get_meta( $condition->id, '', true );
					if ( ! empty( $meta_data ) ) {
						foreach ( $meta_data as $key => $value ) {
							if ( ! isset( $condition->{$key} ) ) {
								$condition->{$key} = maybe_unserialize( $value[0] );
							}
						}
					}
				} elseif ( isset( $condition['id'] ) ) {
					$meta_data = $wccs->condition_meta->get_meta( $condition['id'], '', true );
					if ( ! empty( $meta_data ) ) {
						foreach ( $meta_data as $key => $value ) {
							if ( ! isset( $condition[ $key ] ) ) {
								$condition[ $key ] = maybe_unserialize( $value[0] );
							}
						}
					}
				}
			}
		}

		return $conditions;
	}

	/**
	 * Updating conditions ordering.
	 *
	 * @since  1.0.0
	 *
	 * @param  array $conditions
	 *
	 * @return boolean
	 */
	public function update_conditions_ordering( array $conditions ) {
		if ( empty( $conditions ) ) {
			return false;
		}

		global $wpdb;

		foreach ( $conditions as $condition ) {
			if ( empty( $condition['id'] ) || empty( $condition['ordering'] ) ) {
				continue;
			}

			if ( false === $wpdb->update( $this->table_name, array( 'ordering' => $condition['ordering'] ), array( 'id' => $condition['id'] ), '%d', '%d' ) ) {
				return false;
			}
		}

		return true;
	}

	/**
	 * Duplicating a condition.
	 *
	 * @since  2.1.0
	 *
	 * @param  int $id
	 *
	 * @return int|false condition id on success and false on failure.
	 */
	public function duplicate( $id ) {
		$id = absint( $id );
		if ( ! $id ) {
			return false;
		}

		$condition = $this->get_condition( $id, ARRAY_A, false );
		if ( ! $condition ) {
			return false;
		}

		unset( $condition['id'] );
		$condition['name'] = sprintf( '%s (Copy)', $condition['name'] );
		$condition_id = $this->add( $condition );
		if ( ! $condition_id ) {
			return false;
		}

		$meta_data = WCCS()->condition_meta->get_meta( $id, '', true );
		if ( ! empty( $meta_data ) ) {
			foreach ( $meta_data as $meta_key => $meta_value ) {
				if ( ! WCCS()->condition_meta->add_meta( $condition_id, $meta_key, maybe_unserialize( $meta_value[0] ), true ) ) {
					return false;
				}
			}
		}

		return $condition_id;
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
	 * Getting great order in conditions by type.
	 *
	 * @since  1.0.0
	 *
	 * @return int
	 */
	protected function get_great_order( $type ) {
		global $wpdb;
		$great_order = $wpdb->get_var( $wpdb->prepare( "SELECT MAX(ordering) FROM $this->table_name WHERE `type` = %s", sanitize_text_field( $type ) ) );

		return absint( $great_order );
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
		type varchar(200) NOT NULL,
		name mediumtext NOT NULL DEFAULT '',
		ordering MEDIUMINT NOT NULL DEFAULT 0,
		status TINYINT NOT NULL DEFAULT '1',
		PRIMARY KEY  (id)
		) $collate;";

		dbDelta( $sql );

		update_option( $this->table_name . '_db_version', $this->version );
	}

}
