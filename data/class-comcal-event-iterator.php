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
     * @param Comcal_Category  $category Only a certain category.
     * @param string           $calendar_name Name of the calendar.
     * @param Comcal_Date_Time $start_date Range start - null for all.
     * @param Comcal_Date_Time $end_date Range end - null for all.
     *
     * @return array Database query result.
     */
    public static function load_from_database(
        $category = null,
        $calendar_name = '',
        $start_date = null,
        $end_date = null
    ) {
        $event_rows = comcal_query_events(
            $category,
            $calendar_name,
            $start_date,
            $end_date
        );
        return new static( $event_rows );
    }

    public function __construct( $event_rows ) {
        $this->event_rows = $event_rows;
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
 * Iterator that wraps around Comcal_Event_Iterator and that repeats multi-day events.
 */
class Comcal_Multiday_Event_Iterator implements Iterator {
    /**
     * The original event iterator.
     *
     * @var Comcal_Event_Iterator
     */
    private $event_iterator = null;

    private $next = null;

    /**
     * What is the currently returned date? Necessary for detecting date changes for inserting mutli-day instances of events.
     *
     * @var Comcal_Date_Time
     */
    private $current_date = null;

    /**
     * Stack of current events: array( date string => [event1, event2, ....] ).
     *
     * @var array
     */
    private $current_events = array();

    public function __construct( Comcal_Event_Iterator $event_iterator ) {
        $this->event_iterator = $event_iterator;
        $this->current_date   = null;
    }

    public function rewind() {
        $this->event_iterator->rewind();
        $this->current_date    = null;
        $this->multiday_events = array();
        $this->next = null;
    }

    public function current() {
        return array( $this->event_iterator->current(), 0 );
    }

    public function key() {
        return $this->event_iterator->key();
    }

    public function next() {
        $this->event_iterator->next();
    }

    public function valid() {
        return $this->event_iterator->valid();
    }
}
