<?php

defined( 'ABSPATH' ) || exit;

class WCCS_Usage_Validator {

	public static function check_rule_usage_limit( $rule ) {
		if ( ! $rule || ! $rule->id ) {
			return false;
		}

		if ( empty( $rule->usage_limit ) || 0 >= (int) $rule->usage_limit ) {
			return true;
		}

		$model = WCCS()->container()->get( WCCS_DB_Rule_Usage_Logs::class );
		$usage_count = $model->get_unique_order_count_by_rule( $rule->id );

		if ( empty( $usage_count ) ) {
			return true;
		}

		return (int) $usage_count < (int) $rule->usage_limit;
	}

}
