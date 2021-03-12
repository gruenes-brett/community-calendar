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
    $latestDate = null;
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
        $latestDate = $startDate->getNextDay($days);
    }

    $isAdmin = comcal_currentUserCanSetPublic();
    $eventsIterator = new comcal_EventIterator(
        !$isAdmin,
        $category,
        $calendarName,
        $startDate ? $startDate->getDateStr() : null,
        $latestDate ? $latestDate->getDateStr() : null
    );

    $output = comcal_EventsDisplayBuilder::createDisplay($style, $eventsIterator, $startDate, $latestDate);

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

    static function createDisplay($styleName, $eventsIterator, $earliestDate=null, $latestDate=null) {
        // Factory for display class instances
        if (isset(self::$styles[$styleName])) {
            $clazz = self::$styles[$styleName];
        } else {
            $clazz = 'comcal_DefaultDisplayBuilder';
        }
        $builder = new $clazz($earliestDate, $latestDate);
        foreach ($eventsIterator as $event) {
            // $event->addCategory($ccc);
            $builder->addEvent($event);
        }
        return $builder;
    }

    abstract function addEvent($event);
    abstract function get_html();

    function __construct($earliestDate=null, $latestDate=null) {
        $this->earliestDate = $earliestDate;
        $this->latestDate = $latestDate;
        $this->current_date = null;
    }

    function showFullMonth() {
        /* show a full month when not starting at a specific date or
        if this is not the first month */
        return $this->earliestDate === null || $this->current_date !== null;
    }
}


/**
 * Fallback builder if a wrong or non-existent output builder has been selected
 */
class comcal_DefaultDisplayBuilder {
    function addEvent($event) {
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

    function __construct($earliestDate=null, $latestDate=null) {
        parent::__construct($earliestDate, $latestDate);
        $this->event_renderer = new comcal_TableEventRenderer();
    }

    function get_html() {
        $this->finishCurrentMonth();
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

    protected function newMonth($date) {
        $this->finishCurrentMonth();
        $this->html .= $this->get_table_head($date);
        if ($this->showFullMonth()) {
            $this->fillDaysBetween($date->getFirstOfMonthDateTime(), $date);
        }
    }

    protected function finishCurrentMonth() {
        if ($this->current_date !== null) {
            $this->fillDaysAfter($this->current_date);
            $this->html .= "</tbody></table>\n";
        }
    }

    protected function fillDaysBetween($beginAtDate, $endBeforeDate) {
        foreach ($beginAtDate->getAllDatesUntil($endBeforeDate) as $thisDay) {
            $this->create_day_row($thisDay, '');
        }
    }

    protected function fillDaysAfter($date) {
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

    function addEvent($event) {
        if ($this->earliestDate !== null && $this->current_date === null
                                         && !$event->getDateTime()->isSameDay($this->earliestDate)) {
            // add an empty row for the earliest date
            $this->newMonth($this->earliestDate);
            $this->create_day_row($this->earliestDate, '');
        }
        if ($this->current_date === null || ! $this->current_date->isSameMonth($event->getDateTime())) {
            // new month
            if ($this->current_date !== null) {
                while (true) {
                    // fill in empty months
                    $nextMonth = $this->current_date->getLastDayOfMonth()->getNextDay();
                    if ($nextMonth->isSameMonth($event->getDateTime())) {
                        break;
                    }
                    $this->newMonth($nextMonth);
                }
            }
            // create month with this event
            $this->newMonth($event->getDateTime());
        } else if ($this->current_date !== null) {
            // fill empty days between events
            $this->fillDaysBetween($this->current_date->getNextDay(), $event->getDateTime());
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

    function __construct($earliestDate=null, $latestDate=null) {
        parent::__construct($earliestDate, $latestDate);
        $this->event_renderer = new comcal_MarkdownEventRenderer();
    }

    function get_html() {
        if ($this->earliestDate !== null) {
            $prettyStart = $this->earliestDate->format('d.m.');
        } else {
            $prettyStart = '??.??.';
        }
        if ($this->latestDate !== null) {
            $prettyEnd = $this->latestDate->format('d.m.');
        } else {
            $prettyEnd = '??.??.';
        }
        $header = "🗓 **Woche vom $prettyStart bis $prettyEnd:**

Hallo liebe Leser*innen von @input_dd, hier die Veranstaltungsempfehlungen der kommenden Woche!
Let's GO! 🌿🌳/ 🌎 Klima-, Naturschutz & Nachhaltigkeit 🌱

";

        if ($this->latestDate !== null && $this->current_date !== null) {
            $this->fillDaysBetween($this->current_date->getNextDay(), $this->latestDate->getNextDay());
        }
        $result = '<input id="comcal-copy-markdown" type="button" class="btn btn-primary" value="Copy to clipboard"/><br>';
        $result .= '<textarea id="comcal-markdown" style="width: 100%; height: 80vh;">' . $header . $this->html .
        'Achtet auf Veranstaltungen bitte auf eure Mitmenschen u. haltet euch an die Hygiene- und Abstandsregeln!
**Allen eine schöne Woche!** 😁'
        . '</textarea>';
        return $result;
    }
    protected function fillDaysBetween($beginAtDate, $endBeforeDate) {
        foreach ($beginAtDate->getAllDatesUntil($endBeforeDate) as $thisDay) {
            $this->html .= $this->createNewDay($thisDay) . '(bis jetzt leider nichts)

';
        }
    }
    function addEvent($event) {
        if ($this->current_date === null && $this->earliestDate !== null) {
            $this->fillDaysBetween($this->earliestDate, $event->getDateTime());
        } else if ($this->current_date !== null) {
            $this->fillDaysBetween($this->current_date->getNextDay(), $event->getDateTime());
        }
        if ($this->current_date === null || !$this->current_date->isSameDay($event->getDateTime())) {
            $this->current_date = $event->getDateTime();
            $this->html .= $this->createNewDay($this->current_date);
        }
        $this->html .= $this->event_renderer->render($event) . '

';
        $this->current_date = $event->getDateTime();
    }

    function createNewDay($dateTime) {
        return '🕑 **' . $dateTime->getHumanizedDate() . '**

';
    }
}