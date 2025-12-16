<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Date_Time_Validator {

	/**
	 * Checking is valid given date times.
	 *
	 * @param  array  $date_times
	 * @param  string $match_mode 'one' or 'all'
	 *
	 * @return boolean
	 */
	public function is_valid_date_times( array $date_times, $match_mode = 'one' ) {
		if ( empty( $date_times ) ) {
			return true;
		}

		if ( is_array( $date_times[0] ) && ! isset( $date_times[0]['type'] ) ) {
			$empty = true;
			foreach ( $date_times as $group ) {
				if ( empty( $group ) ) {
					continue;
				}

				$empty = false;
				$valid = true;
				foreach ( $group as $date_time ) {
					if ( ! $this->is_valid( $date_time ) ) {
						$valid = false;
						break;
					}
				}
				if ( $valid ) {
					return true;
				}
			}
			return $empty;
		}

		foreach ( $date_times as $date_time ) {
			if ( 'one' === $match_mode && $this->is_valid( $date_time ) ) {
				return true;
			} elseif ( 'all' === $match_mode && ! $this->is_valid( $date_time ) ) {
				return false;
			}
		}

		return 'all' === $match_mode;
	}

	/**
	 * Is valid given date time.
	 *
	 * @param  array $date_time
	 *
	 * @return boolean
	 */
	public function is_valid( array $date_time ) {
		if ( empty( $date_time ) || empty( $date_time['type'] ) ) {
			return false;
		}

		if ( in_array( $date_time['type'], array( 'date', 'date_time' ) ) ) {
			if ( empty( $date_time['start']['time'] ) && empty( $date_time['end']['time'] ) ) {
				return false;
			}

			$format = 'date_time' === $date_time['type'] ? 'Y-m-d H:i' : 'Y-m-d';
			$now    = strtotime( date( $format, current_time( 'timestamp' ) ) );

			if ( ! empty( $date_time['start']['time'] ) ) {
				$start_date = strtotime( date( $format, strtotime( $date_time['start']['time'] ) ) );
				if ( false === $start_date || $now < $start_date ) {
					return false;
				}
			}

			if ( ! empty( $date_time['end']['time'] ) ) {
				$end_date = strtotime( date( $format, strtotime( $date_time['end']['time'] ) ) );
				if ( false === $end_date || $now > $end_date ) {
					return false;
				}
			}

			return true;
		} elseif ( 'specific_date' === $date_time['type'] ) {
			if ( ! empty( $date_time['date']['time'] ) ) {
				$dates = array_map( 'trim', explode( ',', trim( $date_time['date']['time'], '[]' ) ) );
				if ( ! empty( $dates ) ) {
					$now = strtotime( date( 'Y-m-d', current_time( 'timestamp' ) ) );
					foreach ( $dates as $date ) {
						$date = strtotime( trim( $date, '"' ) );
						if ( $now == $date ) {
							return true;
						}
					}
				}
			}
		} elseif ( 'time' === $date_time['type'] ) {
			if ( empty( $date_time['start_time'] ) || empty( $date_time['end_time'] ) ) {
				return false;
			}

			$now        = current_time( 'timestamp' );
			$start_date = strtotime( $date_time['start_time'], $now );
			$end_date   = strtotime( $date_time['end_time'], $now );

			// If end_time is less than start_time, add 24 hours to end_time
			if ( $end_date < $start_date ) {
				$end_date = strtotime( '+1 day', $end_date );
			}

			return ( $now >= $start_date && $now <= $end_date );
		} elseif ( 'days' === $date_time['type'] ) {
			if ( ! empty( $date_time['days'] ) ) {
				$today = date( 'l', current_time( 'timestamp' ) );
				foreach ( $date_time['days'] as $day ) {
					if ( $today == $day ) {
						return true;
					}
				}
			}
		}

		return false;
	}

}
