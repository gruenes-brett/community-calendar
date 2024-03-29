<?php
/**
 * Functions for rendering the events to a WordPress page
 *
 * @package Community_Calendar
 */

/**
 * Base class for objects that output the calendar and events in different formats
 * (e.g., as HTML table, as Markdown, etc.)
 */
abstract class Comcal_Events_Display_Builder {

    /**
     * Declares style shorthands for DisplayBuilder classes.
     *
     * @var array
     */
    public static $styles = array(
        'table'    => 'Comcal_Table_Builder',
        'markdown' => 'Comcal_Markdown_Builder',
    );

    /**
     * Set to false in the concrete class to disable repeated event instances
     * for multi-day events.
     *
     * @var bool
     */
    protected static bool $is_multiday = true;

    public static function add_style( $name, $class_name ) {
        self::$styles[ $name ] = $class_name;
    }

    public static function create_display( $style_name, $events_iterator, $earliest_date = null, $latest_date = null ) {
        // Factory for display class instances.
        if ( isset( self::$styles[ $style_name ] ) ) {
            $clazz = self::$styles[ $style_name ];
        } elseif ( static::class === $style_name ) {
            $clazz = static::class;
        } else {
            $clazz = 'Comcal_Default_Display_Builder';
        }
        $builder = new $clazz( $earliest_date, $latest_date );

        if ( static::$is_multiday ) {
            $multiday_iterator = new Comcal_Multiday_Event_Iterator( $events_iterator );
            foreach ( $multiday_iterator as list($event, $day) ) {
                if ( static::is_event_visible( $event, $day, $earliest_date, $latest_date ) ) {
                    $builder->add_event( $event, $day );
                }
            }
        } else {
            foreach ( $events_iterator as $event ) {
                if ( static::is_event_visible( $event, 0, $earliest_date, $latest_date ) ) {
                    $builder->add_event( $event, 0 );
                }
            }
        }
        return $builder;
    }

    /**
     * Helper function to determine if an event instance should be rendered or not.
     *
     * @param Comcal_Event     $event Event to be checked.
     * @param int              $day Nth day of the event (starting at 0).
     * @param Comcal_Date_Time $earliest_date Start date of visible range or null for ignore.
     * @param Comcal_Date_Time $latest_date End date of visible range or null for ignore.
     * @return true, if the event is to be rendered.
     */
    protected static function is_event_visible(
        Comcal_Event $event,
        int $day,
        ?Comcal_Date_Time $earliest_date,
        ?Comcal_Date_Time $latest_date
    ) : bool {
        if ( $event->get_start_date_time( $day )->is_in_date_range( $earliest_date, $latest_date ) ) {
            return $event->get_field( 'joinDaily' ) || 0 === $day;
        }
        return false;
    }

    /**
     * Called by create_display() for each event that is loaded from the database.
     *
     * @param Comcal_Event $event Event that is to be rendered.
     * @param int          $day This is the n-th day instance of this event (starting at 0).
     *                     Check $event->get_number_of_days() for total amount of days.
     */
    abstract public function add_event( $event, int $day );

    /**
     * Called when the events are to be rendered to HTML.
     *
     * @return string
     */
    abstract public function get_html();

    protected function __construct( $earliest_date = null, $latest_date = null ) {
        $this->earliest_date = $earliest_date;
        $this->latest_date   = $latest_date;
        $this->current_date  = null;
    }

    /**
     * Show a full month when not starting at a specific date or
     * if this is not the first month?
     *
     * @return bool
     */
    public function show_full_month() {
        return null === $this->earliest_date || null !== $this->current_date;
    }
}


/**
 * Fallback builder if a wrong or non-existent output builder has been selected
 */
class Comcal_Default_Display_Builder extends Comcal_Events_Display_Builder {
    /**
     * Resulting HTML.
     *
     * @var string
     */
    protected $html = '';

    /**
     * Event renderer instance.
     *
     * @var Comcal_Event_Renderer
     */
    protected $event_renderer = null;

    protected function __construct( $earliest_date = null, $latest_date = null ) {
        parent::__construct( $earliest_date, $latest_date );
        $this->event_renderer = new Comcal_Default_Event_Renderer();
        $this->html           = '';
    }

    public function add_event( $event, int $day ) {
        $this->html .= $this->event_renderer->render( $event, $day );
    }

    public function get_html() {
        return $this->html;
    }
}

/**
 * Creates HTML tables for each month that contains at least one event
 */
class Comcal_Table_Builder extends Comcal_Default_Display_Builder {

    protected function __construct( $earliest_date = null, $latest_date = null ) {
        parent::__construct( $earliest_date, $latest_date );
        $this->event_renderer = new Comcal_Table_Event_Renderer();
    }

    public function get_html() {
        $this->finish_current_month();
        if ( empty( $this->html ) ) {
            return '<h2 class="month-title comcal-no-entries">Keine Einträge vorhanden</h2>';
        } else {
            return $this->html;
        }
    }

    /**
     * Returns HTML for the beginning of a month table.
     *
     * @param Comcal_Date_Time $date Date object for this month.
     *
     * @return string HTML.
     */
    protected function get_table_head( Comcal_Date_Time $date ) {
        $month_title = $date->get_month_title();
        return "<h3 class='month-title'>$month_title</h3>\n"
               . "<table class='community-calendar'><tbody>\n";
    }

    /**
     * Returns HTML for the end of a month table.
     *
     * @return string HTML.
     */
    protected function get_table_foot() {
        return "</tbody></table>\n";
    }

    protected function new_month( $date ) {
        $this->finish_current_month();
        $this->html .= $this->get_table_head( $date );
        if ( $this->show_full_month() ) {
            $this->fill_days_between( $date->get_first_of_month_date_time(), $date );
        }
    }

    protected function finish_current_month() {
        if ( null !== $this->current_date ) {
            if ( null === $this->latest_date || ! $this->latest_date->is_same_month( $this->current_date ) ) {
                // finish the whole month.
                $this->fill_days_after( $this->current_date );
            } elseif ( ! $this->current_date->is_same_day( $this->latest_date ) ) {
                // only finish until latest date.
                $this->fill_days_between( $this->current_date, $this->latest_date->get_next_day() );
            }
            $this->html .= $this->get_table_foot();
        }
    }

    protected function fill_days_between( $begin_at_date, $end_before_date ) {
        foreach ( $begin_at_date->get_all_dates_until( $end_before_date ) as $this_date ) {
            $this->create_day_row_internal( $this_date, '' );
        }
    }

    protected function fill_days_after( $date ) {
        foreach ( $date->get_next_day()->get_all_dates_until( $date->get_last_day_of_month() ) as $this_date ) {
            $this->create_day_row_internal( $this_date, '' );
        }
    }

    private function create_day_row_internal( $date_time, $text, $is_new_day = true ) {
        $this->create_day_row( $date_time, $text, $is_new_day );
        $this->current_date = $date_time;
    }

    /**
     * Override to specifiy how a day row is rendered. This will be called for every event instance
     * in a day.
     *
     * @param Comcal_Date_Time $date_time which day.
     * @param string           $text Content of the day.
     * @param bool             $is_new_day true if this is the first time the day is rendered.
     */
    protected function create_day_row( $date_time, $text, $is_new_day = true ) {
        $date_str    = $is_new_day ? $date_time->get_short_weekday_and_day() : '';
        $tr_class    = $is_new_day ? '' : 'sameDay';
        $date_class  = ( '' === $text ) ? 'has-no-events' : 'has-events';
        $this->html .= "<tr class='{$date_time->get_day_classes()} $tr_class day'>";
        $this->html .= "<td class='date $date_class'>$date_str</td>";
        $this->html .= "<td class='event'>$text</td></tr>\n";
    }

    public function add_event( $event, int $day ) {
        $event_instance_start = $event->get_start_date_time( $day );

        if ( null !== $this->earliest_date && null === $this->current_date
                 && ! $event_instance_start->is_same_day( $this->earliest_date ) ) {
            // Add an empty row for the earliest date.
            $this->new_month( $this->earliest_date );
            $this->create_day_row_internal( $this->earliest_date, '' );
        }
        if ( null === $this->current_date || ! $this->current_date->is_same_month( $event_instance_start ) ) {
            // New month.
            if ( null !== $this->current_date ) {
                while ( true ) {
                    // Fill in empty months.
                    $next_month = $this->current_date->get_last_day_of_month()->get_next_day();
                    if ( $next_month->is_same_month( $event_instance_start ) ) {
                        break;
                    }
                    $this->new_month( $next_month );
                }
            }
            // Create month with this event.
            $this->new_month( $event_instance_start );
        } elseif ( null !== $this->current_date ) {
            // Fill empty days between events.
            $this->fill_days_between( $this->current_date->get_next_day(), $event_instance_start );
        }
        $this->create_day_row_internal(
            $event_instance_start,
            $this->event_renderer->render( $event, $day ),
            ! $event_instance_start->is_same_day( $this->current_date )
        );
    }
}
