<?php

/**
 * Functions for rendering the events to a Wordpress page
 */

$comcal_calendarAlreadyShown = false;

/**
 * Shortcode [community-calendar-table]
 */
function comcal_table_func( $atts ) {
    global $comcal_calendarAlreadyShown;
    if ($comcal_calendarAlreadyShown) {
        return comcal_makeErrorBox('Error: only a single calendar is allowed per page!');
    }
	$a = shortcode_atts( array(
        'start' => null,  // show events starting from ... 'today', 'next-monday', '2020-10-22', ...
        'name' => '',
        'style' => 'table',
        'days' => null,  // number of days to show (excluding start day)
        'categories' => true,  // show category buttons
    ), $atts );

    $calendarName = $a['name'];
    $style = $a['style'];
    $days = $a['days'];
    $start = $a['start'];
    $showCategories = $a['categories'];

    // determine category
    $category = null;
    if (isset($_GET['comcal_category'])) {
        $category = comcal_Category::queryFromCategoryId($_GET['comcal_category']);
    }

    // determine date range
    $latest_date = null;
    $startDate = null;
    if (strtolower($start) === 'today') {
        $startDate = comcal_DateTime::now();
    } else if (strtolower($start) === 'next-monday') {
        $startDate = comcal_DateTime::nextMonday();
    } else if ($start !== null) {
        try {
            $startDate = comcal_DateTime::fromDateStrTimeStr($start, '00:00:00');
        } catch (Exception $e) {
            return comcal_makeErrorBox("Error in 'start' attribute:<br>{$e->getMessage()}");
        }
    }
    if ($days !== null && $startDate !== null) {
        $latest_date = $startDate->getNextDay($days);
    }

    $isAdmin = comcal_currentUserCanSetPublic();
    $eventsIterator = new comcal_EventIterator(
        !$isAdmin,
        $category,
        $calendarName,
        $startDate ? $startDate->getDateStr() : null,
        $latest_date ? $latest_date->getDateStr() : null
    );

    $output = comcal_EventsDisplayBuilder::create_display($style, $eventsIterator, $startDate, $latest_date);

    $comcal_calendarAlreadyShown = true;

    $allHtml = '';
    if ($showCategories) {
        $allHtml .= comcal_getCategoryButtons($category);
    }
    $allHtml .= $output->get_html() . comcal_getShowEventBox() . comcal_getEditForm($calendarName);
    if (comcal_currentUserCanSetPublic()) {
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

    static function add_style($name, $className) {
        comcal_EventsDisplayBuilder::$styles[$name] = $className;
    }

    static function create_display($styleName, $eventsIterator, $earliest_date=null, $latest_date=null) {
        // Factory for display class instances
        if (isset(self::$styles[$styleName])) {
            $clazz = self::$styles[$styleName];
        } elseif ( static::class === $styleName ) {
            $clazz = static::class;
        } else {
            $clazz = 'comcal_DefaultDisplayBuilder';
        }
        $builder = new $clazz($earliest_date, $latest_date);
        foreach ($eventsIterator as $event) {
            // $event->addCategory($ccc);
            $builder->add_event($event);
        }
        return $builder;
    }

    abstract function add_event($event);
    abstract function get_html();

    function __construct($earliest_date=null, $latest_date=null) {
        $this->earliest_date = $earliest_date;
        $this->latest_date = $latest_date;
        $this->current_date = null;
    }

    function show_full_month() {
        /* show a full month when not starting at a specific date or
        if this is not the first month */
        return $this->earliest_date === null || $this->current_date !== null;
    }
}


/**
 * Fallback builder if a wrong or non-existent output builder has been selected
 */
class comcal_DefaultDisplayBuilder {
    function add_event($event) {
    }
    function get_html() {
        return '<h3>specified display style not available!</h3>';
    }

}


/**
 * Creates HTML tables for each month that contains at least one event
 */
class comcal_TableBuilder extends comcal_EventsDisplayBuilder {
    var $html = '';
    var $event_renderer = null;

    function __construct($earliest_date=null, $latest_date=null) {
        parent::__construct($earliest_date, $latest_date);
        $this->event_renderer = new comcal_TableEventRenderer();
    }

    function get_html() {
        $this->finish_current_month();
        if (empty($this->html)) {
            return '<h2 class="month-title comcal-no-entries">Keine Einträge vorhanden</h2>';
        } else {
            return $this->html;
        }
    }

    /**
     * Returns HTML for the beginning of a month table.
     *
     * @param comcal_DateTime $date Date object for this month.
     *
     * @return string HTML.
     */
    protected function get_table_head( comcal_DateTime $date ) {
        $month_title = $date->get_month_title();
        return "<h3 class='month-title'>$month_title</h3>\n"
               . "<table class='community-calendar'><tbody>\n";
    }

    protected function new_month($date) {
        $this->finish_current_month();
        $this->html .= $this->get_table_head($date);
        if ($this->show_full_month()) {
            $this->fill_days_between($date->getFirstOfMonthDateTime(), $date);
        }
    }

    protected function finish_current_month() {
        if ($this->current_date !== null) {
            $this->fill_days_after($this->current_date);
            $this->html .= "</tbody></table>\n";
        }
    }

    protected function fill_days_between($beginAtDate, $endBeforeDate) {
        foreach ($beginAtDate->getAllDatesUntil($endBeforeDate) as $thisDay) {
            $this->create_day_row($thisDay, '');
        }
    }

    protected function fill_days_after($date) {
        foreach ($date->getNextDay()->getAllDatesUntil($date->getLastDayOfMonth()) as $thisDay) {
            $this->create_day_row($thisDay, '');
        }
    }

    protected function create_day_row($dateTime, $text, $isNewDay=true) {
        $dateStr = $isNewDay ? $dateTime->getShortWeekdayAndDay() : '';
        $trClass = $isNewDay ? '' : 'sameDay';
        $dateClass = ($text==='') ? 'has-no-events' : 'has-events';
        $this->html .= "<tr class='{$dateTime->getDayClasses()} $trClass day'>";
        $this->html .= "<td class='date $dateClass'>$dateStr</td>";
        $this->html .= "<td class='event'>$text</td></tr>\n";
        $this->current_date = $dateTime;
    }

    function add_event($event) {
        if ($this->earliest_date !== null && $this->current_date === null
                                         && !$event->getDateTime()->isSameDay($this->earliest_date)) {
            // add an empty row for the earliest date
            $this->new_month($this->earliest_date);
            $this->create_day_row($this->earliest_date, '');
        }
        if ($this->current_date === null || ! $this->current_date->isSameMonth($event->getDateTime())) {
            // new month
            if ($this->current_date !== null) {
                while (true) {
                    // fill in empty months
                    $next_month = $this->current_date->getLastDayOfMonth()->getNextDay();
                    if ($next_month->isSameMonth($event->getDateTime())) {
                        break;
                    }
                    $this->new_month($next_month);
                }
            }
            // create month with this event
            $this->new_month($event->getDateTime());
        } else if ($this->current_date !== null) {
            // fill empty days between events
            $this->fill_days_between($this->current_date->getNextDay(), $event->getDateTime());
        }
        $this->create_day_row(
            $event->getDateTime(),
            $this->event_renderer->render($event),
            !$event->getDateTime()->isSameDay($this->current_date)
        );
    }
}


/**
 * Creates a Markdown overview of all events in the next week (starting monday)
 */
class comcal_MarkdownBuilder extends comcal_EventsDisplayBuilder {
    var $html = '';
    var $event_renderer = null;

    function __construct($earliest_date=null, $latest_date=null) {
        parent::__construct($earliest_date, $latest_date);
        $this->event_renderer = new comcal_MarkdownEventRenderer();
    }

    function get_html() {
        if ($this->earliest_date !== null) {
            $pretty_start = $this->earliest_date->format('d.m.');
        } else {
            $pretty_start = '??.??.';
        }
        if ($this->latest_date !== null) {
            $pretty_end = $this->latest_date->format('d.m.');
        } else {
            $pretty_end = '??.??.';
        }
        $header = "🗓 **Woche vom $pretty_start bis $pretty_end:**

Hallo liebe Leser*innen von @input_dd, hier die Veranstaltungsempfehlungen der kommenden Woche!
Let's GO! 🌿🌳/ 🌎 Klima-, Naturschutz & Nachhaltigkeit 🌱

";

        if ($this->latest_date !== null && $this->current_date !== null) {
            $this->fill_days_between($this->current_date->getNextDay(), $this->latest_date->getNextDay());
        }
        $result = '<input id="comcal-copy-markdown" type="button" class="btn btn-primary" value="Copy to clipboard"/><br>';
        $result .= '<textarea id="comcal-markdown" style="width: 100%; height: 80vh;">' . $header . $this->html .
        'Achtet auf Veranstaltungen bitte auf eure Mitmenschen u. haltet euch an die Hygiene- und Abstandsregeln!
**Allen eine schöne Woche!** 😁'
        . '</textarea>';
        return $result;
    }
    protected function fill_days_between($beginAtDate, $endBeforeDate) {
        foreach ($beginAtDate->getAllDatesUntil($endBeforeDate) as $thisDay) {
            $this->html .= $this->create_new_day($thisDay) . '(bis jetzt leider nichts)

';
        }
    }
    function add_event($event) {
        if ($this->current_date === null && $this->earliest_date !== null) {
            $this->fill_days_between($this->earliest_date, $event->getDateTime());
        } else if ($this->current_date !== null) {
            $this->fill_days_between($this->current_date->getNextDay(), $event->getDateTime());
        }
        if ($this->current_date === null || !$this->current_date->isSameDay($event->getDateTime())) {
            $this->current_date = $event->getDateTime();
            $this->html .= $this->create_new_day($this->current_date);
        }
        $this->html .= $this->event_renderer->render($event) . '

';
        $this->current_date = $event->getDateTime();
    }

    function create_new_day($dateTime) {
        return '🕑 **' . $dateTime->getHumanizedDate() . '**

';
    }
}