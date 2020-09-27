<?php

/*
 * Functions for rendering the month tables
 */


// [tag id="foo-value"]
function evtcal_table_func( $atts ) {
	$a = shortcode_atts( array(
        'starttoday' => 'false',
    ), $atts );

    $category = null;
    if (isset($_GET['evtcal_category'])) {
        $category = evtcal_Category::queryFromCategoryId($_GET['evtcal_category']);
    }
    if (strtolower($a['starttoday']) != 'false') {
        $now = evtcal_DateTime::now();
    } else {
        $now = null;
    }
    $t = new evtcal_TableBuilder($now);
    $isAdmin = evtcal_currentUserCanSetPublic();
    $eventsIterator = new EventIterator(!$isAdmin, $category);
    foreach ($eventsIterator as $event) {
        // $event->addCategory($ccc);
        $t->addEvent($event);
    }

    $allHtml = $t->getHtml() . evtcal_getShowEventBox() . evtcal_getEditForm();
    if (evtcal_currentUserCanSetPublic()) {
        $allHtml .= evtcal_getEditCategoriesDialog();
    }
    return evtcal_getCategoryButtons($category) . $allHtml;
}
add_shortcode( 'events-calendar-table', 'evtcal_table_func' );

class evtcal_TableBuilder {
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
            . "<table class='events-calendar'><tbody>\n";
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

