<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Comparison {

	/**
	 * Compares two arrays based on given type.
	 *
	 * @since  2.0.0
	 *
	 * @param  array   $cmp
	 * @param  array   $cmp_to
	 * @param  string  $type
	 * @param  integer $number
	 *
	 * @return boolean
	 */
	public function union_compare( array $cmp, array $cmp_to, $type, $number = 2 ) {
		switch ( $type ) {
			case 'none_of':
				return ! count( array_intersect( $cmp, $cmp_to ) );

			case 'at_least_one_of':
				return count( array_intersect( $cmp, $cmp_to ) );

			case 'at_least_number_of':
				return count( array_intersect( $cmp, $cmp_to ) ) >= (int) $number;

			case 'all_of':
				return ! count( array_diff( $cmp, $cmp_to ) );

			case 'only':
				return ! count( array_diff( $cmp, $cmp_to ) ) && ! count( array_diff( $cmp_to, $cmp ) );
		}

		return false;
	}

	/**
	 * Compare two values by given math operations.
	 *
	 * @since  2.0.0
	 *
	 * @param  mixed  $value
	 * @param  mixed  $against_value
	 * @param  string $operation
	 *
	 * @return boolean
	 */
	public function math_compare( $value, $against_value, $operation ) {
		if ( 'less_than' === $operation ) {
			return $value < $against_value;
		} elseif ( 'less_equal_to' === $operation ) {
			return $value <= $against_value;
		} elseif ( 'greater_than' === $operation ) {
			return $value > $against_value;
		} elseif ( 'greater_equal_to' === $operation ) {
			return $value >= $against_value;
		} elseif ( 'equal_to' === $operation ) {
			return $value == $against_value;
		} elseif ( 'not_equal_to' === $operation) {
			return $value != $against_value;
		}

		return false;
	}

	/**
	 * Compare two string values by given condition.
	 *
	 * @since  4.8.0
	 *
	 * @param  string  $value
	 * @param  string  $against_value
	 * @param  string  $condition
	 * @param  boolean $case_sensitive
	 * @param  boolean $trim
	 *
	 * @return boolean
	 */
	public function string_compare( $value, $against_value, $condition, $case_sensitive = false, $trim = true ) {
		$value         = ! $case_sensitive ? strtolower( (string) $value ) : (string) $value;
		$against_value = ! $case_sensitive ? strtolower( (string) $against_value ) : (string) $against_value;
		if ( $trim ) {
			$value         = trim( $value );
			$against_value = trim( $against_value );
		}

		switch ( $condition ) {
			case 'empty':
				return empty( $value );

			case 'is_not_empty':
				return ! empty( $value );

			case 'equal_to':
				return $value === $against_value;

			case 'not_equal_to':
				return $value !== $against_value;

			case 'equal_to_list':
				$against_value = array_map( 'trim', explode( ',', trim( $against_value ) ) );
				return in_array( $value, $against_value, true );

			case 'not_equal_to_list':
				$against_value = array_map( 'trim', explode( ',', trim( $against_value ) ) );
				return ! in_array( $value, $against_value, true );

			case 'contains':
				if ( empty( $against_value ) || empty( $value ) ) {
					return false;
				}
				return false !== strpos( $value, $against_value );

			case 'does_not_contain':
				if ( empty( $against_value ) ) {
					return false;
				}
				return false === strpos( $value, $against_value );

			case 'begins_with':
				if ( empty( $against_value ) || empty( $value ) ) {
					return false;
				}
				return 0 === strpos( $value, $against_value );

			case 'ends_with':
				if ( empty( $against_value ) || empty( $value ) ) {
					return false;
				}
				return strlen( $value ) >= strlen( $against_value ) && $against_value === substr( $value, -1 * strlen( $against_value ) );
		}

		return false;
	}

	/**
	 * Compare a meta data.
	 *
	 * @since  2.0.0
	 *
	 * @param  mixed  $meta_data
	 * @param  string $condition
	 * @param  string $compare_to
	 *
	 * @return boolean
	 */
	public function meta_compare( $meta_data, $condition, $compare_to = '' ) {
		switch ( $condition ) {
			case 'empty':
				if ( is_array( $meta_data ) ) {
					foreach ( $meta_data as $meta ) {
						if ( ! empty( $meta ) ) {
							return false;
						}
					}
					return true;
				}
				return empty( $meta_data );

			case 'is_not_empty':
				if ( is_array( $meta_data ) ) {
					foreach ( $meta_data as $meta ) {
						if ( empty( $meta ) ) {
							return false;
						}
					}
					return empty( $meta_data ) ? false : true;
				}
				return ! empty( $meta_data );

			case 'contains':
				if ( empty( $meta_data ) || empty( $compare_to ) ) {
					return false;
				}
				if ( is_array( $meta_data ) ) {
					foreach ( $meta_data as $meta ) {
						if ( is_array( $meta ) ) {
							if ( in_array( $compare_to, $meta ) ) {
								return true;
							}
						} elseif ( false !== strpos( (string) $meta, $compare_to ) ) {
							return true;
						}
					}
					return false;
				}
				return false !== strpos( (string) $meta_data, $compare_to );

			case 'does_not_contain':
				if ( empty( $compare_to ) ) {
					return false;
				}
				if ( is_array( $meta_data ) ) {
					foreach ( $meta_data as $meta ) {
						if ( is_array( $meta ) ) {
							if ( in_array( $compare_to, $meta ) ) {
								return false;
							}
						} elseif ( false !== strpos( (string) $meta, $compare_to ) ) {
							return false;
						}
					}
					return true;
				}
				return false === strpos( (string) $meta_data, $compare_to );

			case 'begins_with':
				if ( empty( $meta_data ) || empty( $compare_to ) ) {
					return false;
				}
				if ( is_array( $meta_data ) ) {
					if ( 1 === count( $meta_data ) ) {
						return $compare_to === array_shift( $meta_data );
					} else {
						foreach ( $meta_data as $meta ) {
							if ( is_array( $meta ) ) {
								if ( $compare_to !== array_shift( $meta ) ) {
									return false;
								}
							} elseif ( 0 !== strpos( (string) $meta, $compare_to ) ) {
								return false;
							}
						}
					}
					return true;
				}
				return 0 === strpos( (string) $meta_data, $compare_to );

			case 'ends_with':
				if ( empty( $meta_data ) || empty( $compare_to ) ) {
					return false;
				}
				if ( is_array( $meta_data ) ) {
					if ( 1 === count( $meta_data ) ) {
						return $compare_to === array_pop( $meta_data );
					} else {
						foreach ( $meta_data as $meta ) {
							if ( is_array( $meta ) ) {
								if ( $compare_to !== array_pop( $meta ) ) {
									return false;
								}
							} else {
								if ( strlen( strval( $meta ) ) < strlen( $compare_to ) ) {
									return false;
								}

								if ( $compare_to !== strtolower( substr( (string) $meta, -1 * strlen( $compare_to ) ) ) ) {
									return false;
								}
							}
						}
					}
					return true;
				}
				return strlen( strval( $meta_data ) ) >= strlen( $compare_to ) && $compare_to === substr( (string) $meta_data, -1 * strlen( $compare_to ) );

			case 'equal_to':
				return $this->equal_to( $meta_data, $compare_to );

			case 'equal_to_list':
				$compare_to = array_map( 'trim', explode( ',', trim( $compare_to ) ) );
				if ( ! empty( $compare_to ) ) {
					foreach ( $compare_to as $cmp_to ) {
						if ( $this->equal_to( $meta_data, $cmp_to ) ) {
							return true;
						}
					}
				}
				return false;

			case 'not_equal_to':
				return ! $this->equal_to( $meta_data, $compare_to );

			case 'not_equal_to_list':
				$compare_to = array_map( 'trim', explode( ',', trim( $compare_to ) ) );
				if ( ! empty( $compare_to ) ) {
					foreach ( $compare_to as $cmp_to ) {
						if ( $this->equal_to( $meta_data, $cmp_to ) ) {
							return false;
						}
					}
				}
				return true;

			case 'less_than':
				return $this->less_than( $meta_data, $compare_to );

			case 'less_equal_to':
				return $this->less_equal_to( $meta_data, $compare_to );

			case 'greater_than' :
				return $this->greater_than( $meta_data, $compare_to );

			case 'greater_equal_to':
				return $this->greater_equal_to( $meta_data, $compare_to );

			case 'is_checked':
				if ( is_array( $meta_data ) ) {
					foreach ( $meta_data as $meta ) {
						if ( ! $meta ) {
							return false;
						}
					}
					return ! empty( $meta_data );
				}
				return $meta_data;

			case 'is_not_checked':
				if ( empty( $meta_data ) ) {
					return true;
				}

				if ( is_array( $meta_data ) ) {
					foreach ( $meta_data as $meta ) {
						if ( $meta ) {
							return false;
						}
					}
					return true;
				}
				return ! $meta_data;

			default:
				break;
		}

		return false;
	}

	/**
	 * Is given values equals.
	 *
	 * @since  2.0.0
	 *
	 * @param  mixed $value
	 * @param  mixed $cmp_value
	 *
	 * @return boolean
	 */
	public function equal_to( $value, $cmp_value ) {
		if ( is_array( $value ) ) {
			if ( empty( $value ) ) {
				return false;
			}

			foreach ( $value as $v ) {
				if ( ! $this->equal_to( $v, $cmp_value ) ) {
					return false;
				}
			}

			return true;
		}

		return $value == $cmp_value;
	}

	/**
	 * Is given value less than compare value.
	 *
	 * @since  2.0.0
	 *
	 * @param  mixed $value
	 * @param  mixed $cmp_value
	 *
	 * @return boolean
	 */
	public function less_than( $value, $cmp_value ) {
		if ( is_array( $value ) ) {
			if ( empty( $value ) ) {
				return false;
			}

			foreach ( $value as $v ) {
				if ( ! $this->less_than( $v, $cmp_value ) ) {
					return false;
				}
			}

			return true;
		}

		return $value < $cmp_value;
	}

	/**
	 * Is given values less or equal to compare value.
	 *
	 * @since  2.0.0
	 *
	 * @param  mixed $value
	 * @param  mixed $cmp_value
	 *
	 * @return boolean
	 */
	public function less_equal_to( $value, $cmp_value ) {
		if ( is_array( $value ) ) {
			if ( empty( $value ) ) {
				return false;
			}

			foreach ( $value as $v ) {
				if ( ! $this->less_equal_to( $v, $cmp_value ) ) {
					return false;
				}
			}

			return true;
		}

		return $value <= $cmp_value;
	}

	/**
	 * Is given values greater than compare value.
	 *
	 * @since  2.0.0
	 *
	 * @param  mixed $value
	 * @param  mixed $cmp_value
	 *
	 * @return boolean
	 */
	public function greater_than( $value, $cmp_value ) {
		if ( is_array( $value ) ) {
			if ( empty( $value ) ) {
				return false;
			}

			foreach ( $value as $v ) {
				if ( ! $this->greater_than( $v, $cmp_value ) ) {
					return false;
				}
			}

			return true;
		}

		return $value > $cmp_value;
	}

	/**
	 * Is given values greater or equal to compare value.
	 *
	 * @since  2.0.0
	 *
	 * @param  mixed $value
	 * @param  mixed $cmp_value
	 *
	 * @return boolean
	 */
	public function greater_equal_to( $value, $cmp_value ) {
		if ( is_array( $value ) ) {
			if ( empty( $value ) ) {
				return false;
			}

			foreach ( $value as $v ) {
				if ( ! $this->greater_equal_to( $v, $cmp_value ) ) {
					return false;
				}
			}

			return true;
		}

		return $value >= $cmp_value;
	}

	/**
	 * Compare two postcodes.
	 *
	 * @since  2.3.0
	 *
	 * @param  mixed $value
	 * @param  mixed $cmp_value
	 *
	 * @return boolean
	 */
	public function postcode_compare( $value, $cmp_value ) {
		$cmp_value = trim( $cmp_value );

		// Range comparison - String start with [ and end with ]
		if ( 0 === strpos( $cmp_value, '[' ) && strlen( $cmp_value ) - 1 === strpos( $cmp_value, ']' ) ) {
			$range = array_map( 'trim', explode( '-', trim( $cmp_value, '[]' ) ) );
			if ( 2 !== count( $range ) || '' === $range[0] || '' === $range[1] ) {
				return false;
			}

			if ( is_numeric( $range[0] ) && is_numeric( $range[1] ) ) {
				if ( ! is_numeric( $value ) || $range[0] > $range[1] ) {
					return false;
				}

				return $value >= $range[0] && $value <= $range[1];
			}

			// All of values should have non numeric values.
			if ( is_numeric( $range[0] ) || is_numeric( $range[1] ) || is_numeric( $value ) ) {
				return false;
			}

			$range = array_map( 'strrev', $range );
			$value = strrev( $value );

			// Find position of first occurrance of non digit.
			$match_list = array();
			preg_match( '/\D/is', $range[0], $match_list, PREG_OFFSET_CAPTURE );
			$pos1 = ! empty( $match_list ) ? $match_list[0][1] : 0;
			preg_match( '/\D/is', $range[1], $match_list, PREG_OFFSET_CAPTURE );
			$pos2 = ! empty( $match_list ) ? $match_list[0][1] : 0;
			preg_match( '/\D/is', $value, $match_list, PREG_OFFSET_CAPTURE );
			$pos3 = ! empty( $match_list ) ?  $match_list[0][1] : 0;

			// There is not any digit value on last of range values.
			if ( 0 === $pos1 || 0 === $pos2 || 0 === $pos3 ) {
				return false;
			}

			$num1      = strrev( substr( $range[0], 0, $pos1 ) );
			$num2      = strrev( substr( $range[1], 0, $pos2 ) );
			$value_num = strrev( substr( $value, 0, $pos3 ) );
			$str1      = trim( substr( $range[0], $pos1, strlen( $range[0] ) ) );
			$str2      = trim( substr( $range[1], $pos2, strlen( $range[1] ) ) );
			$value_str = trim( substr( $value, $pos3, strlen( $value ) ) );

			// String parts are not same.
			if ( 0 != strcasecmp( $str1, $str2 ) || 0 != strcasecmp( $str1, $value_str ) || 0 != strcasecmp( $value_str, $str2 ) ) {
				return false;
			}

			if ( $num1 > $num2 ) {
				return false;
			}

			return $value_num >= $num1 && $value_num <= $num2;
		} // * comparison.
		elseif ( false !== strpos( $cmp_value, '*' ) ) {
			$cmp_value = str_replace( '*', '.', $cmp_value );
			return preg_match( "/^{$cmp_value}$/i", $value );
		}

		return 0 == strcasecmp( (string) $value, (string) $cmp_value );
	}

	public function quantities_compare( $quantities, $value, $type ) {
		foreach ( $quantities as $item_id => $quantity ) {
			if ( 'less_than' === $type ) {
				if ( $quantity >= $value ) {
					return false;
				}
			} elseif ( 'less_equal_to' === $type ) {
				if ( $quantity > $value ) {
					return false;
				}
			} elseif ( 'greater_than' === $type ) {
				if ( $quantity <= $value ) {
					return false;
				}
			} elseif ( 'greater_equal_to' === $type ) {
				if ( $quantity < $value ) {
					return false;
				}
			} elseif ( 'equal_to' === $type ) {
				if ( $quantity != $value ) {
					return false;
				}
			} elseif ( 'not_equal_to' === $type ) {
				if ( $quantity == $value ) {
					return false;
				}
			}
		}

		if ( empty( $quantities ) ) {
			if ( 'less_than' === $type ) {
				if ( $value > 0 ) {
					return true;
				}
			} elseif ( 'less_equal_to' === $type ) {
				if ( $value >= 0 ) {
					return true;
				}
			} elseif ( 'greater_equal_to' === $type || 'equal_to' === $type ) {
				if ( 0 == $value ) {
					return true;
				}
			}

			return false;
		}

		return true;
	}

}
