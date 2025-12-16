<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Sorting {

	public function sort_by_order_asc( $a, $b ) {
		$first  = isset( $a['order'] ) ? (int) $a['order'] : 0;
		$second = isset( $b['order'] ) ? (int) $b['order'] : 0;
		return $this->sort_asc( $first, $second );
	}

	public function sort_by_ordering_asc( $a, $b ) {
		if ( is_array( $a ) ) {
			$first = isset( $a['ordering'] ) ? (int) $a['ordering'] : 0;
		} else {
			$first = isset( $a->ordering ) ? (int) $a->ordering : 0;
		}

		if ( is_array( $b ) ) {
			$second = isset( $b['ordering'] ) ? (int) $b['ordering'] : 0;
		} else {
			$second = isset( $b->ordering ) ? (int) $b->ordering : 0;
		}

		return $this->sort_asc( $first, $second );
	}

	public function sort_by_item_price_asc( $a, $b ) {
		$first  = isset( $a['item_price'] ) ? (float) $a['item_price'] : 0;
		$second = isset( $b['item_price'] ) ? (float) $b['item_price'] : 0;
		return $this->sort_asc( $first, $second );
	}

	public function sort_by_item_price_desc( $a, $b ) {
		$first  = isset( $a['item_price'] ) ? (float) $a['item_price'] : 0;
		$second = isset( $b['item_price'] ) ? (float) $b['item_price'] : 0;
		return $this->sort_desc( $first, $second );
	}

	public function sort_by_time_asc( $a, $b ) {
		$first  = ! empty( $a ) ? strtotime( $a ) : 0;
		$second = ! empty( $b ) ? strtotime( $b ) : 0;
		return $this->sort_asc( $first, $second );
	}

	public function sort_asc( $a, $b ) {
		if ( $a === $b ) {
			return 0;
		}
		return ( $a > $b ) ? 1 : -1;
	}

	public function sort_desc( $a, $b ) {
		if ( $a === $b ) {
			return 0;
		}
		return ( $a < $b ) ? 1 : -1;
	}

}
