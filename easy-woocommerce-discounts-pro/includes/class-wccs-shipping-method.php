<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Shipping_Method extends WC_Shipping_Method {

    protected $valid_rules;

    protected $rules_filter;

    protected $date_time_validator;

    protected $condition_validator;

    /**
     * Constructor.
     */
    public function __construct( $instance_id = 0 ) {
        $this->id                 = 'dynamic_shipping';
        $this->instance_id        = absint( $instance_id );
        $this->method_title       = __( 'Dynamic Shipping', 'easy-woocommerce-discounts' );
        $this->method_description = __( 'Dynamic Shipping', 'easy-woocommerce-discounts' );
        $this->init();
    }

    /**
	 * Initilize properties.
	 */
    public function init() {
        $this->rules_filter        = new WCCS_Rules_Filter();
        $this->date_time_validator = WCCS()->WCCS_Date_Time_Validator;
        $this->condition_validator = WCCS()->WCCS_Shipping_Condition_Validator;
        $this->enabled             = $this->get_option( 'enabled', 'yes' );
    }

    /**
	 * Get_option function.
	 *
	 * Gets and option from the settings API, using defaults if necessary to prevent undefined notices.
	 *
	 * @param  string $key Key.
	 * @param  mixed  $empty_value Empty value.
     *
	 * @return mixed  The value specified for the option or a default value for the option.
	 */
    public function get_option( $key, $empty_value = null ) {
        return apply_filters(
            'woocommerce_shipping_' . $this->id . '_option',
            WCCS()->settings->get_setting( 'shipping_' . $key, $empty_value ),
            $key,
            $this
        );
    }

    /**
	 * Called to calculate shipping rates for this method. Rates can be added using the add_rate() method.
	 *
	 * @param array $package Package array.
	 */
	public function calculate_shipping( $package = array() ) {
        if ( ! isset( $this->valid_rules ) ) {
            $this->valid_rules = $this->get_valid_rules( $package );
        }

        if ( empty( $this->valid_rules ) ) {
            return;
        }

        foreach ( $this->valid_rules as $rule ) {
            $this->instance_id = $rule->id;
            $this->tax_status  = $rule->tax_status;
            $this->add_rate( apply_filters(
                'wccs_shipping_method_rate',
                array(
                    'id'      => $this->get_rate_id(),
                    'label'   => ! empty( $rule->name ) ? sanitize_text_field( $rule->name ) : __( 'Shipping', 'easy-woocommerce-discounts' ),
                    'cost'    => $this->get_rule_cost( $rule, $package ),
                    'package' => $package,
                ),
                $package,
                $rule
            ) );
        }
    }

    /**
     * Get a rule cost.
     *
     * @param  Object $rule
     * @param  array $package
     *
     * @return float
     */
    public function get_rule_cost( $rule, $package = array() ) {
        $cost = ! empty( $rule->cost ) ? (float) $rule->cost : 0;
        $cost += $this->get_total_quantities_cost( $package, $rule );
        $cost += $this->get_total_weights_cost( $package, $rule );
        $cost += $this->get_fee( $package, $rule );
        return apply_filters( 'wccs_shipping_method_get_rule_cost', $cost, $rule, $package );
    }

    /**
	 * Get fee to add to shipping cost.
	 *
	 * @param  array  $package Package array.
     * @param  Object $rule
     *
	 * @return float
	 */
	public function get_fee( $package, $rule ) {
        if ( ! isset( $rule->fee ) || empty( $package ) ) {
            return apply_filters( 'wccs_shipping_method_get_fee', 0, $package, $rule, $this );
        }

        $this->fee = $rule->fee;

        if ( false !== strpos( $rule->fee, '%' ) ) {
            $fee = ( $package['contents_cost'] / 100 ) * str_replace( '%', '', $rule->fee );
        } else {
            $fee = (float) $rule->fee;
        }

        if ( ! empty( $rule->min_fee ) && (float) $rule->min_fee > $fee ) {
            $fee = (float) $rule->min_fee;
        }

        if ( ! empty( $rule->max_fee ) && (float) $rule->max_fee < $fee ) {
            $fee = (float) $rule->max_fee;
        }

        return apply_filters( 'wccs_shipping_method_get_fee', (float) $fee, $package, $rule, $this );
    }

    /**
     * Get total quantities cost for given package and rule.
     *
     * @param  array  $package Package array.
     * @param  Object $rule
     *
     * @return float
     */
    public function get_total_quantities_cost( $package, $rule ) {
        if ( ! isset( $rule->cost_per_quantity ) || empty( $package ) ) {
            return apply_filters( 'wccs_shipping_method_get_total_quantities_cost', 0, $package, $rule, $this );
        }

        $quantity = WCCS()->WCCS_Shipping_Helpers->get_shipping_package_contents_count( $package );
        if ( empty( $quantity ) ) {
            return apply_filters( 'wccs_shipping_method_get_total_quantities_cost', 0, $package, $rule, $this );
        }

        if ( false !== strpos( $rule->cost_per_quantity, '%' ) ) {
            $total_cost = ( $quantity / 100 ) * str_replace( '%', '', $rule->cost_per_quantity );
        } else {
            $total_cost = $quantity * (float) $rule->cost_per_quantity;
        }

        return apply_filters( 'wccs_shipping_method_get_total_quantities_cost', (float) $total_cost, $package, $rule, $this );
    }

    /**
     * Get total weights cost for given package and rule.
     *
     * @param  array  $package Package array.
     * @param  Object $rule
     *
     * @return float
     */
    public function get_total_weights_cost( $package, $rule ) {
        if ( ! isset( $rule->cost_per_weight ) || empty( $package ) ) {
            return apply_filters( 'wccs_shipping_method_get_total_weights_cost', 0, $package, $rule, $this );
        }

        $weight = WCCS()->WCCS_Shipping_Helpers->get_shipping_package_weight( $package );
        if ( empty( $weight ) ) {
            return apply_filters( 'wccs_shipping_method_get_total_weights_cost', 0, $package, $rule, $this );
        }

        if ( false !== strpos( $rule->cost_per_weight, '%' ) ) {
            $total_cost = ( $weight / 100 ) * str_replace( '%', '', $rule->cost_per_weight );
        } else {
            $total_cost = $weight * (float) $rule->cost_per_weight;
        }

        return apply_filters( 'wccs_shipping_method_get_total_weights_cost', (float) $total_cost, $package, $rule, $this );
    }

    /**
     * Get valid rules.
     *
     * @param  array $package
     *
     * @return array
     */
    public function get_valid_rules( $package = array() ) {
        $rules = WCCS_Conditions_Provider::get_shippings( array( 'status' => 1 ) );
        if ( empty( $rules ) ) {
            return apply_filters( 'wccs_shipping_method_get_valid_rules', array(), $package, $this );
        }

        $valid_rules = array();
        foreach ( $rules as $rule ) {
            // Validating usage limit.
            if ( ! empty( $rule->usage_limit ) && ! WCCS_Usage_Validator::check_rule_usage_limit( $rule ) ) {
				continue;
            }

            // Validating date time.
            if ( ! $this->date_time_validator->is_valid_date_times( $rule->date_time, ( ! empty( $rule->date_times_match_mode ) ? $rule->date_times_match_mode : 'one' ) ) ) {
				continue;
            }
            
            // Validating conditions.
            if ( ! $this->condition_validator->is_valid_conditions( $rule, ( ! empty( $rule->conditions_match_mode ) ? $rule->conditions_match_mode : 'all' ), $package ) ) {
				continue;
            }

			$valid_rules[] = $rule;
        }

        if ( ! empty( $valid_rules ) ) {
            usort( $valid_rules, array( WCCS()->WCCS_Sorting, 'sort_by_ordering_asc' ) );
			$valid_rules = $this->rules_filter->by_apply_mode( $valid_rules );
        }

        return apply_filters( 'wccs_shipping_method_get_valid_rules', $valid_rules, $package, $this );
    }

}
