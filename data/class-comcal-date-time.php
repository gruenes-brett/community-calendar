<?php
/**
 * Functions for handling PHP DateTime objects
 *
 * @package Community_Calendar
 */

$comcal_weekday_names_de = array(
    'Sonntag',
    'Montag',
    'Dienstag',
    'Mittwoch',
    'Donnerstag',
    'Freitag',
    'Samstag',
);

$comcal_month_map = array(
    'Jan' => 'Januar',
    'Feb' => 'Februar',
    'Mar' => 'März',
    'Apr' => 'April',
    'May' => 'Mai',
    'Jun' => 'Juni',
    'Jul' => 'Juli',
    'Aug' => 'August',
    'Sep' => 'September',
    'Oct' => 'Oktober',
    'Nov' => 'November',
    'Dec' => 'Dezember',
);


/**
 * Wrapper for a PHP DateTime object with convenience functions
 */
class Comcal_Date_Time {

    /**
     * Actual DateTime object.
     *
     * @var DateTime $date_time Basic DateTime object.
     */
    protected $date_time = null;

    public static function from_date_time_str( $date_time_str ) {
        $instance            = new self();
        $instance->date_time = new DateTime( $date_time_str );
        return $instance;
    }
    public static function from_date_str_time_str( $date_str, $time_str ) {
        $instance            = new self();
        $instance->date_time = new DateTime( $date_str . 'T' . $time_str );
        return $instance;
    }
    public static function from_date_time( $date_time ) {
        $instance            = new self();
        $instance->date_time = $date_time;
        return $instance;
    }
    public static function now() {
        $instance            = new self();
        $instance->date_time = new DateTime();
        return $instance;
    }
    public static function next_monday() {
        $instance = static::now();
        while ( ! $instance->is_monday() ) {
            $instance = $instance->get_next_day();
        }
        return $instance;
    }

    public function format( $fmt ) {
        return $this->date_time->format( $fmt );
    }

    public function get_date_str() {
        return $this->date_time->format( 'Y-m-d' );
    }
    public function get_time_str() {
        return $this->date_time->format( 'H:i' );
    }
    public function get_start_date_time() {
        return $this->date_time;
    }

    public function get_pretty_time() {
        return $this->date_time->format( 'H:i' ) . ' Uhr';
    }

    public function get_timestamp() {
        return $this->date_time->getTimestamp();
    }

    public function get_database_timestamp() {
        return $this->date_time->format( 'Y-m-d H:i:s' );
    }

    public function get_humanized_time() {
        $hour   = $this->date_time->format( 'G' );
        $minute = $this->date_time->format( 'i' );
        $time   = $hour;
        if ( '00' !== $minute ) {
            $time .= ":$minute";
        }
        return $time . ' Uhr';
    }

    public function get_pretty_date() {
        return $this->date_time->format( 'd.m.Y' );
    }

    public function get_humanized_date() {
        $weekday = $this->get_weekday();
        return "$weekday, " . $this->date_time->format( 'd.m.' );
    }

    public function get_weekday() {
        global $comcal_weekday_names_de;
        return $comcal_weekday_names_de[ $this->date_time->format( 'w' ) ];
    }
    public function is_monday() {
        return '1' === $this->date_time->format( 'N' );
    }
    public function is_sunday() {
        return '7' === $this->date_time->format( 'N' );
    }

    public function get_short_weekday_and_day() {
        return $this->get_day_of_month() . ' ' . $this->get_short_weekday();
    }

    public function get_short_weekday() {
        return substr( $this->get_weekday(), 0, 2 );
    }

    public function get_day_of_month() {
        return $this->date_time->format( 'd' );
    }

    public function get_day_classes() {
        $classes = '';
        switch ( $this->date_time->format( 'w' ) ) {
            case 0:
            case 6:
                $classes .= 'weekend';
        }
        if ( 0 === strcmp( $this->get_date_str(), date( 'Y-m-d' ) ) ) {
            $classes .= ' today';
        }
        return $classes;
    }

    public function get_month_title() {
        global $comcal_month_map;
        $month_en = $this->date_time->format( 'M' );
        $month    = $month_en;
        if ( isset( $comcal_month_map[ $month_en ] ) ) {
            $month = $comcal_month_map[ $month_en ];
        }
        return $month . $this->date_time->format( ' Y' );
    }

    public function get_month_link() {
        return $this->date_time->format( 'M-Y' );
    }

    public function get_first_of_month_date_time(): Comcal_Date_Time {
        $dt = new DateTime( $this->date_time->format( 'Y-m-01\TH:i:s' ) );
        return self::from_date_time( $dt );
    }

    public function get_year_month() {
        return $this->date_time->format( 'Y-m' );
    }

    public function is_same_month( $date_time ) {
        return 0 === strcmp( $date_time->get_year_month(), $this->get_year_month() );
    }

    public function is_same_day( $date_time ) {
        if ( null === $date_time ) {
            return false;
        }
        return 0 === strcmp( $date_time->get_date_str(), $this->get_date_str() );
    }

    public function compare_date( $other ) {
        return strcmp( $this->get_date_str(), $other->get_date_str() );
    }

    public function is_day_less_than( $other ) {
        return $this->compare_date( $other ) < 0;
    }

    public function is_day_greater_than( $other ) {
        return $this->compare_date( $other ) > 0;
    }

    public function is_before( Comcal_Date_Time $other ) {
        return $this->date_time->getTimestamp() < $other->get_timestamp();
    }

    /**
     * Checks if this date is within a date range. Time is ignored.
     * Parameter values of null are allowed and will ignore this limit.
     *
     * @param Comcal_Date_Time $earliest_date Beginning of date range (including).
     * @param Comcal_Date_Time $latest_date End of date range (including).
     * @return bool true, if in range, false otherwise.
     */
    public function is_in_date_range( $earliest_date, $latest_date ) {
        if ( null !== $earliest_date && $this->is_day_less_than( $earliest_date ) ) {
            return false;
        }
        if ( null !== $latest_date && $this->is_day_greater_than( $latest_date ) ) {
            return false;
        }
        return true;
    }

    public function get_next_day( $number_of_days = 1 ) {
        $next_day = clone $this->date_time;
        $next_day->add( new DateInterval( "P${number_of_days}D" ) );
        return self::from_date_time( $next_day );
    }

    public function get_prev_day( $number_of_days = 1 ) {
        $next_day = clone $this->date_time;
        $next_day->sub( new DateInterval( "P${number_of_days}D" ) );
        return self::from_date_time( $next_day );
    }

    public function get_last_monday() {
        if ( $this->is_monday() ) {
            return clone $this;
        }
        $yesterday = clone $this;
        do {
            $yesterday = $yesterday->get_prev_day();
        } while ( ! $yesterday->is_monday() );
        return $yesterday;
    }

    /**
     * Creates a new Comcal_Date_Time that is $minutes before the current.
     *
     * @param int $minutes How many minutes to subtract.
     *
     * @return newly created Comcal_Date_Time
     */
    public function get_prev_minutes( $minutes ) {
        $prev_minutes_date = clone $this->date_time;
        $prev_minutes_date->sub( new DateInterval( "PT${minutes}M" ) );
        return self::from_date_time( $prev_minutes_date );
    }

    public function get_date_time_difference( $other_date_time ) {
        $this_date_time = clone $this->date_time;
        $diff           = $other_date_time->get_start_date_time()->diff( $this_date_time );
        return $diff;
    }

    public function get_all_dates_until( $end_date_time ) {
        $dates   = array();
        $current = $this;
        while ( $current->is_day_less_than( $end_date_time ) ) {
            $dates[] = $current;
            $current = $current->get_next_day();
        }
        return $dates;
    }

    public function get_last_day_of_month() {
        $next_day = $this;
        do {
            $previous_day = $next_day;
            $next_day     = $next_day->get_next_day();
        } while ( $previous_day->is_same_month( $next_day ) );
        return $next_day;
    }
}
