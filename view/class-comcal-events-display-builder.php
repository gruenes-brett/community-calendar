<?php
/**
 * Functions for rendering the events to a WordPress page
 *
 * @package Community_Calendar
 */

$comcal_calendar_already_shown = false;

/**
 * Shortcode [community-calendar-table]
 *
 * @param array $atts Shortcode attributes.
 * @return string Resulting HTML.
 */
function comcal_table_func( $atts ) {
    global $comcal_calendar_already_shown;
    if ( $comcal_calendar_already_shown ) {
        return comcal_make_error_box( 'Error: only a single calendar is allowed per page!' );
    }
    $a = shortcode_atts(
        array(
            'start'      => null,  // show events starting from ... 'today', 'next-monday', '2020-10-22', ...
            'name'       => '',
            'style'      => 'table',
            'days'       => null,  // number of days to show (excluding start day).
            'categories' => true,  // show category buttons.
        ),
        $atts
    );

    $calendar_name   = $a['name'];
    $style           = $a['style'];
    $days            = $a['days'];
    $start           = $a['start'];
    $show_categories = $a['categories'];

    // Determine category.
    $category = null;
    if ( isset( $_GET['comcal_category'] ) ) {
        $category = Comcal_Category::query_from_category_id( $_GET['comcal_category'] );
    }

    // Determine date range.
    $latest_date = null;
    $start_date  = null;
    if ( strtolower( $start ) === 'today' ) {
        $start_date = Comcal_Date_Time::now();
    } elseif ( strtolower( $start ) === 'next-monday' ) {
        $start_date = Comcal_Date_Time::next_monday();
    } elseif ( null !== $start ) {
        try {
            $start_date = Comcal_Date_Time::from_date_str_time_str( $start, '00:00:00' );
        } catch ( Exception $e ) {
            return comcal_make_error_box( "Error in 'start' attribute:<br>{$e->getMessage()}" );
        }
    }
    if ( null !== $days && null !== $start_date ) {
        $latest_date = $start_date->get_next_day( $days );
    }

    $events_iterator = new Comcal_Event_Iterator(
        $category,
        $calendar_name,
        $start_date ? $start_date->get_date_str() : null,
        $latest_date ? $latest_date->get_date_str() : null,
    );

    $output = Comcal_Events_Display_Builder::create_display( $style, $events_iterator, $start_date, $latest_date );

    $comcal_calendar_already_shown = true;

    $all_html = '';
    if ( $show_categories ) {
        $all_html .= comcal_get_category_buttons( $category );
    }
    $all_html .= $output->get_html() . Comcal_Basic_Event_Popup::get_popup_html() . comcal_get_edit_form( $calendar_name );
    if ( comcal_current_user_can_set_public() ) {
        $all_html .= comcal_get_edit_categories_dialog();
    }
    return $all_html;
}
add_shortcode( 'community-calendar-table', 'comcal_table_func' );


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
        'table' => 'Comcal_Table_Builder',
        'markdown' => 'Comcal_Markdown_Builder',
    );

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
        foreach ( $events_iterator as $event ) {
            $builder->add_event( $event );
        }
        return $builder;
    }

    /**
     * Called by create_display() for each event that is loaded from the database.
     *
     * @param Comcal_Event $event Event that is to be rendered.
     */
    abstract public function add_event( $event );

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

    public function add_event( $event ) {
        $this->html .= $this->event_renderer->render( $event );
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

    protected function new_month( $date ) {
        $this->finish_current_month();
        $this->html .= $this->get_table_head( $date );
        if ( $this->show_full_month() ) {
            $this->fill_days_between( $date->get_first_of_month_date_time(), $date );
        }
    }

    protected function finish_current_month() {
        if ( null !== $this->current_date ) {
            $this->fill_days_after( $this->current_date );
            $this->html .= "</tbody></table>\n";
        }
    }

    protected function fill_days_between( $begin_at_date, $end_before_date ) {
        foreach ( $begin_at_date->get_all_dates_until( $end_before_date ) as $this_date ) {
            $this->create_day_row( $this_date, '' );
        }
    }

    protected function fill_days_after( $date ) {
        foreach ( $date->get_next_day()->get_all_dates_until( $date->get_last_day_of_month() ) as $this_date ) {
            $this->create_day_row( $this_date, '' );
        }
    }

    protected function create_day_row( $date_time, $text, $is_new_day = true ) {
        $date_str           = $is_new_day ? $date_time->get_short_weekday_and_day() : '';
        $tr_class           = $is_new_day ? '' : 'sameDay';
        $date_class         = ( '' === $text ) ? 'has-no-events' : 'has-events';
        $this->html        .= "<tr class='{$date_time->get_day_classes()} $tr_class day'>";
        $this->html        .= "<td class='date $date_class'>$date_str</td>";
        $this->html        .= "<td class='event'>$text</td></tr>\n";
        $this->current_date = $date_time;
    }

    public function add_event( $event ) {
        if ( null !== $this->earliest_date && null === $this->current_date
                 && ! $event->get_start_date_time()->is_same_day( $this->earliest_date ) ) {
            // Add an empty row for the earliest date.
            $this->new_month( $this->earliest_date );
            $this->create_day_row( $this->earliest_date, '' );
        }
        if ( null === $this->current_date || ! $this->current_date->is_same_month( $event->get_start_date_time() ) ) {
            // New month.
            if ( null !== $this->current_date ) {
                while ( true ) {
                    // Fill in empty months.
                    $next_month = $this->current_date->get_last_day_of_month()->get_next_day();
                    if ( $next_month->is_same_month( $event->get_start_date_time() ) ) {
                        break;
                    }
                    $this->new_month( $next_month );
                }
            }
            // Create month with this event.
            $this->new_month( $event->get_start_date_time() );
        } elseif ( null !== $this->current_date ) {
            // Fill empty days between events.
            $this->fill_days_between( $this->current_date->get_next_day(), $event->get_start_date_time() );
        }
        $this->create_day_row(
            $event->get_start_date_time(),
            $this->event_renderer->render( $event ),
            ! $event->get_start_date_time()->is_same_day( $this->current_date )
        );
    }
}


/**
 * Creates a Markdown overview of all events in the next week (starting monday)
 */
class Comcal_Markdown_Builder extends Comcal_Default_Display_Builder {

    public function __construct( $earliest_date = null, $latest_date = null ) {
        parent::__construct( $earliest_date, $latest_date );
        $this->event_renderer = new Comcal_Markdown_Event_Renderer();
    }

    public function get_html() {
        if ( null !== $this->earliest_date ) {
            $pretty_start = $this->earliest_date->format( 'd.m.' );
        } else {
            $pretty_start = '??.??.';
        }
        if ( null !== $this->latest_date ) {
            $pretty_end = $this->latest_date->format( 'd.m.' );
        } else {
            $pretty_end = '??.??.';
        }
        $header = "🗓 **Woche vom $pretty_start bis $pretty_end:**

Hallo liebe Leser*innen von @input_dd, hier die Veranstaltungsempfehlungen der kommenden Woche!
Let's GO! 🌿🌳/ 🌎 Klima-, Naturschutz & Nachhaltigkeit 🌱

";

        if ( null !== $this->latest_date && null !== $this->current_date ) {
            $this->fill_days_between( $this->current_date->get_next_day(), $this->latest_date->get_next_day() );
        }
        $result = '<input id="comcal-copy-markdown" type="button" class="btn btn-primary" value="Copy to clipboard"/><br>';
        $result .= '<textarea id="comcal-markdown" style="width: 100%; height: 80vh;">' . $header . $this->html .
        'Achtet auf Veranstaltungen bitte auf eure Mitmenschen u. haltet euch an die Hygiene- und Abstandsregeln!
**Allen eine schöne Woche!** 😁'
        . '</textarea>';
        return $result;
    }
    protected function fill_days_between( $begin_at_date, $end_before_date ) {
        foreach ( $begin_at_date->get_all_dates_until( $end_before_date ) as $this_date ) {
            $this->html .= $this->create_new_day( $this_date ) . '(bis jetzt leider nichts)

';
        }
    }

    public function add_event( $event ) {
        if ( null === $this->current_date && null !== $this->earliest_date ) {
            $this->fill_days_between( $this->earliest_date, $event->get_start_date_time() );
        } elseif ( null !== $this->current_date ) {
            $this->fill_days_between( $this->current_date->get_next_day(), $event->get_start_date_time() );
        }
        if ( null === $this->current_date || ! $this->current_date->is_same_day( $event->get_start_date_time() ) ) {
            $this->current_date = $event->get_start_date_time();
            $this->html        .= $this->create_new_day( $this->current_date );
        }
        $this->html .= $this->event_renderer->render( $event ) . '

';
        $this->current_date = $event->get_start_date_time();
    }

    private function create_new_day( $date_time ) {
        return '🕑 **' . $date_time->get_humanized_date() . '**

';
    }
}
