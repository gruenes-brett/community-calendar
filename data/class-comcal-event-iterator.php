<?php
/**
 * Iterator classes for returning events.
 *
 * @package Community_Calendar
 */

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
     * @param Comcal_Category         $category Only a certain category.
     * @param string                  $calendar_name Name of the calendar.
     * @param Comcal_Date_Time|string $start_date Range start - null for all.
     * @param Comcal_Date_Time|string $end_date Range end - null for all.
     *
     * @return Comcal_Event_Iterator Iterator inclucding database query result.
     */
    public static function load_from_database(
        $category = null,
        $calendar_name = '',
        $start_date = null,
        $end_date = null
    ) {
        if ( is_a( $start_date, 'Comcal_Date_Time' ) ) {
            $start_date = $start_date->get_date_str();
        }
        if ( is_a( $end_date, 'Comcal_Date_Time' ) ) {
            $end_date = $end_date->get_date_str();
        }
        $event_rows = Comcal_Query_Event_Rows::query_events_by_date(
            $category,
            $calendar_name,
            $start_date,
            $end_date
        );

        return new static( $event_rows );
    }

    public function __construct( $event_rows ) {
        // Remove duplicates.
        $event_ids  = array();
        $clean_rows = array();
        foreach ( $event_rows as $row ) {
            if ( in_array( $row->id, $event_ids, true ) ) {
                continue;
            }
            $clean_rows[] = $row;
            $event_ids[]  = $row->id;
        }

        $this->event_rows = $clean_rows;
        $this->positition = 0;
    }

    public function rewind() : void {
        $this->positition = 0;
    }

    /**
     * The current event.
     *
     * @return Comcal_Event
     */
    public function current() {
        if ( -1 === $this->positition ) {
            return null;
        }
        return new Comcal_Event( $this->event_rows[ $this->positition ] );
    }

    public function key() {
        return $this->positition;
    }

    public function next() : void {
        $this->positition++;
    }

    public function valid() : bool {
        return isset( $this->event_rows[ $this->positition ] );
    }
}


/**
 * Iterator that wraps around Comcal_Event_Iterator and that repeats multi-day events.
 */
class Comcal_Multiday_Event_Iterator implements Iterator {
    /**
     * The original event iterator.
     *
     * @var Comcal_Event_Iterator
     */
    private $event_iterator = null;

    /**
     * The event that is returned next: array( Comcal_Event, int $day ).
     *
     * @var list( Comcal_Event $event, int $day )
     */
    private $next_event_day = null;

    /**
     * Stack of collected events.
     * This array contains an array for each day that has at least one event or a
     * multiday instance of an event. During initialization, the array keys are strings
     * representing the date. This will be normalized to a simple indexed list at the end
     * of initialize_stack().
     *
     * @var array
     */
    private $event_stack = array();

    public function __construct( Comcal_Event_Iterator $event_iterator ) {
        $this->event_iterator = $event_iterator;
    }

    public function rewind() : void {
        $this->event_iterator->rewind();
        $this->initialize_stack();
    }

    private function initialize_stack() {
        $this->next_event_day = null;
        $this->event_stack    = array();

        // Collect events.
        foreach ( $this->event_iterator as $event ) {
            $this->add_event( $event );
        }

        // Normalize array:
        // 1. Convert associative array to simple indexed list.
        $this->event_stack = array_values( $this->event_stack );
        $count             = count( $this->event_stack );
        // 2. Sort each day: newer events above older events.
        for ( $i = 0; $i < $count; $i++ ) {
            usort( $this->event_stack[ $i ], 'static::event_sort_key' );
        }
        // Initialize first event.
        $this->next_event_day = $this->next_event_instance();
    }

    /**
     * Return current event.
     *
     * @return list( Comcal_Event $event, int $day )
     */
    public function current() {
        return $this->next_event_day;
    }

    public function key() {
        if ( null !== $this->next_event_day ) {
            return $this->next_event_day[0]->get_entry_id() . $this->next_event_day[1];
        }
        return null;
    }

    public function next() : void {
        $this->next_event_day = $this->next_event_instance();
    }

    public function valid() : bool {
        return null !== $this->next_event_day;
    }

    /**
     * Retrieve the next event from the event stack.
     *
     * @return list( Comcal_Event $event, int $day )
     */
    private function next_event_instance() {
        if ( empty( $this->event_stack ) ) {
            return null;
        }
        $event_day = array_shift( $this->event_stack[0] );
        if ( empty( $this->event_stack[0] ) ) {
            array_shift( $this->event_stack );
        }
        return $event_day;
    }

    /**
     * Fill stack with instances of this event for each day of the event.
     *
     * @param Comcal_Event $event The event.
     */
    private function add_event( Comcal_Event $event ) {
        $days = $event->get_number_of_days();
        for ( $day = 0; $day < $days; $day++ ) {
            $date_str = $event->get_start_date_time( $day )->get_date_str();
            if ( ! isset( $this->event_stack[ $date_str ] ) ) {
                $this->event_stack[ $date_str ] = array();
            }

            array_push( $this->event_stack[ $date_str ], array( $event, $day ) );
        }
    }

    /**
     * Key function for sorting events within a day array.
     *
     * @param list $event_day1 Event and Day: list( Comcal_Event $event, int $day ).
     * @param list $event_day2 Event and Day: list( Comcal_Event $event, int $day ).
     */
    private static function event_sort_key( $event_day1, $event_day2 ) {
        $date1 = $event_day1[0]->get_date_str();
        $date2 = $event_day2[0]->get_date_str();
        if ( $date1 === $date2 ) {
            $time1 = $event_day1[0]->get_time_str();
            $time2 = $event_day2[0]->get_time_str();
            if ( $time1 === $time2 ) {
                return 0;
            }
            return ( $time1 < $time2 ) ? -1 : 1;
        }
        return ( $date1 < $date2 ) ? 1 : -1;
    }
}
