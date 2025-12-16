<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The class responsible for Ajax operations of the plugin..
 *
 * @package    WC_Conditions
 * @subpackage WC_Conditions/admin
 * @author     Taher Atashbar <taher.atashbar@gmail.com>
 */
class WCCS_Admin_Ajax {

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param WCCS_Loader $loader
	 */
	public function __construct( WCCS_Loader $loader ) {
		$loader->add_action( 'wp_ajax_wccs_save_condition', $this, 'save_condition' );
		$loader->add_action( 'wp_ajax_wccs_delete_condition', $this, 'delete_condition' );
		$loader->add_action( 'wp_ajax_wccs_update_condition', $this, 'update_condition' );
		$loader->add_action( 'wp_ajax_wccs_update_conditions_ordering', $this, 'update_conditions_ordering' );
		$loader->add_action( 'wp_ajax_wccs_duplicate_condition', $this, 'duplicate_condition' );
		$loader->add_action( 'wp_ajax_wccs_select_autocomplete', $this, 'select_autocomplete' );
		$loader->add_action( 'wp_ajax_wccs_select_options', $this, 'select_options' );
		$loader->add_action( 'wp_ajax_wccs_get_addons', $this, 'get_addons' );
		$loader->add_action( 'wp_ajax_wccs_get_coupon_code', $this, 'get_coupon_code' );
		$loader->add_action( 'wp_ajax_wccs_live_price', $this, 'live_price' );
		$loader->add_action( 'wp_ajax_nopriv_wccs_live_price', $this, 'live_price' );
	}

	/**
	 * Save a condition.
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	public function save_condition() {
		check_ajax_referer( 'wccs_conditions_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$errors = array();

		$type = isset( $_POST['type'] ) ? sanitize_text_field( $_POST['type'] ) : '';

		if ( empty( $type ) ) {
			$errors[] = __( 'Condition type required', 'easy-woocommerce-discounts' );
		}

		if ( ! empty( $errors ) ) {
			die(
				json_encode(
					array(
						'success' => 0,
						'message' => __( 'Some errors occurred in saving condition.', 'easy-woocommerce-discounts' ),
						'errors' => $errors,
					)
				)
			);
		}

		$wccs = WCCS();
		$conditions_db = $wccs->conditions;
		$condition_meta = $wccs->condition_meta;

		$data = array(
			'type' => $type,
			'name' => isset( $_POST['name'] ) ? sanitize_text_field( $_POST['name'] ) : '',
			'status' => isset( $_POST['status'] ) ? (int) $_POST['status'] : 1,
		);

		if ( ! empty( $_POST['id'] ) && (int) $_POST['id'] > 0 ) {
			$data['id'] = intval( $_POST['id'] );
		}

		if ( ! empty( $_POST['ordering'] ) ) {
			$data['ordering'] = (int) $_POST['ordering'];
		}

		if ( 'cart-discount' === $type && ! empty( $data['name'] ) ) {
			$data['name'] = wc_format_coupon_code( $data['name'] );
		}

		$condition_id = $conditions_db->add( $data );

		if ( $condition_id ) {
			$conditions = ! empty( $_POST['conditions'] ) ? wp_kses_post_deep( $_POST['conditions'] ) : array();
			$condition_meta->update_meta( $condition_id, 'conditions', $conditions );

			$meta_data = array(
				'date_time' => ! empty( $_POST['date_time'] ) ? map_deep( $_POST['date_time'], 'sanitize_text_field' ) : array(),
			);

			// Products list condition meta data.
			if ( 'products-list' === $type ) {
				$meta_data['include'] = ! empty( $_POST['include'] ) ? map_deep( $_POST['include'], 'sanitize_text_field' ) : array();
				$meta_data['exclude'] = ! empty( $_POST['exclude'] ) ? map_deep( $_POST['exclude'], 'sanitize_text_field' ) : array();
				$meta_data['paginate'] = ! empty( $_POST['paginate'] ) ? sanitize_text_field( $_POST['paginate'] ) : 'true';
			} // Cart Discount condition meta data.
			elseif ( 'cart-discount' === $type ) {
				if ( ! empty( $data['name'] ) ) {
					wp_cache_delete( 'asnp_ewd_coupon_id_from_code_' . $data['name'], 'asnp_ewd_discounts' );
				}

				$meta_data['private_note'] = ! empty( $_POST['private_note'] ) ? sanitize_text_field( $_POST['private_note'] ) : '';
				$meta_data['apply_mode'] = ! empty( $_POST['apply_mode'] ) ? sanitize_text_field( $_POST['apply_mode'] ) : 'all';
				$meta_data['discount_type'] = ! empty( $_POST['discount_type'] ) ? sanitize_text_field( $_POST['discount_type'] ) : 'percentage';
				$meta_data['discount_amount'] = ! empty( $_POST['discount_amount'] ) ? floatval( $_POST['discount_amount'] ) : 0;
				$meta_data['items'] = ! empty( $_POST['items'] ) ? map_deep( $_POST['items'], 'sanitize_text_field' ) : array();
				$meta_data['exclude_items'] = ! empty( $_POST['exclude_items'] ) ? map_deep( $_POST['exclude_items'], 'sanitize_text_field' ) : array();
				$meta_data['manual'] = ! empty( $_POST['manual'] ) ? absint( $_POST['manual'] ) : 0;
				$meta_data['usage_limit'] = ! empty( $_POST['usage_limit'] ) ? absint( $_POST['usage_limit'] ) : '';
			} // Checkout Fee condition meta data.
			elseif ( 'checkout-fee' === $type ) {
				$meta_data['private_note'] = ! empty( $_POST['private_note'] ) ? sanitize_text_field( $_POST['private_note'] ) : '';
				$meta_data['apply_mode'] = ! empty( $_POST['apply_mode'] ) ? sanitize_text_field( $_POST['apply_mode'] ) : 'all';
				$meta_data['fee_type'] = ! empty( $_POST['fee_type'] ) ? sanitize_text_field( $_POST['fee_type'] ) : 'price_fee';
				$meta_data['fee_amount'] = ! empty( $_POST['fee_amount'] ) ? floatval( $_POST['fee_amount'] ) : 0;
				$meta_data['items'] = ! empty( $_POST['items'] ) ? map_deep( $_POST['items'], 'sanitize_text_field' ) : array();
				$meta_data['exclude_items'] = ! empty( $_POST['exclude_items'] ) ? map_deep( $_POST['exclude_items'], 'sanitize_text_field' ) : array();
				$meta_data['usage_limit'] = ! empty( $_POST['usage_limit'] ) ? absint( $_POST['usage_limit'] ) : '';
			} // Shipping method condition meta data.
			elseif ( 'shipping' === $type ) {
				$meta_data['private_note'] = ! empty( $_POST['private_note'] ) ? sanitize_text_field( $_POST['private_note'] ) : '';
				$meta_data['apply_mode'] = ! empty( $_POST['apply_mode'] ) ? sanitize_text_field( $_POST['apply_mode'] ) : 'all';
				$meta_data['tax_status'] = ! empty( $_POST['tax_status'] ) ? sanitize_text_field( $_POST['tax_status'] ) : 'taxable';
				$meta_data['cost'] = ! empty( $_POST['cost'] ) ? floatval( $_POST['cost'] ) : 0;
				$meta_data['cost_per_quantity'] = ! empty( $_POST['cost_per_quantity'] ) ? sanitize_text_field( $_POST['cost_per_quantity'] ) : 0;
				$meta_data['cost_per_weight'] = ! empty( $_POST['cost_per_weight'] ) ? sanitize_text_field( $_POST['cost_per_weight'] ) : 0;
				$meta_data['fee'] = ! empty( $_POST['fee'] ) ? sanitize_text_field( $_POST['fee'] ) : 0;
				$meta_data['min_fee'] = isset( $_POST['min_fee'] ) && '' !== $_POST['min_fee'] ? floatval( $_POST['min_fee'] ) : '';
				$meta_data['max_fee'] = isset( $_POST['max_fee'] ) && '' !== $_POST['max_fee'] ? floatval( $_POST['max_fee'] ) : '';
				$meta_data['usage_limit'] = ! empty( $_POST['usage_limit'] ) ? absint( $_POST['usage_limit'] ) : '';
			} // Pricing condition meta data.
			elseif ( 'pricing' === $type ) {
				if ( ! empty( $_POST['mode'] ) ) {
					$meta_data['apply_mode'] = ! empty( $_POST['apply_mode'] ) ? sanitize_text_field( $_POST['apply_mode'] ) : 'all';
					$meta_data['mode'] = sanitize_text_field( $_POST['mode'] );
					$meta_data['items'] = ! empty( $_POST['items'] ) ? map_deep( $_POST['items'], 'sanitize_text_field' ) : array();
					$meta_data['exclude_items'] = ! empty( $_POST['exclude_items'] ) ? map_deep( $_POST['exclude_items'], 'sanitize_text_field' ) : array();
					$meta_data['usage_limit'] = ! empty( $_POST['usage_limit'] ) ? absint( $_POST['usage_limit'] ) : '';
					$meta_data['message_type'] = ! empty( $_POST['message_type'] ) ? sanitize_text_field( $_POST['message_type'] ) : 'text_message';
					$meta_data['message_background_color'] = ! empty( $_POST['message_background_color'] ) ? sanitize_text_field( $_POST['message_background_color'] ) : '';
					$meta_data['message_color'] = ! empty( $_POST['message_color'] ) ? sanitize_text_field( $_POST['message_color'] ) : '';
					$meta_data['receive_message'] = ! empty( $_POST['receive_message'] ) ? wp_kses_post( $_POST['receive_message'] ) : '';

					$delete_meta = array();

					if ( 'bulk' === $_POST['mode'] || 'tiered' === $_POST['mode'] ) {
						$meta_data['quantity_based_on'] = ! empty( $_POST['quantity_based_on'] ) ? sanitize_text_field( $_POST['quantity_based_on'] ) : 'single_product';
						$meta_data['quantities'] = ! empty( $_POST['quantities'] ) ? map_deep( $_POST['quantities'], 'sanitize_text_field' ) : array();
						$meta_data['set_min_quantity'] = ! empty( $_POST['set_min_quantity'] ) ? sanitize_text_field( $_POST['set_min_quantity'] ) : 'false';

						if ( 'bulk' === $_POST['mode'] ) {
							$meta_data['display_quantity'] = ! empty( $_POST['display_quantity'] ) ? sanitize_text_field( $_POST['display_quantity'] ) : 'yes';
							$meta_data['display_price'] = ! empty( $_POST['display_price'] ) ? sanitize_text_field( $_POST['display_price'] ) : 'yes';
							$meta_data['display_discount'] = ! empty( $_POST['display_discount'] ) ? sanitize_text_field( $_POST['display_discount'] ) : 'no';
						}

						if ( 'tiered' === $_POST['mode'] ) {
							$meta_data['reorder'] = ! empty( $_POST['reorder'] ) && in_array( strtolower( $_POST['reorder'] ), array( 'asc', 'desc' ) ) ? sanitize_text_field( strtolower( $_POST['reorder'] ) ) : 'asc';
						}

						if ( isset( $data['id'] ) ) {
							$delete_meta = array( 'discount_type', 'discount', 'purchase', 'purchased_items', 'purchased_message', 'repeat' );
						}
					} elseif ( 'purchase_x_receive_y' === $_POST['mode'] || 'purchase_x_receive_y_same' === $_POST['mode'] || 'bogo' === $_POST['mode'] ) {
						$meta_data['purchase'] = ! empty( $_POST['purchase'] ) ? map_deep( $_POST['purchase'], 'sanitize_text_field' ) : (object) array( 'purchase' => '', 'receive' => '', 'discount_type' => 'percentage_discount', 'discount' => '' );
						$meta_data['repeat'] = ! empty( $_POST['repeat'] ) ? sanitize_text_field( $_POST['repeat'] ) : 'false';

						if ( 'bogo' !== $_POST['mode'] ) {
							$meta_data['purchased_items'] = ! empty( $_POST['purchased_items'] ) ? map_deep( $_POST['purchased_items'], 'sanitize_text_field' ) : array();
							$meta_data['purchased_message'] = ! empty( $_POST['purchased_message'] ) ? wp_kses_post( $_POST['purchased_message'] ) : '';
							$meta_data['quantity_based_on'] = ! empty( $_POST['quantity_based_on'] ) ? sanitize_text_field( $_POST['quantity_based_on'] ) : 'all_products';
						}

						if ( isset( $data['id'] ) ) {
							$delete_meta = array( 'discount_type', 'discount', 'quantities' );
						}
					} elseif ( 'products_group' === $_POST['mode'] ) {
						$meta_data['discount_type'] = ! empty( $_POST['discount_type'] ) ? sanitize_text_field( $_POST['discount_type'] ) : 'percentage_discount';
						$meta_data['discount'] = ! empty( $_POST['discount'] ) ? (float) $_POST['discount'] : 0;
						$meta_data['repeat'] = ! empty( $_POST['repeat'] ) ? sanitize_text_field( $_POST['repeat'] ) : 'false';

						if ( isset( $data['id'] ) ) {
							$delete_meta = array( 'quantity_based_on', 'quantities', 'purchase', 'purchased_items', 'purchased_message' );
						}
					} elseif ( 'simple' === $_POST['mode'] ) {
						$meta_data['discount_type'] = ! empty( $_POST['discount_type'] ) ? sanitize_text_field( $_POST['discount_type'] ) : 'percentage_discount';
						$meta_data['discount'] = ! empty( $_POST['discount'] ) ? (float) $_POST['discount'] : 0;

						if ( isset( $data['id'] ) ) {
							$delete_meta = array( 'quantity_based_on', 'quantities', 'purchase', 'purchased_items', 'purchased_message', 'repeat' );
						}
					} elseif ( 'exclude' === $_POST['mode'] ) {
						if ( isset( $data['id'] ) ) {
							$delete_meta = array( 'discount', 'discount_type', 'quantity_based_on', 'quantities', 'purchase', 'purchased_items', 'purchased_message', 'repeat' );
						}
					}
				}
			}

			$meta_data = apply_filters( 'wccs_condition_metadata', $meta_data, $condition_id, $type );
			if ( ! empty( $meta_data ) ) {
				foreach ( $meta_data as $meta => $meta_value ) {
					$condition_meta->update_meta( $condition_id, $meta, $meta_value );
				}
			}

			if ( ! empty( $delete_meta ) ) {
				foreach ( $delete_meta as $meta ) {
					$condition_meta->delete_meta( $condition_id, $meta );
				}
			}

			$condition = $conditions_db->get_condition( $condition_id );

			do_action( 'wccs_condition_added', $condition );

			die(
				json_encode(
					array(
						'success' => 1,
						'condition' => $condition,
						'message' => sprintf( __( 'Condition %s successfully.', 'easy-woocommerce-discounts' ), ( isset( $data['id'] ) ? 'updated' : 'saved' ) ),
					)
				)
			);
		}

		die(
			json_encode(
				array(
					'success' => 0,
					'message' => __( 'Errors occurred in saving condition.', 'easy-woocommerce-discounts' ),
				)
			)
		);
	}

	/**
	 * Deleting a condition.
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	public function delete_condition() {
		check_ajax_referer( 'wccs_conditions_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$errors = array();

		$id = isset( $_GET['id'] ) ? intval( $_GET['id'] ) : 0;
		if ( $id <= 0 ) {
			$errors[] = __( 'Condition id required to deleting it.', 'easy-woocommerce-discounts' );
		}

		if ( ! empty( $errors ) ) {
			die(
				json_encode(
					array(
						'success' => 0,
						'message' => __( 'Some errors occurred in deleting condition.', 'easy-woocommerce-discounts' ),
						'errors' => $errors,
					)
				)
			);
		}

		$condition = WCCS()->conditions->get_condition( $id );

		if ( $condition ) {
			if ( ! empty( $condition->type ) && 'cart-discount' === $condition->type && ! empty( $condition->name ) ) {
				wp_cache_delete( 'asnp_ewd_coupon_id_from_code_' . $condition->name, 'asnp_ewd_discounts' );
			}

			$delete = WCCS()->conditions->delete( $id );

			if ( $delete ) {
				do_action( 'wccs_condition_deleted', $condition );
				die(
					json_encode(
						array(
							'success' => 1,
							'message' => __( 'Condition deleted successfully.', 'easy-woocommerce-discounts' ),
						)
					)
				);
			}
		}

		die(
			json_encode(
				array(
					'success' => 0,
					'message' => __( 'Errors occurred in deleting condition.', 'easy-woocommerce-discounts' ),
				)
			)
		);
	}

	/**
	 * Updating condition.
	 *
	 * @since  1.1.0
	 *
	 * @return void
	 */
	public function update_condition() {
		check_ajax_referer( 'wccs_conditions_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$errors = array();

		if ( empty( $_POST['id'] ) ) {
			$errors[] = __( 'ID is required to updating condition.', 'easy-woocommerce-discounts' );
		}

		if ( empty( $_POST['type'] ) ) {
			$errors[] = __( 'Type is required to updating condition.', 'easy-woocommerce-discounts' );
		}

		if ( ! isset( $_POST['data'] ) ) {
			$errors[] = __( 'Data is required to updating condition.', 'easy-woocommerce-discounts' );
		}

		if ( ! empty( $errors ) ) {
			die(
				json_encode(
					array(
						'success' => 0,
						'message' => __( 'Some errors occurred in updating condition.', 'easy-woocommerce-discounts' ),
						'errors' => $errors,
					)
				)
			);
		}

		$wccs = WCCS();
		$conditions_db = $wccs->conditions;
		$condition_meta = $wccs->condition_meta;

		$update = false;
		$condition = $conditions_db->get_condition( intval( $_POST['id'] ) );
		if ( $condition ) {
			$data = array();
			if ( ! empty( $_POST['data']['name'] ) ) {
				$data['name'] = sanitize_text_field( $_POST['data']['name'] );
				$data['name'] = 'cart-discount' === $_POST['type'] ? wc_format_coupon_code( $data['name'] ) : $data['name'];
			}

			if ( isset( $_POST['data']['status'] ) ) {
				$data['status'] = intval( $_POST['data']['status'] );
			}

			if ( ! empty( $data ) ) {
				$update = $conditions_db->update( $condition->id, $data );
			}

			if ( ! empty( $data['name'] ) && 'cart-discount' === $_POST['type'] ) {
				wp_cache_delete( 'asnp_ewd_coupon_id_from_code_' . $data['name'], 'asnp_ewd_discounts' );
			}

			if ( in_array( $_POST['type'], array( 'cart-discount', 'pricing', 'checkout-fee', 'shipping' ), true ) ) {
				$meta_data = array();

				if ( ! empty( $_POST['data']['apply_mode'] ) ) {
					$meta_data['apply_mode'] = sanitize_text_field( $_POST['data']['apply_mode'] );
				}

				if ( ! empty( $meta_data ) ) {
					foreach ( $meta_data as $meta => $meta_value ) {
						$condition_meta->update_meta( $condition->id, $meta, $meta_value );
					}
					$update = true;
				}
			}

			if ( $update ) {
				$condition = $conditions_db->get_condition( $condition->id );
				do_action( 'wccs_condition_updated', $condition );
				die(
					json_encode(
						array(
							'success' => 1,
							'condition' => $condition,
							'message' => __( 'Condition updated successfully.', 'easy-woocommerce-discounts' ),
						)
					)
				);
			}
		}

		die(
			json_encode(
				array(
					'success' => 0,
					'message' => __( 'Errors occurred in updating condition.', 'easy-woocommerce-discounts' ),
				)
			)
		);
	}

	/**
	 * Updating conditions ordering.
	 *
	 * @since  1.0.0
	 *
	 * @return void
	 */
	public function update_conditions_ordering() {
		check_ajax_referer( 'wccs_conditions_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$errors = array();

		if ( empty( $_POST['conditions'] ) || ! is_array( $_POST['conditions'] ) ) {
			$errors[] = __( 'Conditions required for ordering', 'easy-woocommerce-discounts' );
		}

		if ( empty( $_POST['type'] ) ) {
			$errors[] = __( 'Type required for ordering.', 'easy-woocommerce-discounts' );
		}

		if ( ! empty( $errors ) ) {
			die(
				json_encode(
					array(
						'success' => 0,
						'message' => __( 'Some errors occurred in ordering conditions.', 'easy-woocommerce-discounts' ),
						'errors' => $errors,
					)
				)
			);
		}

		$conditions = WCCS()->conditions;
		$update = $conditions->update_conditions_ordering( map_deep( $_POST['conditions'], 'intval' ) );

		if ( $update ) {
			do_action( 'wccs_conditions_ordering_updated', sanitize_text_field( $_POST['type'] ) );
			die(
				json_encode(
					array(
						'success' => 1,
						'message' => __( 'Conditions ordered successfully.', 'easy-woocommerce-discounts' ),
						'conditions' => $conditions->get_conditions( array( 'type' => sanitize_text_field( $_POST['type'] ), 'number' => -1, 'orderby' => 'ordering', 'order' => 'ASC' ) ),
					)
				)
			);
		}

		die(
			json_encode(
				array(
					'success' => 0,
					'message' => __( 'Conditions did not ordered successfully.', 'easy-woocommerce-discounts' ),
				)
			)
		);
	}

	/**
	 * Duplicate a condition.
	 *
	 * @since  2.1.0
	 *
	 * @return void
	 */
	public function duplicate_condition() {
		check_ajax_referer( 'wccs_conditions_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$errors = array();

		$id = isset( $_POST['id'] ) ? intval( $_POST['id'] ) : 0;
		if ( $id <= 0 ) {
			$errors[] = __( 'Condition id required to duplicate it.', 'easy-woocommerce-discounts' );
		}

		if ( empty( $_POST['type'] ) ) {
			$errors[] = __( 'Type required for duplicating.', 'easy-woocommerce-discounts' );
		}

		if ( ! empty( $errors ) ) {
			die(
				json_encode(
					array(
						'success' => 0,
						'message' => __( 'Some errors occurred in duplicating condition.', 'easy-woocommerce-discounts' ),
						'errors' => $errors,
					)
				)
			);
		}

		$condition_id = WCCS()->conditions->duplicate( intval( $_POST['id'] ) );
		if ( ! $condition_id ) {
			die(
				json_encode(
					array(
						'success' => 0,
						'message' => __( 'Errors occurred in duplicating condition.', 'easy-woocommerce-discounts' ),
					)
				)
			);
		}

		do_action( 'wccs_condition_duplicated', $condition_id );

		die(
			json_encode(
				array(
					'success' => 1,
					'message' => __( 'Condition duplicated successfully.', 'easy-woocommerce-discounts' ),
					'conditions' => WCCS()->conditions->get_conditions( array( 'type' => sanitize_text_field( $_POST['type'] ), 'number' => -1, 'orderby' => 'ordering', 'order' => 'ASC' ) ),
				)
			)
		);
	}

	/**
	 * Get live price of a product.
	 *
	 * @since  2.2.0
	 *
	 * @return void
	 */
	public function live_price() {
		check_ajax_referer( 'wccs_single_product_nonce', 'nonce' );

		if ( empty( $_POST['data'] ) ) {
			die(
				json_encode(
					array(
						'success' => 0,
						'message' => __( 'Data is required to get live price.', 'easy-woocommerce-discounts' ),
					)
				)
			);
		}

		/**
		 * Using posted data instead of $_POST.
		 * It is necessary to make plugin compatible with other plugins.
		 * Compatibility with TM EPO: https://codecanyon.net/item/woocommerce-extra-product-options/7908619
		 */
		$post = $_POST;
		parse_str( wp_unslash( $_POST['data'] ), $_POST );
		$_REQUEST = $_POST;
		$live_price = new WCCS_Live_Price_Handler(
			$_POST,
			'cart_price' === WCCS()->settings->get_setting( 'live_pricing_calculation', 'cart_price' )
		);

		try {
			$data = $live_price->add_to_cart();
			// Reseting post.
			$_POST = $_REQUEST = $post;
		} catch (Exception $e) {
			die(
				json_encode(
					array(
						'success' => 0,
						'message' => $e->getMessage(),
					)
				)
			);
		}

		if ( ! empty( $data ) ) {
			die(
				json_encode(
					array(
						'success' => 1,
						'price' => $data['price'],
						'data' => $data,
					)
				)
			);
		}

		die(
			json_encode(
				array(
					'success' => 0,
					'message' => __( 'Error occurred in getting live price.', 'easy-woocommerce-discounts' ),
				)
			)
		);
	}

	/**
	 * Get list of options based on given term.
	 *
	 * @since  2.4.0
	 *
	 * @return void
	 */
	public function select_autocomplete() {
		check_ajax_referer( 'wccs_conditions_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$errors = array();

		if ( empty( $_GET['term'] ) ) {
			$errors[] = __( 'Search term is required to select data.', 'easy-woocommerce-discounts' );
		}

		if ( empty( $_GET['type'] ) ) {
			$errors[] = __( 'Type is required to select data.', 'easy-woocommerce-discounts' );
		}

		if ( ! empty( $errors ) ) {
			die(
				json_encode(
					array(
						'success' => 0,
						'message' => __( 'Some errors occurred in selecting data.', 'easy-woocommerce-discounts' ),
						'errors' => $errors,
					)
				)
			);
		}

		$term = wc_clean( wp_unslash( $_GET['term'] ) );
		if ( empty( $term ) ) {
			wp_die();
		}

		$limit = (int) WCCS()->settings->get_setting( 'search_items_limit', 20 );
		$items = array();

		if ( 'products' === $_GET['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::search_products( array( 'search' => $term, 'limit' => $limit ) );
		} elseif ( 'variations' === $_GET['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::search_variations( array( 'search' => $term, 'limit' => $limit ) );
		} elseif ( 'customers' === $_GET['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::get_customers( array( 'search' => '*' . $term . '*', 'number' => $limit ) );
		} elseif ( 'coupons' === $_GET['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::get_coupons( array( 's' => $term, 'posts_per_page' => $limit ) );
		} elseif ( 'url_coupons' === $_GET['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::get_url_coupons( array( 'name' => $term, 'number' => $limit ) );
		} elseif ( 'shipping_methods' === $_GET['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::get_shipping_methods_by_type( array( 'name' => $term ) );
		} elseif ( 'shipping_methods_by_title' === $_GET['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::get_shipping_methods_by_title( array( 'name' => $term ) );
		} elseif ( 'categories' === $_GET['type'] ) {
			$items = WCCS()->products->get_categories( array( 'name__like' => $term, 'number' => $limit ) );
		} elseif ( 'tags' === $_GET['type'] ) {
			$items = WCCS()->products->get_tags( array( 'name__like' => $term, 'number' => $limit ) );
		} elseif ( 'attributes' === $_GET['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::get_product_attributes( array( 'name__like' => $term, 'number' => $limit ) );
		} elseif ( false !== strpos( $_GET['type'], 'taxonomy_' ) ) {
			$taxonomy = str_replace( 'taxonomy_', '', $_GET['type'] );
			if ( false !== strpos( $taxonomy, '__' ) ) {
				$taxonomy = substr( $taxonomy, strpos( $taxonomy, '__' ) + 2 );
			}

			$items = WCCS()->products->get_product_taxonomies( array(
				'taxonomy' => sanitize_text_field( $taxonomy ),
				'name__like' => $term,
				'number' => $limit,
			) );
		} elseif ( 'rules' === $_GET['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::get_rules( [ 'name' => $term, 'number' => $limit ] );
		}

		die( json_encode( array( 'items' => $items ) ) );
	}

	/**
	 * Get list of options details based on given options.
	 *
	 * @since  2.4.0
	 *
	 * @return void
	 */
	public function select_options() {
		check_ajax_referer( 'wccs_conditions_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$errors = array();

		if ( empty( $_POST['options'] ) ) {
			$errors[] = __( 'Options are required to get select data.', 'easy-woocommerce-discounts' );
		}

		if ( empty( $_POST['type'] ) ) {
			$errors[] = __( 'Type is required to get select data.', 'easy-woocommerce-discounts' );
		}

		if ( ! empty( $errors ) ) {
			die(
				json_encode(
					array(
						'success' => 0,
						'message' => __( 'Some errors occurred in getting select data.', 'easy-woocommerce-discounts' ),
						'errors' => $errors,
					)
				)
			);
		}

		$items = array();

		if ( 'products' === $_POST['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::get_products( array( 'include' => is_array( $_POST['options'] ) ? array_map( 'WCCS_Helpers::maybe_get_exact_item_id', $_POST['options'] ) : array( WCCS_Helpers::maybe_get_exact_item_id( $_POST['options'] ) ) ) );
		} elseif ( 'variations' === $_POST['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::get_variations( array( 'include' => is_array( $_POST['options'] ) ? array_map( 'WCCS_Helpers::maybe_get_exact_item_id', $_POST['options'] ) : array( WCCS_Helpers::maybe_get_exact_item_id( $_POST['options'] ) ) ) );
		} elseif ( 'customers' === $_POST['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::get_customers( array( 'include' => is_array( $_POST['options'] ) ? array_map( 'absint', $_POST['options'] ) : array( absint( $_POST['options'] ) ) ) );
		} elseif ( 'coupons' === $_POST['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::get_coupons( array( 'include' => is_array( $_POST['options'] ) ? array_map( 'sanitize_text_field', $_POST['options'] ) : array( sanitize_text_field( $_POST['options'] ) ) ) );
		} elseif ( 'url_coupons' === $_POST['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::get_url_coupons( array( 'id' => is_array( $_POST['options'] ) ? array_map( 'absint', $_POST['options'] ) : array( absint( $_POST['options'] ) ) ) );
		} elseif ( 'shipping_methods' === $_POST['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::get_shipping_methods_by_type( array( 'id' => wc_clean( $_POST['options'] ) ) );
		} elseif ( 'shipping_methods_by_title' === $_POST['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::get_shipping_methods_by_title( array( 'id' => wc_clean( $_POST['options'] ) ) );
		} elseif ( 'categories' === $_POST['type'] ) {
			$items = WCCS()->products->get_categories( array( 'include' => array_map( 'WCCS_Helpers::maybe_get_exact_category_id', $_POST['options'] ) ) );
		} elseif ( 'tags' === $_POST['type'] ) {
			$items = WCCS()->products->get_tags( array( 'include' => array_map( 'WCCS_Helpers::maybe_get_exact_tag_id', $_POST['options'] ) ) );
		} elseif ( 'attributes' === $_POST['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::get_product_attributes( array( 'include' => array_map( 'sanitize_text_field', $_POST['options'] ) ) );
		} elseif ( false !== strpos( $_POST['type'], 'taxonomy_' ) ) {
			$taxonomy = str_replace( 'taxonomy_', '', sanitize_text_field( $_POST['type'] ) );
			if ( false !== strpos( $taxonomy, '__' ) ) {
				$taxonomy = substr( $taxonomy, strpos( $taxonomy, '__' ) + 2 );
			}

			$taxonomies = ! empty( $_POST['options'] ) ? array_map( 'absint', $_POST['options'] ) : array();
			if ( ! empty( $taxonomies ) ) {
				for ( $i = 0; $i < count( $taxonomies ); $i++ ) {
					$taxonomies[ $i ] = WCCS_Helpers::maybe_get_exact_item_id( $taxonomies[ $i ], $taxonomy );
				}
				$items = WCCS()->products->get_product_taxonomies( array(
					'taxonomy' => sanitize_text_field( $taxonomy ),
					'include' => $taxonomies
				) );
			}
		} elseif ( 'rules' === $_POST['type'] ) {
			$items = WCCS_Admin_Select_Data_Provider::get_rules( [ 'id' => is_array( $_POST['options'] ) ? array_map( 'absint', $_POST['options'] ) : [ absint( $_POST['options'] ) ] ] );
		}

		die( json_encode( array( 'items' => $items ) ) );
	}

	public function get_addons() {
		check_ajax_referer( 'wccs_conditions_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$items = array(
			'bundle' => array(
				'title' => __( 'Bundle', 'easy-woocommerce-discounts' ),
				'items' => array(
					array(
						'id' => 'advanced-discounts-woocommerce',
						'name' => 'Advanced Discounts for WooCommerce',
						'desc' => 'Advanced Discounts for WooCommerce is a discount bundle plugin that includes all of the discount and coupon plugins that you need for your WooCommerce store.',
						'url' => 'https://www.asanaplugins.com/advanced-discounts-woocommerce',
						'price' => '180',
						'sale_price' => '68',
					),
				),
			),
			'addons' => array(
				'title' => __( 'Addons', 'easy-woocommerce-discounts' ),
				'items' => array(
					array(
						'id' => 'sale-badges-woocommerce',
						'name' => 'Sale Badges and Product Labels',
						'desc' => 'The WooCommerce Sale Badges and Product Labels plugin enhances your store appeal by offering custom badges and countdown timers. Unleash the full potential of your WooCommerce store with attractive sale badges, product labels, and countdown timers. This all-in-one plugin empowers you to create eye-catching labels and scheduled badges for products, categories, and tags. These labels convey valuable information to your customers, such as discount values, product quantities, ratings, shipping classes, stock status, and sale status. Additionally, you can utilize countdown timers to instill urgency and boost conversions. With an intuitive interface, effortlessly customize labels and replace WooCommerce default on-sale badges. Elevate your WooCommerce store with visually appealing badges, labels, and countdown timers tailored to your unique offerings.',
						'url' => 'https://www.asanaplugins.com/product/woocommerce-sale-badges-and-product-labels/',
						'price' => '60',
						'sale_price' => '30',
					),
					array(
						'id' => 'auto-add-products-to-cart-woocommerce',
						'name' => 'Auto Add Products to Cart',
						'desc' => 'Automatically Add Products to Cart is a discount plugin that can add products to the cart automatically based on conditions with a discount or without. You can use it to automatically add gift products to the cart in WooCommerce.',
						'url' => 'https://www.asanaplugins.com/product/auto-add-products-to-cart-woocommerce',
						'price' => '40',
						'sale_price' => '20',
					),
					array(
						'id' => 'shipping-discount-woocommerce',
						'name' => 'Shipping Discount',
						'desc' => 'Shipping Discount is a discount plugin that applies the discount on specific shipping methods based on conditions. You can set price or percentage discounts on shipping methods.',
						'url' => 'https://www.asanaplugins.com/product/shipping-discount-woocommerce',
						'price' => '40',
						'sale_price' => '20',
					),
					array(
						'id' => 'url-coupons-for-woocommerce',
						'name' => 'URL Coupons',
						'desc' => 'URL Coupons for WooCommerce adds unique URLs for coupons and when a customer visits that unique URL it will apply the discount to the cart. You can use the WooCommerce URL Coupons plugin to automate coupons on your site.',
						'url' => 'https://www.asanaplugins.com/product/url-coupons-for-woocommerce',
						'price' => '40',
						'sale_price' => '20',
					),
				),
			),
		);

		die( json_encode( array( 'items' => $items ) ) );
	}

	public function get_coupon_code() {
		check_ajax_referer( 'wccs_conditions_nonce', 'nonce' );

		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			wp_die( -1 );
		}

		$errors = array();

		if ( empty( $_GET['name'] ) ) {
			$errors[] = __( 'Coupon name is required.', 'easy-woocommerce-discounts' );
		}

		if ( ! empty( $errors ) ) {
			die(
				json_encode(
					array(
						'success' => 0,
						'message' => __( 'Some errors occurred in getting coupon code.', 'easy-woocommerce-discounts' ),
						'errors' => $errors,
					)
				)
			);
		}

		die( json_encode( array( 'coupon_code' => sanitize_title( $_GET['name'] ) ) ) );
	}

}
