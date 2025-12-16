<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class WCCS_Countdown_Timer {

    protected $date_time_validator;

    public function __construct( WCCS_Date_Time_Validator $date_time_validator = null ) {
        $this->date_time_validator = null !== $date_time_validator ? $date_time_validator : WCCS()->WCCS_Date_Time_Validator;
    }

    public function get_valid_nearest_end_time( array $date_times, $match_mode = 'one' ) {
        if ( empty( $date_times ) ) {
			return false;
		}

		// It is a OR condition.
		if ( is_array( $date_times[0] ) && ! isset( $date_times[0]['type'] ) ) {
			return $this->get_or_rules_nearest_end_time( $date_times );
		}

		if ( ! $this->date_time_validator->is_valid_date_times( $date_times, $match_mode ) ) {
			return false;
		}

		if ( 'all' === $match_mode ) {
			return $this->get_all_valid_nearest_end_time( $date_times );
		}

		return $this->get_one_valid_nearest_end_time( $date_times );
	}

	protected function get_or_rules_nearest_end_time( array $date_times ) {
		if ( empty( $date_times ) ) {
			return false;
		}

		$valids = array();
		foreach ( $date_times as $group ) {
			if ( empty( $group ) ) {
				continue;
			}

			$valid = true;
			foreach ( $group as $date_time ) {
				if ( empty( $date_time ) || ! $this->date_time_validator->is_valid( $date_time ) ) {
					$valid = false;
					break;
				}
			}
			if ( $valid ) {
				$valids[] = $group;
			}
		}
		if ( empty( $valids ) ) {
			return false;
		}

		$ends = array();
		foreach ( $valids as $valid ) {
			if ( $end = $this->get_all_valid_nearest_end_time( $valid ) ) {
				$ends[] = $end;
			}
		}
		if ( empty( $ends ) ) {
			return false;
		}

		// Find nearest end time or minimum one.
		$end = $ends[0];
		for ( $i = 1; $i < count( $ends ); $i++ ) {
			if ( strtotime( $end ) > strtotime( $ends[ $i ] ) ) {
				$end = $ends[ $i ];
			}
		}

		return $end;
	}

	protected function get_one_valid_nearest_end_time( array $date_times ) {
		if ( empty( $date_times ) ) {
			return false;
		}

		foreach ( $date_times as $date_time ) {
			if ( $this->date_time_validator->is_valid( $date_time ) ) {
				return $this->get_date_time_end( $date_time );
			}
		}

		return false;
	}

	protected function get_all_valid_nearest_end_time( array $date_times ) {
		if ( empty( $date_times ) ) {
			return false;
		}

		$ends = array();
		$end  = false;

		foreach ( $date_times as $date_time ) {
			$end = $this->get_date_time_end( $date_time );
			if ( false === $end ) {
				return false;
			}
			$ends[] = $end;
		}

		if ( empty( $ends ) ) {
			return false;
		}

		// Find nearest end time or minimum one.
		$end = $ends[0];
		for ( $i = 1; $i < count( $ends ); $i++ ) {
			if ( strtotime( $end ) > strtotime( $ends[ $i ] ) ) {
				$end = $ends[ $i ];
			}
		}

		return $end;
	}

	protected function get_date_time_end( array $date_time ) {
		if ( empty( $date_time ) || empty( $date_time['type'] ) ) {
			return false;
		}

		switch ( $date_time['type'] ) {
			case 'date':
				if ( ! empty( $date_time['end']['time'] ) ) {
					return @date( 'Y-m-d H:i:s', strtotime( '+1 day', strtotime( $date_time['end']['time'] ) ) );
				}
				break;

			case 'date_time':
				if ( ! empty( $date_time['end']['time'] ) ) {
					return @date( 'Y-m-d H:i:s', strtotime( $date_time['end']['time'] ) );
				}
				break;

			case 'specific_date':
				if ( ! empty( $date_time['date']['time'] ) ) {
					$dates = array_map( 'trim', explode( '","', trim( $date_time['date']['time'], '[]"' ) ) );
					if ( ! empty( $dates ) ) {
						usort( $dates, array( WCCS()->WCCS_Sorting, 'sort_by_time_asc' ) );
						$now = strtotime( @date( 'Y-m-d', current_time( 'timestamp' ) ) );
						$now_pos = false;
						for ( $i = 0; $i < count( $dates ); $i++ ) {
							if ( $now == strtotime( $dates[ $i ] ) ) {
								$now_pos = $i;
								break;
							}
						}
						if ( false === $now_pos ) {
							return false;
						}

						if ( count( $dates ) <= $now_pos + 1 ) {
							return @date( 'Y-m-d H:i:s', strtotime( '+1 day', $now ) );
						}

						for ( $i = $now_pos; $i < count( $dates ); $i++ ) {
							if ( $i + 1 >= count( $dates ) ) {
								return @date( 'Y-m-d H:i:s', strtotime( '+1 day', strtotime( $dates[ $i ] ) ) );
							} elseif ( @date( 'Y-m-d', strtotime( '+1 day', strtotime( $dates[ $i ] ) ) ) != @date( 'Y-m-d', strtotime( $dates[ $i + 1 ] ) ) ) {
								return @date( 'Y-m-d H:i:s', strtotime( '+1 day', strtotime( $dates[ $i ] ) ) );
							}
						}
					}
				}
				break;

			case 'time':
				if ( isset( $date_time['end_time'] ) ) {
					return @date( 'Y-m-d', current_time( 'timestamp' ) ) . ' ' . $date_time['end_time'];
				}
				break;

			case 'days':
				if ( ! empty( $date_time['days'] ) && 7 > count( $date_time['days'] ) ) {
					for ( $i = 1; $i <= 7; $i++ ) {
						$next_day = date( 'l', strtotime( "+{$i} day" ) );
						if ( ! in_array( $next_day, $date_time['days'] ) ) {
							return @date( 'Y-m-d', strtotime( "+{$i} day" ) ) . ' 00:00:00';
						}
					}
				}
				break;
		}

		return false;
	}

}
