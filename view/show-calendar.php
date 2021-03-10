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

    $allHtml = $output->getHtml() . comcal_getShowEventBox() . comcal_getEditForm($calendarName);
    if (comcal_currentUserCanSetPublic()) {
        $allHtml .= comcal_getEditCategoriesDialog();
    }
    if ($showCategories) {
        $allHtml = comcal_getCategoryButtons($category) . $allHtml;
    }
    return $allHtml;
}
add_shortcode( 'community-calendar-table', 'comcal_table_func' );


/**
 * Base class for objects that output the calendar and events in different formats
 * (e.g., as HTML table, as Markdown, etc.)
 */
abstract class comcal_EventsDisplayBuilder {
    // var $earliestDate;
    // var $latestDate;
    // var $currentDate;
    static function createDisplay($styleName, $eventsIterator, $earliestDate=null, $latestDate=null) {
        // Factory for display class instances
        $styles = array(
            'table' => 'comcal_TableBuilder',
            'markdown' => 'comcal_MarkdownBuilder',
        );
        if (isset($styles[$styleName])) {
            $clazz = $styles[$styleName];
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
    abstract function getHtml();

    function __construct($earliestDate=null, $latestDate=null) {
        $this->earliestDate = $earliestDate;
        $this->latestDate = $latestDate;
        $this->currentDate = null;
    }

    function showFullMonth() {
        /* show a full month when not starting at a specific date or
        if this is not the first month */
        return $this->earliestDate === null || $this->currentDate !== null;
    }
}


/**
 * Fallback builder if a wrong or non-existent output builder has been selected
 */
class comcal_DefaultDisplayBuilder {
    function addEvent($event) {
    }
    function getHtml() {
        return '<h3>specified display style not available!</h3>';
    }

}


/**
 * Creates HTML tables for each month that contains at least one event
 */
class comcal_TableBuilder extends comcal_EventsDisplayBuilder {
    var $html = '';

    function getHtml() {
        $this->finishCurrentMonth();
        if (empty($this->html)) {
            return '<h3 class="month-title comcal-no-entries">Keine Einträge vorhanden</h3>';
        } else {
            return $this->html;
        }
    }

    protected function newMonth($date) {
        $this->finishCurrentMonth();
        $this->html .= "<h3 class='month-title'>" . $date->getMonthTitle() . "</h3>\n"
            . "<table class='community-calendar'><tbody>\n";
        if ($this->showFullMonth()) {
            $this->fillDaysBetween($date->getFirstOfMonthDateTime(), $date);
        }    
    }

    protected function finishCurrentMonth() {
        if ($this->currentDate !== null) {
            $this->fillDaysAfter($this->currentDate);
            $this->html .= "</tbody></table>\n";
        }
    }

    protected function fillDaysBetween($beginAtDate, $endBeforeDate) {
        foreach ($beginAtDate->getAllDatesUntil($endBeforeDate) as $thisDay) {
            $this->createDayRow($thisDay, '');
        }
    }

    protected function fillDaysAfter($date) {
        foreach ($date->getNextDay()->getAllDatesUntil($date->getLastDayOfMonth()) as $thisDay) {
            $this->createDayRow($thisDay, '');
        }
    }

    protected function createDayRow($dateTime, $text, $isNewDay=true) {
        $dateStr = $isNewDay ? $dateTime->getShortWeekdayAndDay() : '';
        $trClass = $isNewDay ? '' : 'sameDay';
        $dateClass = ($text==='') ? 'has-no-events' : 'has-events';
        $this->html .= "<tr class='{$dateTime->getDayClasses()} $trClass day'>";
        $this->html .= "<td class='date $dateClass'>$dateStr</td>";
        $this->html .= "<td class='event'>$text</td></tr>\n";
        $this->currentDate = $dateTime;
    }

    function addEvent($event) {
        if ($this->earliestDate !== null && $this->currentDate === null 
                                         && !$event->getDateTime()->isSameDay($this->earliestDate)) {
            // add an empty row for the earliest date
            $this->newMonth($this->earliestDate);
            $this->createDayRow($this->earliestDate, '');
        }
        if ($this->currentDate === null || ! $this->currentDate->isSameMonth($event->getDateTime())) {
            // new month
            if ($this->currentDate !== null) {
                while (true) {
                    // fill in empty months
                    $nextMonth = $this->currentDate->getLastDayOfMonth()->getNextDay();
                    if ($nextMonth->isSameMonth($event->getDateTime())) {
                        break;
                    }
                    $this->newMonth($nextMonth);
                }
            }
            // create month with this event
            $this->newMonth($event->getDateTime());
        } else if ($this->currentDate !== null) {
            // fill empty days between events
            $this->fillDaysBetween($this->currentDate->getNextDay(), $event->getDateTime());
        }
        $this->createDayRow(
            $event->getDateTime(),
            $event->getHtml(),
            !$event->getDateTime()->isSameDay($this->currentDate)
        );
    }
}


/**
 * Creates a Markdown overview of all events in the next week (starting monday)
 */
class comcal_MarkdownBuilder extends comcal_EventsDisplayBuilder {
    var $html = '';
    function getHtml() {
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

        if ($this->latestDate !== null && $this->currentDate !== null) {
            $this->fillDaysBetween($this->currentDate->getNextDay(), $this->latestDate->getNextDay());
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
        if ($this->currentDate === null && $this->earliestDate !== null) {
            $this->fillDaysBetween($this->earliestDate, $event->getDateTime());
        } else if ($this->currentDate !== null) {
            $this->fillDaysBetween($this->currentDate->getNextDay(), $event->getDateTime());
        }
        if ($this->currentDate === null || !$this->currentDate->isSameDay($event->getDateTime())) {
            $this->currentDate = $event->getDateTime();
            $this->html .= $this->createNewDay($this->currentDate);
        }
        $this->html .= $event->getMarkdown() . '

';
        $this->currentDate = $event->getDateTime();
    }

    function createNewDay($dateTime) {
        return '🕑 **' . $dateTime->getHumanizedDate() . '**

';
    }
}