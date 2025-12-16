<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WCCS_Date_Time {

	public $format;

	protected $start_of_week;

	public function __construct( $format = 'Y-m-d', $start_of_week = null ) {
		$this->format        = $format;
		$this->start_of_week = null === $start_of_week ? (int) get_option( 'start_of_week', 1 ) : $start_of_week;
	}

	public function get_format() {
		return $this->format;
	}

	public function get_start_of_week() {
		return $this->start_of_week;
	}

	public function set_format( $format ) {
		$this->format = $format;
		return $this;
	}

	public function set_start_of_week( $start_of_week ) {
		$start_of_week = absint( $start_of_week );
		if ( $start_of_week >= 0 && $start_of_week <= 6 ) {
			$this->start_of_week = $start_of_week;
		}

		return $this;
	}

	public function current_day() {
		return date_i18n( $this->format, strtotime( 'midnight' ) );
	}

	public function current_week_start() {
		if ( date_i18n( 'w' ) == $this->start_of_week ) {
			return $this->current_day();
		}

		$days = array(
			'sunday',
			'monday',
			'tuesday',
			'wednesday',
			'thursday',
			'friday',
			'saturday',
		);

		return date_i18n( $this->format, strtotime( 'midnight last ' . $days[ $this->start_of_week ] ) );
	}

	public function current_month_start() {
		return date_i18n( $this->format, strtotime( 'midnight first day of this month' ) );
	}

	public function current_year_start() {
		return date_i18n( $this->format, strtotime( 'midnight first day of january' ) );
	}

	public function days_from( $from, $days ) {
		$days = intval( $days );
		if ( 0 == $days ) {
			return $from;
		}

		$from = strtotime( $from );
		if ( ! $from ) {
			return false;
		}

		return date_i18n( $this->format, strtotime( "$days day midnight", $from ) );
	}

	public function later_days_start( $days ) {
		$days = absint( $days );
		if ( ! $days ) {
			return false;
		}

		return date_i18n( $this->format, strtotime( "-$days day midnight" ) );
	}

	public function later_weeks_start( $weeks ) {
		$weeks = absint( $weeks );
		if ( ! $weeks ) {
			return false;
		}

		return date_i18n( $this->format, strtotime( "-$weeks week midnight" ) );
	}

	public function later_months_start( $months ) {
		$months = absint( $months );
		if ( ! $months ) {
			return false;
		}

		return date_i18n( $this->format, strtotime( "-$months month midnight" ) );
	}

	public function later_years_start( $years ) {
		$years = absint( $years );
		if ( ! $years ) {
			return false;
		}

		return date_i18n( $this->format, strtotime( "-$years year midnight" ) );
	}

	/**
	 * Get previous days start and end or one of them.
	 * 
	 * @param int    $days
	 * @param string $start_end 'start'||'end'||null 'start' for get start of the day and 'end' for get end of the day or null to get both of them.
	 * 
	 * @return array|string
	 */
	public function previous_days( $days, $start_end = null ) {
		$timezone = new DateTimeZone( wc_timezone_string() );
		$datetime = new DateTime( "-{$days} day midnight", $timezone );
		$date     = array(
			'start' => $datetime->format( 'Y-m-d 00:00:00' ),
			'end'   => $datetime->format( 'Y-m-d 23:59:59' ),
		);
		if ( 1 < $days ) {
			$datetime    = new DateTime( '-1 day midnight', $timezone );
			$date['end'] = $datetime->format( 'Y-m-d 23:59:59' );
		}
		if ( 'start' === $start_end ) {
			return $date['start'];
		} elseif ( 'end' === $start_end ) {
			return $date['end'];
		}
		return $date;
	}

	/**
	 * Get previous weeks start and end or one of them.
	 * 
	 * @param int    $weeks
	 * @param string $start_end 'start'||'end'||null 'start' for get start of the day and 'end' for get end of the day or null to get both of them.
	 * 
	 * @return array|string
	 */
	public function previous_weeks( $weeks, $start_end = null ) {
		$days     = array(
			'Sunday',
			'Monday',
			'Tuesday',
			'Wednesday',
			'Thursday',
			'Friday',
			'Saturday',
		);
		$timezone = new DateTimeZone( wc_timezone_string() );
		$datetime = new DateTime( "-{$weeks} week midnight", $timezone );
		$datetime = $days[ $this->start_of_week ] == $datetime->format( 'L' ) ? $datetime : $datetime->modify( 'last ' . $days[ $this->start_of_week ] );
		$date     = array(
			'start' => $datetime->format( 'Y-m-d 00:00:00' ),
			'end'   => $datetime->modify( '+6 day midnight' )->format( 'Y-m-d 23:59:59' ),
		);
		if ( 1 < $weeks ) {
			$datetime    = new DateTime( '-1 week midnight', $timezone );
			$datetime    = $days[ $this->start_of_week ] == $datetime->format( 'L' ) ? $datetime : $datetime->modify( 'last ' . $days[ $this->start_of_week ] );
			$date['end'] = $datetime->modify( '+6 day midnight' )->format( 'Y-m-d 23:59:59' );
		}
		if ( 'start' === $start_end ) {
			return $date['start'];
		} elseif ( 'end' === $start_end ) {
			return $date['end'];
		}
		return $date;
	}

	/**
	 * Get previous months start and end or one of them.
	 * 
	 * @param int    $months
	 * @param string $start_end 'start'||'end'||null 'start' for get start of the day and 'end' for get end of the day or null to get both of them.
	 * 
	 * @return array|string
	 */
	public function previous_months( $months, $start_end = null ) {
		$timezone = new DateTimeZone( wc_timezone_string() );
		$datetime = new DateTime( "-{$months} month midnight", $timezone );
		$date           = array(
			'start' => $datetime->format( 'Y-m-01 00:00:00' ),
			'end'   => $datetime->format( 'Y-m-t 23:59:59' ),
		);
		if ( 1 < $months ) {
			$datetime    = new DateTime( '-1 month midnight', $timezone );
			$date['end'] = $datetime->format( 'Y-m-t 23:59:59' );
		}
		if ( 'start' === $start_end ) {
			return $date['start'];
		} elseif ( 'end' === $start_end ) {
			return $date['end'];
		}
		return $date;
	}

	/**
	 * Get previous years start and end or one of them.
	 * 
	 * @param int    $years
	 * @param string $start_end 'start'||'end'||null 'start' for get start of the year and 'end' for get end of the year or null to get both of them.
	 * 
	 * @return array|string
	 */
	public function previous_years( $years, $start_end = null ) {
		$timezone = new DateTimeZone( wc_timezone_string() );
		$datetime = new DateTime( "-{$years} year midnight", $timezone );
		$date     = array(
			'start' => $datetime->format( 'Y-01-01 00:00:00' ),
			'end'   => $datetime->format( 'Y-12-31 23:59:59' ),
		);
		if ( 1 < $years ) {
			$datetime    = new DateTime( '-1 year midnight', $timezone );
			$date['end'] = $datetime->format( 'Y-12-31 23:59:59' );
		}
		if ( 'start' === $start_end ) {
			return $date['start'];
		} elseif ( 'end' === $start_end ) {
			return $date['end'];
		}
		return $date;
	}

	public function get_date_time_args( array $item ) {
		$args = array();

		if ( ! isset( $item['time_type'] ) ) {
			return false;
		}

		switch ( $item['time_type'] ) {
			case 'date' :
			case 'date_time' :
				if ( empty( $item['start']['time'] ) && empty( $item['end']['time'] ) ) {
					return false;
				}

				$format = 'date_time' === $item['time_type'] ? 'Y-m-d H:i' : 'Y-m-d';

				if ( ! empty( $item['start']['time'] ) ) {
					$start_date = date_i18n( $format, strtotime( $item['start']['time'] ) );
					if ( false === $start_date ) {
						return false;
					}
					$args['date_after'] = $start_date;
				}

				if ( ! empty( $item['end']['time'] ) ) {
					$end_date = date_i18n( $format, strtotime( $item['end']['time'] ) );
					if ( false === $end_date ) {
						return false;
					}
					$args['date_before'] = $end_date;
				}
			break;

			case 'current' :
				if ( 'day' === $item['current'] ) {
					$args['date_after'] = $this->later_days_start( 1 );
				} elseif ( 'week' === $item['current'] ) {
					$start_date = $this->current_week_start();
					if ( ! $start_date ) {
						return false;
					}
					$args['date_after'] = $this->days_from( $start_date, -1 );
				} elseif ( 'month' === $item['current'] ) {
					$start_date = $this->current_month_start();
					if ( ! $start_date ) {
						return false;
					}
					$args['date_after'] = $this->days_from( $start_date, -1 );
				} elseif ( 'year' === $item['current'] ) {
					$start_date = $this->current_year_start();
					if ( ! $start_date ) {
						return false;
					}
					$args['date_after'] = $this->days_from( $start_date, -1 );
				}
			break;

			case 'day' :
			case 'week' :
			case 'month' :
			case 'year' :
				$number = ! empty( $item['number_value_1'] ) ? absint( $item['number_value_1'] ) : 0;
				if ( ! $number ) {
					return false;
				}

				if ( 'day' === $item['time_type'] ) {
					$args['date_after'] = $this->later_days_start( $number + 1 );
				} elseif ( 'week' === $item['time_type'] ) {
					$start_date = $this->later_weeks_start( $number );
					if ( ! $start_date ) {
						return false;
					}
					$args['date_after'] = $this->days_from( $start_date, -1 );
				} elseif ( 'month' === $item['time_type'] ) {
					$start_date = $this->later_months_start( $number );
					if ( ! $start_date ) {
						return false;
					}
					$args['date_after'] = $this->days_from( $start_date, -1 );
				} elseif ( 'year' === $item['time_type'] ) {
					$start_date = $this->later_years_start( $number );
					if ( ! $start_date ) {
						return false;
					}
					$args['date_after'] = $this->days_from( $start_date, -1 );
				}
			break;

			case 'previous_days':
			case 'previous_weeks':
			case 'previous_months':
			case 'previous_years':
				$number = ! empty( $item['number_value_1'] ) ? absint( $item['number_value_1'] ) : 0;
				if ( ! $number ) {
					return false;
				}

				$date = array();
				if ( 'previous_days' === $item['time_type'] ) {
					$date = $this->previous_days( $number );
				} elseif ( 'previous_weeks' === $item['time_type'] ) {
					$date = $this->previous_weeks( $number );
				} elseif ( 'previous_months' === $item['time_type'] ) {
					$date = $this->previous_months( $number );
				} elseif ( 'previous_years' === $item['time_type'] ) {
					$date = $this->previous_years( $number );
				}

				if ( empty( $date ) || empty( $date['start'] ) || empty( $date['end'] ) ) {
					return false;
				}

				$args['date_after']  = $date['start'];
				$args['date_before'] = $date['end'];
			break;
		} // end switch.

		return $args;
	}

}
