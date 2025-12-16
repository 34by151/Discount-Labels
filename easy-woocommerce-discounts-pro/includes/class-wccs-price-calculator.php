<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Price_Calculator {

	public static function calculate( $price, $discount_type, $discount ) {
		switch ( $discount_type ) {
			case 'percentage_discount':
				if ( '' !== $discount &&
					0 < (float) $discount &&
					0 <= (float) $price - (float) $discount / 100 * $price
				) {
					return (float) $price - (float) $discount / 100 * $price;
				}
				break;

			case 'price_discount':
				if ( '' !== $discount &&
					0 <= (float) $discount &&
					0 <= (float) $price - (float) $discount
				) {
					return (float) $price - (float) $discount;
				}
				break;

			case 'fixed_price':
				if ( '' !== $discount && 0 <= (float) $discount ) {
					return (float) $discount;
				}
				break;
		}

		return $price;
	}

}
