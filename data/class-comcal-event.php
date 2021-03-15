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

    const IDPREFIX = 'event:';

    public static function get_defaults() {
        return array(
            'date'    => '2019-01-01',
            'time'    => '12:00:00',
            'eventId' => '',
            'public'  => 0,
            'created' => current_time( 'mysql' ),
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
            'title',
            'description',
            'url',
            'public',
            'created',
            'calendarName',
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
            description text NOT NULL,
            url varchar(1300) DEFAULT '' NOT NULL,
            public tinyint(2) DEFAULT 0 NOT NULL,
            created timestamp NOT NULL,
            calendarName tinytext NOT NULL,
            PRIMARY KEY  (id)
            ) $charset_collate;";
        return $sql;
    }

    public function add_category( $category ) {
        $vs = Comcal_Event_Vs_Category::create( $this, $category );
        $vs->store();
    }
    public function has_category( $category ) {
        return Comcal_Event_Vs_Category::isset( $this, $category );
    }
    public function remove_all_categories() {
        Comcal_Event_Vs_Category::remove_event( $this );
    }
    public function get_categories() {
        return Comcal_Event_Vs_Category::get_categories( $this );
    }

    public function get_public_fields() {
        /* returns fields and values for display */
        return array(
            'eventId' => $this->get_field( 'eventId' ),
            'date' => $this->get_field( 'date' ),
            'time' => $this->get_field( 'time' ),
            'dateEnd' => $this->get_field( 'dateEnd' ),
            'timeEnd' => $this->get_field( 'timeEnd' ),
            'organizer' => $this->get_field( 'organizer' ),
            'location' => $this->get_field( 'location' ),
            'title' => $this->get_field( 'title' ),
            'description' => $this->get_field( 'description' ),
            'url' => $this->get_field( 'url' ),
            'public' => $this->get_field( 'public' ),
            'created' => $this->get_field( 'created' ),
            'categories' => $this->get_categories_details(),
            'calendarName' => $this->get_field( 'calendarName' ),
            'number_of_days' => $this->getNumberOfDays(),
        );
    }
    public static function get_text_field_names() {
        return array( 'eventId', 'organizer', 'title', 'description', 'url' );
    }

    public function get_date_str(): string {
        return $this->get_field( 'date' );
    }
    public function get_start_date_time(): Comcal_Date_Time {
        if ( null === $this->start_date_time ) {
            // Initialize on first use.
            $this->start_date_time = Comcal_Date_Time::from_date_str_time_str( $this->get_field( 'date' ), $this->get_field( 'time' ) );
        }
        return $this->start_date_time;
    }

    public function get_categories_details() {
        $result = array();
        foreach ( $this->get_categories() as $c ) {
            $result[] = $c->get_public_fields();
        }
        return $result;
    }
    public function getNumberOfDays() {
        $start_date = Comcal_Date_Time::from_date_str_time_str( $this->get_field( 'date' ), '00:00' );
        $end_date   = Comcal_Date_Time::from_date_str_time_str( $this->get_field( 'dateEnd' ), '00:00' );
        $diff       = $end_date->get_date_time_difference( $start_date );
        if ( 1 === $diff->invert ) {
            return 1;
        }
        return $diff->days + 1;
    }
}


/**
 * Loads events from the database and allows to iterate over
 * them as Comcal_Event instances.
 */
class Comcal_Event_Iterator implements Iterator {

    /**
     * Current position.
     *
     * @var int
     */
    private $positition = -1;
    /**
     * All loaded rows.
     *
     * @var array()
     */
    public $event_rows = null;

    /**
     * Query database and initalize iterator.
     *
     * @param bool                   $public_only Only show events that are set public.
     * @param Comcal_Category        $category Only a certain category.
     * @param string                 $calendar_name Name of the calendar.
     * @param Comcal_Date_Time $start_date Range start - null for all.
     * @param Comcal_Date_Time $end_date Range end - null for all.
     *
     * @return array Database query result.
     */
    public function __construct(
        $public_only,
        $category = null,
        $calendar_name = '',
        $start_date = null,
        $end_date = null
    ) {
        $this->event_rows = _comcal_get_all_event_rows(
            $public_only,
            $category,
            $calendar_name,
            $start_date,
            $end_date
        );
        $this->positition = 0;
    }

    public function rewind() {
        $this->positition = 0;
    }

    public function current() {
        if ( -1 === $this->positition ) {
            return null;
        }
        return new Comcal_Event( $this->event_rows[ $this->positition ] );
    }

    public function key() {
        return $this->positition;
    }

    public function next() {
        $this->positition++;
    }

    public function valid() {
        return isset( $this->event_rows[ $this->positition ] );
    }
}


/**
 * Query events from database.
 *
 * @param bool                   $public_only Only show events that are set public.
 * @param Comcal_Category        $category Only a certain category.
 * @param string                 $calendar_name Name of the calendar.
 * @param Comcal_Date_Time $start_date Range start.
 * @param Comcal_Date_Time $end_date Range end.
 *
 * @return array Database query result.
 */
function _comcal_get_all_event_rows(
    $public_only = true,
    $category = null,
    $calendar_name = '',
    $start_date = null,
    $end_date = null
) {
    global $wpdb;
    $events_table  = Comcal_Event::get_table_name();
    $evt_cat_table = Comcal_Event_Vs_Category::get_table_name();

    $where_conditions   = array();
    $where_conditions[] = "($events_table.calendarName='$calendar_name' OR $events_table.calendarName='')";
    if ( $public_only ) {
        $where_conditions[] = "$events_table.public='1'";
    }
    if ( null !== $start_date ) {
        $where_conditions[] = "$events_table.date >= '$start_date'";
    }
    if ( null !== $end_date ) {
        $where_conditions[] = "$events_table.date <= '$end_date'";
    }

    if ( null === $category ) {
        $where = Comcal_Database::where_and( $where_conditions );
        $query = "SELECT * FROM $events_table $where ORDER BY date, time;";
    } else {
        $category_id        = $category->get_field( 'id' );
        $where_conditions[] = "$evt_cat_table.category_id=$category_id";

        $where = Comcal_Database::where_and( $where_conditions );
        $query = "SELECT $events_table.* FROM $events_table "
        . "INNER JOIN $evt_cat_table ON $evt_cat_table.event_id=$events_table.id "
        . "$where ORDER BY $events_table.date, $events_table.time;";
    }
    $rows = $wpdb->get_results( $query );
    return $rows;
}
