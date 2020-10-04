<?php

/*
 * Functions for rendering the month tables
 */

$comcal_calendarAlreadyShown = false;

// [tag id="foo-value"]
function comcal_table_func( $atts ) {
    global $comcal_calendarAlreadyShown;
    if ($comcal_calendarAlreadyShown) {
        return '<p style="color:red">Error: only a single calendar is allowed per page!</p>';
    }
	$a = shortcode_atts( array(
        'starttoday' => 'false',
        'name' => '',
        'style' => 'table',
    ), $atts );

    $calendarName = $a['name'];
    $style = $a['style'];
    $category = null;
    if (isset($_GET['comcal_category'])) {
        $category = comcal_Category::queryFromCategoryId($_GET['comcal_category']);
    }
    if (strtolower($a['starttoday']) != 'false') {
        $now = comcal_DateTime::now();
    } else {
        $now = null;
    }
    $output = comcal_EventsDisplayBuilder::createDisplay($style, $now);
    $isAdmin = comcal_currentUserCanSetPublic();
    $eventsIterator = new EventIterator(!$isAdmin, $category, $calendarName, $now);
    foreach ($eventsIterator as $event) {
        // $event->addCategory($ccc);
        $output->addEvent($event);
    }

    $comcal_calendarAlreadyShown = true;

    $allHtml = $output->getHtml() . comcal_getShowEventBox() . comcal_getEditForm($calendarName);
    if (comcal_currentUserCanSetPublic()) {
        $allHtml .= comcal_getEditCategoriesDialog();
    }
    return comcal_getCategoryButtons($category) . $allHtml;
}
add_shortcode( 'community-calendar-table', 'comcal_table_func' );


abstract class comcal_EventsDisplayBuilder {
    var $earliestDate = null;
    var $latestDate = null;
    static function createDisplay($styleName, $earliestDate=null) {
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
        return new $clazz($earliestDate);
    }

    abstract function addEvent($event);
    abstract function getHtml();

    function __construct($earliestDate=null) {
        $this->earliestDate = $earliestDate;
    }
    protected function isDateIncluded($dateTime) {
        $included = $this->earliestDate === null || !$dateTime->isDayLessThan($this->earliestDate);
        if ($included && $this->latestDate !== null) {
            return $dateTime->isDayLessThan($this->latestDate->getNextDay());
        }
        return $included;
    }
}


class comcal_DefaultDisplayBuilder {
    function addEvent($event) {
    }
    function getHtml() {
        return '<h3>specified display style not available!</h3>';
    }

}


class comcal_TableBuilder extends comcal_EventsDisplayBuilder {
    var $html = '';
    var $currentDate = null;

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
        $this->fillDaysBetween($date->getFirstOfMonthDateTime(), $date);
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
        if (!$this->isDateIncluded($dateTime)) {
            return;
        }
        $dateStr = $isNewDay ? $dateTime->getShortWeekdayAndDay() : '';
        $trClass = $isNewDay ? '' : 'sameDay';
        $dateClass = ($text==='') ? 'has-no-events' : 'has-events';
        $this->html .= "<tr class='{$dateTime->getDayClasses()} $trClass day'>";
        $this->html .= "<td class='date $dateClass'>$dateStr</td>";
        $this->html .= "<td class='event'>$text</td></tr>\n";
    }

    function addEvent($event) {
        if (!$this->isDateIncluded($event->getDateTime())) {
            return;
        }
        if ($this->currentDate === null || ! $this->currentDate->isSameMonth($event->getDateTime())) {
            $this->newMonth($event->getDateTime());
        } else {
            $this->fillDaysBetween($this->currentDate->getNextDay(), $event->getDateTime());
        }
        $this->createDayRow(
            $event->getDateTime(),
            $event->getHtml(),
            !$event->getDateTime()->isSameDay($this->currentDate)
        );
        $this->currentDate = $event->getDateTime();
    }
}


class comcal_MarkdownBuilder extends comcal_EventsDisplayBuilder {
    /*
     * Creates a Markdown overview of all events in the next week (starting monday)
     */
    var $html = '';
    var $currentDate = null;
    function __construct($earliestDate=null) {
        $this->earliestDate = comcal_DateTime::nextMonday();
        $this->latestDate = $this->earliestDate->getNextDay(6);
        $prettyStart = $this->earliestDate->format('d.m.');
        $prettyEnd = $this->latestDate->format('d.m.');
        $this->html = "🗓 **Woche vom $prettyStart bis $prettyEnd:**

Hallo liebe Leser*innen von @input_dd, hier die Veranstaltungsempfehlungen der kommenden Woche!
Let's GO! 🌿🌳/ 🌎 Klima-, Naturschutz & Nachhaltigkeit 🌱

";
    }
    function getHtml() {
        $this->fillDaysBetween($this->currentDate->getNextDay(), $this->latestDate->getNextDay());
        $result = '<input id="comcal-copy-markdown" type="button" class="btn btn-primary" value="Copy to clipboard"/><br>';
        $result .= '<textarea id="comcal-markdown" style="width: 100%; height: 80vh;">' . $this->html .
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
        if (!$this->isDateIncluded($event->getDateTime())) {
            return;
        }
        if ($this->currentDate === null) {
            $this->fillDaysBetween($this->earliestDate, $event->getDateTime());
        } else {
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