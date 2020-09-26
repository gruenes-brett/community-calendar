<?php

/*
 * Functions for handling PHP DateTime objects
 */

$evtcal_aWeekdayNamesDE = [
    'Sonntag', 'Montag', 'Dienstag', 'Mittwoch', 'Donnerstag', 'Freitag', 'Samstag'
];
$evtcal_monthMap = array(
    'Jan' => 'Januar',
    'Feb' => 'Februar',
    'Mar' => 'März',
    'Apr' => 'April',
    'May' => 'Mai',
    'Jun' => 'Juni',
    'Jul' => 'Juli',
    'Aug' => 'August',
    'Sep' => 'September',
    'Oct' => 'Oktober',
    'Nov' => 'November',
    'Dec' => 'Dezember',
);


class evtcal_DateTime {
    /*
     * Wrapper for a PHP DateTime object with convenience functions
     */
    var $dateTime = null;

    public static function fromDateStrTimeStr($dateStr, $timeStr) {
        $instance = new self();
        $instance->dateTime = new DateTime($dateStr . 'T' . $timeStr);
        return $instance;
    }
    public static function fromDateTime($dateTime) {
        $instance = new self();
        $instance->dateTime = $dateTime;
        return $instance;
    }
    public static function now() {
        $instance = new self();
        $instance->dateTime = new DateTime();
        return $instance;
    }

    function getDateStr() {
        return $this->dateTime->format('Y-m-d');
    }
    function getDateTime() {
        return $this->dateTime;
    }

    function getPrettyTime() {
        return $this->dateTime->format('H:i') . ' Uhr';
    }

    function getPrettyDate() {
        return $this->dateTime->format('d.m.Y');
    }

    function getWeekday() {
        global $evtcal_aWeekdayNamesDE;
        return $evtcal_aWeekdayNamesDE[$this->dateTime->format('w')];
    }

    function getShortWeekdayAndDay() {
        global $evtcal_aWeekdayNamesDE;
        return $this->dateTime->format('d ') . substr($evtcal_aWeekdayNamesDE[$this->dateTime->format('w')], 0, 2);
    }

    function getDayClasses() {
        $classes = '';
        switch ($this->dateTime->format('w')) {
            case 0:
            case 6:
                $classes .= 'weekend';
        }
        if (strcmp($this->getDateStr(), date('Y-m-d')) == 0) {
            $classes .= ' today';
        }
        return $classes;
    }

    function getMonthTitle() {
        global $evtcal_monthMap;
        $month = $monthEng = $this->dateTime->format('M');
        if (isset($evtcal_monthMap[$monthEng])) {
            $month = $evtcal_monthMap[$monthEng];
        }
        return $month . $this->dateTime->format(' Y');
    }

    function getFirstOfMonthDateTime(): evtcal_DateTime {
        $dt = new DateTime($this->dateTime->format('Y-m-01\TH:i:s'));
        return self::fromDateTime($dt);
    }

    function getYearMonth() {
        return $this->dateTime->format('Y-m');
    }

    function isSameMonth($dateTime) {
        return strcmp($dateTime->getYearMonth(), $this->getYearMonth()) == 0;
    }

    function isSameDay($dateTime) {
        if ($dateTime === null) {
            return false;
        }
        return strcmp($dateTime->getDateStr(), $this->getDateStr()) == 0;
    }

    function isDayLessThan($other) {
        return strcmp($this->dateTime->format('Y-m-d'), $other->getDateTime()->format('Y-m-d')) < 0;
    }

    function getNextDay() {
        $nextDay = clone $this->dateTime;
        $nextDay->add(new DateInterval('P1D'));
        return self::fromDateTime($nextDay);
    }

    function getAllDatesUntil($endDateTime) {
        $dates = [];
        $current = $this;
        while ($current->isDayLessThan($endDateTime)) {
            $dates[] = $current;
            $current = $current->getNextDay();
        }
        return $dates;
    }

    function getLastDayOfMonth() {
        $nextDay = $this;
        do {
            $previousDay = $nextDay;
            $nextDay = $nextDay->getNextDay();
        } while ($previousDay->isSameMonth($nextDay));
        return $nextDay;
    }


}