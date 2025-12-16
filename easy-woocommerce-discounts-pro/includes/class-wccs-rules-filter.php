<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Rules_Filter {

	/**
	 * Filtering rules by apply mode.
	 *
	 * @since  1.1.0
	 *
	 * @param  array $rules
	 *
	 * @return array
	 */
	public function by_apply_mode( array $rules ) {
		if ( 1 >= count( $rules ) ) {
			return $rules;
		}

		$applicable_not_exists = array();
		$all = array();

		foreach ( $rules as $key => $rule ) {
			if ( empty( $rule ) || ( ! is_array( $rule ) && ! is_object( $rule ) ) ) {
				continue;
			} elseif ( is_array( $rule ) ) {
				if ( empty( $rule['apply_mode'] ) ) {
					continue;
				}
			} elseif ( is_object( $rule ) ) {
				if ( empty( $rule->apply_mode ) ) {
					continue;
				}
			}

			$apply_mode = is_array( $rule ) ? $rule['apply_mode'] : $rule->apply_mode;

			if ( 'individually' === $apply_mode ) {
				return array( $key => $rule );
			} elseif ( 'applicable_not_exists' === $apply_mode ) {
				if ( empty( $applicable_not_exists ) ) {
					$applicable_not_exists[ $key ] = $rule;
				}
			} elseif ( 'all' === $apply_mode ) {
				$all[ $key ] = $rule;
			}
		}

		return ! empty( $all ) ? $all : $applicable_not_exists;
	}

}
