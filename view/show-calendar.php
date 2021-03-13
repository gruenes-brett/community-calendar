<?php

/**
 * Functions for rendering the events to a WordPress page
 */

$comcal_calendar_already_shown = false;

/**
 * Shortcode [community-calendar-table]
 */
function comcal_table_func( $atts ) {
    global $comcal_calendar_already_shown;
    if ( $comcal_calendar_already_shown ) {
        return comcal_makeErrorBox( 'Error: only a single calendar is allowed per page!' );
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
        $category = comcal_Category::query_from_category_id( $_GET['comcal_category'] );
    }

    // determine date range
    $latest_date = null;
    $startDate = null;
    if ( strtolower( $start ) === 'today' ) {
        $startDate = comcal_DateTimeWrapper::now();
    } else if ( strtolower( $start ) === 'next-monday' ) {
        $startDate = comcal_DateTimeWrapper::next_monday();
    } else if ( $start !== null ) {
        try {
            $startDate = comcal_DateTimeWrapper::from_date_str_time_str( $start, '00:00:00' );
        } catch ( Exception $e ) {
            return comcal_makeErrorBox( "Error in 'start' attribute:<br>{$e->getMessage()}" );
        }
    }
    if ( $days !== null && $startDate !== null ) {
        $latest_date = $startDate->get_next_day( $days );
    }

    $isAdmin = comcal_currentUserCanSetPublic();
    $eventsIterator = new comcal_EventIterator(
        ! $isAdmin,
        $category,
        $calendar_name,
        $startDate->get_date_str() ?? null,
        $latest_date->get_date_str() ?? null,
    );

    $output = comcal_EventsDisplayBuilder::create_display( $style, $eventsIterator, $startDate, $latest_date );

    $comcal_calendar_already_shown = true;

    $allHtml = '';
    if ( $show_categories ) {
        $allHtml .= comcal_getCategoryButtons( $category );
    }
    $allHtml .= $output->get_html() . comcal_get_show_event_box() . comcal_getEditForm( $calendar_name );
    if ( comcal_currentUserCanSetPublic() ) {
        $allHtml .= comcal_getEditCategoriesDialog();
    }
    return $allHtml;
}
add_shortcode( 'community-calendar-table', 'comcal_table_func' );


/**
 * Base class for objects that output the calendar and events in different formats
 * (e.g., as HTML table, as Markdown, etc.)
 */
abstract class comcal_EventsDisplayBuilder {
    static $styles = array(
        'table' => 'comcal_TableBuilder',
        'markdown' => 'comcal_MarkdownBuilder',
    );

    static function add_style( $name, $className ) {
        self::$styles[ $name ] = $className;
    }

    static function create_display( $styleName, $eventsIterator, $earliest_date = null, $latest_date = null ) {
        // Factory for display class instances
        if ( isset( self::$styles[ $styleName ] ) ) {
            $clazz = self::$styles[ $styleName ];
        } elseif ( static::class === $styleName ) {
            $clazz = static::class;
        } else {
            $clazz = 'comcal_DefaultDisplayBuilder';
        }
        $builder = new $clazz( $earliest_date, $latest_date );
        foreach ( $eventsIterator as $event ) {
            // $event->addCategory($ccc);
            $builder->add_event( $event );
        }
        return $builder;
    }

    abstract public function add_event( $event );
    abstract public function get_html();

    function __construct( $earliest_date = null, $latest_date = null ) {
        $this->earliest_date = $earliest_date;
        $this->latest_date = $latest_date;
        $this->current_date = null;
    }

    function show_full_month() {
        /*
         show a full month when not starting at a specific date or
        if this is not the first month */
        return $this->earliest_date === null || $this->current_date !== null;
    }
}


/**
 * Fallback builder if a wrong or non-existent output builder has been selected
 */
class comcal_DefaultDisplayBuilder extends comcal_EventsDisplayBuilder {
    var $html = '';
    var $event_renderer = null;

    function __construct( $earliest_date = null, $latest_date = null ) {
        parent::__construct( $earliest_date, $latest_date );
        $this->event_renderer = new comcal_DefaultEventRenderer();
        $this->html = '';
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
class comcal_TableBuilder extends comcal_DefaultDisplayBuilder {

    function __construct( $earliest_date = null, $latest_date = null ) {
        parent::__construct( $earliest_date, $latest_date );
        $this->event_renderer = new comcal_TableEventRenderer();
    }

    function get_html() {
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
     * @param comcal_DateTimeWrapper $date Date object for this month.
     *
     * @return string HTML.
     */
    protected function get_table_head( comcal_DateTimeWrapper $date ) {
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
        if ( $this->current_date !== null ) {
            $this->fill_days_after( $this->current_date );
            $this->html .= "</tbody></table>\n";
        }
    }

    protected function fill_days_between( $beginAtDate, $endBeforeDate ) {
        foreach ( $beginAtDate->get_all_dates_until( $endBeforeDate ) as $thisDay ) {
            $this->create_day_row( $thisDay, '' );
        }
    }

    protected function fill_days_after( $date ) {
        foreach ( $date->get_next_day()->get_all_dates_until( $date->get_last_day_of_month() ) as $thisDay ) {
            $this->create_day_row( $thisDay, '' );
        }
    }

    protected function create_day_row( $date_time, $text, $isNewDay = true ) {
        $date_str = $isNewDay ? $date_time->get_short_weekday_and_day() : '';
        $trClass = $isNewDay ? '' : 'sameDay';
        $dateClass = ( $text === '' ) ? 'has-no-events' : 'has-events';
        $this->html .= "<tr class='{$date_time->get_day_classes()} $trClass day'>";
        $this->html .= "<td class='date $dateClass'>$date_str</td>";
        $this->html .= "<td class='event'>$text</td></tr>\n";
        $this->current_date = $date_time;
    }

    function add_event( $event ) {
        if ( $this->earliest_date !== null && $this->current_date === null
                                         && ! $event->get_date_time()->is_same_day( $this->earliest_date ) ) {
            // add an empty row for the earliest date
            $this->new_month( $this->earliest_date );
            $this->create_day_row( $this->earliest_date, '' );
        }
        if ( $this->current_date === null || ! $this->current_date->is_same_month( $event->get_date_time() ) ) {
            // new month
            if ( $this->current_date !== null ) {
                while ( true ) {
                    // fill in empty months
                    $next_month = $this->current_date->get_last_day_of_month()->get_next_day();
                    if ( $next_month->is_same_month( $event->get_date_time() ) ) {
                        break;
                    }
                    $this->new_month( $next_month );
                }
            }
            // create month with this event
            $this->new_month( $event->get_date_time() );
        } else if ( $this->current_date !== null ) {
            // fill empty days between events
            $this->fill_days_between( $this->current_date->get_next_day(), $event->get_date_time() );
        }
        $this->create_day_row(
            $event->get_date_time(),
            $this->event_renderer->render( $event ),
            ! $event->get_date_time()->is_same_day( $this->current_date )
        );
    }
}


/**
 * Creates a Markdown overview of all events in the next week (starting monday)
 */
class comcal_MarkdownBuilder extends comcal_DefaultDisplayBuilder {
    var $html = '';
    var $event_renderer = null;

    function __construct( $earliest_date = null, $latest_date = null ) {
        parent::__construct( $earliest_date, $latest_date );
        $this->event_renderer = new comcal_MarkdownEventRenderer();
    }

    function get_html() {
        if ( $this->earliest_date !== null ) {
            $pretty_start = $this->earliest_date->format( 'd.m.' );
        } else {
            $pretty_start = '??.??.';
        }
        if ( $this->latest_date !== null ) {
            $pretty_end = $this->latest_date->format( 'd.m.' );
        } else {
            $pretty_end = '??.??.';
        }
        $header = "🗓 **Woche vom $pretty_start bis $pretty_end:**

Hallo liebe Leser*innen von @input_dd, hier die Veranstaltungsempfehlungen der kommenden Woche!
Let's GO! 🌿🌳/ 🌎 Klima-, Naturschutz & Nachhaltigkeit 🌱

";

        if ( $this->latest_date !== null && $this->current_date !== null ) {
            $this->fill_days_between( $this->current_date->get_next_day(), $this->latest_date->get_next_day() );
        }
        $result = '<input id="comcal-copy-markdown" type="button" class="btn btn-primary" value="Copy to clipboard"/><br>';
        $result .= '<textarea id="comcal-markdown" style="width: 100%; height: 80vh;">' . $header . $this->html .
        'Achtet auf Veranstaltungen bitte auf eure Mitmenschen u. haltet euch an die Hygiene- und Abstandsregeln!
**Allen eine schöne Woche!** 😁'
        . '</textarea>';
        return $result;
    }
    protected function fill_days_between( $beginAtDate, $endBeforeDate ) {
        foreach ( $beginAtDate->get_all_dates_until( $endBeforeDate ) as $thisDay ) {
            $this->html .= $this->create_new_day( $thisDay ) . '(bis jetzt leider nichts)

';
        }
    }
    function add_event( $event ) {
        if ( $this->current_date === null && $this->earliest_date !== null ) {
            $this->fill_days_between( $this->earliest_date, $event->get_date_time() );
        } else if ( $this->current_date !== null ) {
            $this->fill_days_between( $this->current_date->get_next_day(), $event->get_date_time() );
        }
        if ( $this->current_date === null || ! $this->current_date->is_same_day( $event->get_date_time() ) ) {
            $this->current_date = $event->get_date_time();
            $this->html .= $this->create_new_day( $this->current_date );
        }
        $this->html .= $this->event_renderer->render( $event ) . '

';
        $this->current_date = $event->get_date_time();
    }

    function create_new_day( $date_time ) {
        return '🕑 **' . $date_time->get_humanized_date() . '**

';
    }
}
