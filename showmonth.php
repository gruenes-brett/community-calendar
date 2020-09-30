<?php

/*
 * Functions for rendering the month tables
 */


// [tag id="foo-value"]
function comcal_table_func( $atts ) {
	$a = shortcode_atts( array(
        'starttoday' => 'false',
        'name' => '',
    ), $atts );

    $calendarName = $a['name'];
    $category = null;
    if (isset($_GET['comcal_category'])) {
        $category = comcal_Category::queryFromCategoryId($_GET['comcal_category']);
    }
    if (strtolower($a['starttoday']) != 'false') {
        $now = comcal_DateTime::now();
    } else {
        $now = null;
    }
    $t = new comcal_TableBuilder($now);
    $isAdmin = comcal_currentUserCanSetPublic();
    $eventsIterator = new EventIterator(!$isAdmin, $category, $calendarName);
    foreach ($eventsIterator as $event) {
        // $event->addCategory($ccc);
        $t->addEvent($event);
    }

    $allHtml = $t->getHtml() . comcal_getShowEventBox() . comcal_getEditForm($calendarName);
    if (comcal_currentUserCanSetPublic()) {
        $allHtml .= comcal_getEditCategoriesDialog();
    }
    return comcal_getCategoryButtons($category) . $allHtml;
}
add_shortcode( 'community-calendar-table', 'comcal_table_func' );

class comcal_TableBuilder {
    var $html = '';
    var $currentDate = null;
    var $earliestDate = null;

    function __construct($earliestDate=null) {
        $this->earliestDate = $earliestDate;
    }

    function getHtml() {
        $this->finishCurrentMonth();
        return $this->html;
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

    protected function isDateIncluded($dateTime) {
        return $this->earliestDate === null || !$dateTime->isDayLessThan($this->earliestDate);
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

