<?php
/**
 * Functions for retrieving and updating events from the database.
 *
 * @package Community_Calendar
 */

/**
 * Event data from database.
 */
class Comcal_Event extends Comcal_Database_Table {
    /**
     * Event date and time. Don't use directly, always use get_start_date_time().
     *
     * @var Comcal_Date_Time
     */
    private $start_date_time = null;

    const IDPREFIX = 'ev';

    public static function get_defaults() {
        return array(
            'date'      => '2019-01-01',
            'time'      => '12:00:00',
            'eventId'   => '',
            'public'    => 0,
            'created'   => current_time( 'mysql' ),
            'userid'    => Comcal_User_Capabilities::current_user_id(),
            'joinDaily' => 1,
        );
    }

    /**
     * Returns how many Events have been added to the database
     * within the past X minutes.
     *
     * @param int $within_last_minutes How many minutes to look back.
     * @return int Number of events.
     */
    public static function count_events( $within_last_minutes = 5 ) {
        $prev_date_time = Comcal_Date_Time::now()->get_prev_minutes( $within_last_minutes );
        return static::count( 'created >= %s', array( $prev_date_time->format( 'Y-m-d H:i:s' ) ) );
    }

    public static function get_id_field_name() {
        return 'eventId';
    }
    public static function get_all_field_names() {
        return array(
            'eventId',
            'date',
            'time',
            'dateEnd',
            'timeEnd',
            'organizer',
            'location',
            'address',
            'title',
            'description',
            'url',
            'public',
            'created',
            'calendarName',
            'imageUrl',
            'submitterName',
            'submitterEmail',
            'userid',
            'joinDaily',  // Multi-day event can be joined on any day.
        );
    }
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'comcal';
    }
    protected static function get_create_table_query() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE [T] (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            eventId tinytext NOT NULL,
            date date DEFAULT '0000-00-00' NOT NULL,
            time time DEFAULT '00:00:00' NOT NULL,
            dateEnd date DEFAULT '0000-00-00' NOT NULL,
            timeEnd time DEFAULT '00:00:00' NOT NULL,
            title tinytext NOT NULL,
            organizer tinytext DEFAULT '' NOT NULL,
            location tinytext DEFAULT '' NOT NULL,
            address tinytext DEFAULT '' NOT NULL,
            description text NOT NULL,
            url varchar(1300) DEFAULT '' NOT NULL,
            public tinyint(2) DEFAULT 0 NOT NULL,
            created timestamp DEFAULT CURRENT_TIMESTAMP NOT NULL,
            calendarName tinytext NOT NULL,
            imageUrl varchar(1300) NOT NULL,
            submitterName varchar(1300) NOT NULL,
            submitterEmail varchar(1300) NOT NULL,
            userid mediumint(9) NOT NULL,
            joinDaily tinyint(2) DEFAULT 1 NOT NULL,
            PRIMARY KEY  (id)
            ) $charset_collate;";
        return $sql;
    }

    public function add_category( $category, bool $is_primary ) {
        $vs = Comcal_Event_Vs_Category::create( $this, $category, $is_primary );
        $vs->store();
    }
    public function has_category( $category, bool $is_primary ) {
        return Comcal_Event_Vs_Category::isset( $this, $category, $is_primary );
    }
    public function remove_all_categories() {
        Comcal_Event_Vs_Category::remove_event( $this );
    }
    public function get_categories() {
        return Comcal_Event_Vs_Category::get_categories( $this );
    }
    public function get_primary_category() {
        $categories = Comcal_Event_Vs_Category::get_categories( $this, true );
        if ( empty( $categories ) ) {
            return null;
        }
        return $categories[0];
    }

    public function get_public_fields() {
        /* returns fields and values for display */
        return array(
            'eventId'        => $this->get_field( 'eventId' ),
            'date'           => $this->get_field( 'date' ),
            'time'           => $this->get_field( 'time' ),
            'dateEnd'        => $this->get_field( 'dateEnd' ),
            'timeEnd'        => $this->get_field( 'timeEnd' ),
            'organizer'      => $this->get_field( 'organizer' ),
            'location'       => $this->get_field( 'location' ),
            'address'        => $this->get_field( 'address' ),
            'title'          => $this->get_field( 'title' ),
            'description'    => $this->get_field( 'description' ),
            'url'            => $this->get_field( 'url' ),
            'public'         => $this->get_field( 'public' ),
            'created'        => $this->get_field( 'created' ),
            'categories'     => $this->get_categories_details(),
            'calendarName'   => $this->get_field( 'calendarName' ),
            'imageUrl'       => $this->get_field( 'imageUrl' ),
            'number_of_days' => $this->get_number_of_days(),
        );
    }
    public static function get_text_field_names() {
        // TODO: Determine text fields from CREATE TABLE query.
        return array(
            'eventId',
            'organizer',
            'title',
            'location',
            'address',
            'description',
            'url',
            'imageUrl',
            'submitterName',
            'submitterEmail',
        );
    }

    protected function sanitize_data() {
        if ( in_array( $this->get_field( 'dateEnd' ), array( '', '0000-00-00' ), true ) ) {
            // If end date not set, use same as start date.
            $this->set_field( 'dateEnd', $this->get_field( 'date' ) );
        }
        if ( in_array( $this->get_field( 'timeEnd' ), array( '', '00:00:00' ), true ) ) {
            // If end time not set, use same as start time.
            $this->set_field( 'timeEnd', $this->get_field( 'time' ) );
        }

        // Make sure the end date and time are not before start date and time.
        $duration = $this->get_duration();
        if ( 1 === $duration->invert ) {
            // Negative duration: Set end to the same as start.
            $this->set_field( 'dateEnd', $this->get_field( 'date' ) );
            $this->set_field( 'timeEnd', $this->get_field( 'time' ) );
        }
    }

    public function get_date_str(): string {
        return $this->get_field( 'date' );
    }
    public function get_time_str(): string {
        return $this->get_field( 'time' );
    }
    public function get_start_date_time( int $day ): Comcal_Date_Time {
        if ( null === $this->start_date_time ) {
            // Initialize on first use.
            $this->start_date_time = Comcal_Date_Time::from_date_str_time_str( $this->get_field( 'date' ), $this->get_field( 'time' ) );
        }
        if ( 0 === $day ) {
            return $this->start_date_time;
        } else {
            return $this->start_date_time->get_next_day( $day );
        }
    }

    public function get_end_date_time(): Comcal_Date_Time {
        return Comcal_Date_Time::from_date_str_time_str( $this->get_field( 'dateEnd' ), $this->get_field( 'timeEnd' ) );
    }

    public function get_created_date() : Comcal_Date_Time {
        $created = $this->get_field( 'created' );
        return Comcal_Date_Time::from_date_time_str( $created );
    }

    public function get_categories_details() {
        $result = array();
        foreach ( $this->get_categories() as $c ) {
            $result[] = $c->get_public_fields();
        }
        return $result;
    }
    public function get_number_of_days() {
        $start_date = Comcal_Date_Time::from_date_str_time_str( $this->get_field( 'date' ), '00:00' );
        $end_date   = Comcal_Date_Time::from_date_str_time_str( $this->get_field( 'dateEnd' ), '00:00' );
        $diff       = $end_date->get_date_time_difference( $start_date );
        if ( 1 === $diff->invert ) {
            return 1;
        }
        return $diff->days + 1;
    }

    public function get_duration() {
        $start_date = Comcal_Date_Time::from_date_str_time_str( $this->get_field( 'date' ), $this->get_field( 'time' ) );
        $end_date   = Comcal_Date_Time::from_date_str_time_str( $this->get_field( 'dateEnd' ), $this->get_field( 'timeEnd' ) );
        $diff       = $end_date->get_date_time_difference( $start_date );
        return $diff;
    }

    public function current_user_can_edit() {
        if ( Comcal_User_Capabilities::administer_events() ) {
            return true;
        }
        if ( Comcal_User_Capabilities::edit_own_events() ) {
            $current_user_id = Comcal_User_Capabilities::current_user_id();
            return "$current_user_id" === $this->get_field( 'userid' );
        }
        return true;
    }

    /**
     * Returns whether this event instance has already passed.
     *
     * @param int $day Multi-day events only: which day of the event? Default: last day.
     * @return bool Event has passed.
     */
    public function is_obsolete( $day = -1 ) {
        if ( -1 === $day ) {
            $day = $this->get_number_of_days() - 1;
        }
        $day_start_date = $this->get_start_date_time( $day );
        return $day_start_date->is_day_less_than( Comcal_Date_Time::now() );
    }
}
