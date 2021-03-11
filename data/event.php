<?php
/*
 * Functions for retrieving and updating events from the database
 */

class comcal_Event extends comcal_DbTable {
    var $dateTime = null;  // comcal_DateTime ... don't use directly, always use getDateTime()
    var $categories = null;  // array of comcal_Category objects
    const IDPREFIX = 'event:';
    static function DEFAULTS() {
        return array(
        'date' => '2019-01-01',
        'time' => '12:00:00',
        'eventId' => '',
        'public' => 0,
        'created' => current_time('mysql'),
        );
    }

    /**
     * Returns how many Events have been added to the database
     * within the past X minutes.
     */
    static function countEvents($withinLastMinutes=5) {
        $prevDateTime = comcal_DateTime::now()->getPrevMinutes($withinLastMinutes);
        return static::count("created >= %s", [$prevDateTime->format('Y-m-d H:i:s')]);
    }

    /* overridden static methods */
    static function getIdFieldName() {
        return 'eventId';
    }
    static function getAllFieldNames() {
        return array(
            'eventId', 'date', 'time', 'dateEnd', 'timeEnd', 'organizer', 'location',
            'title', 'description', 'url', 'public', 'created', 'calendarName',
        );
    }
    static function getTableName() {
        global $wpdb;
        return $wpdb->prefix . 'comcal';
    }
    protected static function getCreateTableQuery() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE [T] (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            eventId tinytext NOT NULL,
            date date DEFAULT '0000-00-00' NOT NULL,
            time time DEFAULT '00:00:00' NOT NULL,
            dateEnd date DEFAULT '0000-00-00' NOT NULL,
            timeEnd time DEFAULT '00:00:00' NOT NULL,
            title tinytext NOT NULL,
            organizer tinytext DEFAULT '' NOT NULL,
            location tinytext DEFAULT '' NOT NULL,
            description text NOT NULL,
            url varchar(1300) DEFAULT '' NOT NULL,
            public tinyint(2) DEFAULT 0 NOT NULL,
            created timestamp NOT NULL,
            calendarName tinytext NOT NULL,
            PRIMARY KEY  (id)
            ) $charset_collate;";
        return $sql;
    }

    function addCategory($category) {
        $vs = comcal_EventVsCategory::create($this, $category);
        $vs->store();
    }
    function hasCategory($category) {
        return comcal_EventVsCategory::isset($this, $category);
    }
    function removeAllCategories() {
        comcal_EventVsCategory::removeEvent($this);
    }
    function getCategories() {
        return comcal_EventVsCategory::getCategories($this);
    }

    function getPublicFields() {
        /* returns fields and values for display */
        return array(
            'eventId' => $this->getField('eventId'),
            'date' => $this->getField('date'),
            'time' => $this->getField('time'),
            'dateEnd' => $this->getField('dateEnd'),
            'timeEnd' => $this->getField('timeEnd'),
            'organizer' => $this->getField('organizer'),
            'location' => $this->getField('location'),
            'title' => $this->getField('title'),
            'description' => $this->getField('description'),
            'url' => $this->getField('url'),
            'public' => $this->getField('public'),
            'created' => $this->getField('created'),
            'categories' => $this->getCategoriesDetails(),
            'calendarName' => $this->getField('calendarName'),
            'numberOfDays' => $this->getNumberOfDays(),
        );
    }
    static function getTextFieldNames() {
        return array('eventId', 'organizer', 'title', 'description', 'url');
    }

    function getDateStr(): string {
        return $this->getField('date');
    }
    function getDateTime(): comcal_DateTime {
        if ($this->dateTime === null) {
            // initialize on first use
            $this->dateTime = comcal_DateTime::fromDateStrTimeStr($this->getField('date'), $this->getField('time'));
        }
        return $this->dateTime;
    }

    function getCategoriesDetails() {
        $result = array();
        foreach ($this->getCategories() as $c) {
            $result[] = $c->getPublicFields();
        }
        return $result;
    }
    function getNumberOfDays() {
        $startDate = comcal_DateTime::fromDateStrTimeStr($this->getField('date'), '00:00');
        $endDate = comcal_DateTime::fromDateStrTimeStr($this->getField('dateEnd'), '00:00');
        $diff = $endDate->getDateTimeDifference($startDate);
        if ($diff->invert === 1) {
            return 1;
        }
        return $diff->days + 1;
    }
}


class comcal_EventIterator implements Iterator {
    private $positition = -1;
    public $eventRows = null;

    public function __construct(
        $publicOnly,
        $category = null,
        $calendarName = '',
        $startDate = null,
        $endDate = null
    ) {
        $this->eventRows = __comcal_getAllEventRows(
            $publicOnly,
            $category,
            $calendarName,
            $startDate,
            $endDate
        );
        $this->positition = 0;
    }

    public function rewind() {
        $this->positition = 0;
    }

    public function current() {
        if ($this->positition == -1) {
            return null;
        }
        return new comcal_Event($this->eventRows[$this->positition]);
    }

    public function key() {
        return $this->positition;
    }

    public function next() {
        $this->positition++;
    }

    public function valid() {
        return isset($this->eventRows[$this->positition]);
    }
}


/**
 * Query events from database
 */
function __comcal_getAllEventRows(
    $publicOnly = true,
    $category = null,
    $calendarName = '',
    $startDate = null,
    $endDate = null
) {
    global $wpdb;
    $events = comcal_Event::getTableName();
    $evt_cat = comcal_EventVsCategory::getTableName();

    $whereConditions = array();
    $whereConditions[] = "($events.calendarName='$calendarName' OR $events.calendarName='')";
    if ($publicOnly) {
        $whereConditions[] = "$events.public='1'";
    }
    if ($startDate !== null) {
        $whereConditions[] = "$events.date >= '$startDate'";
    }
    if ($endDate !== null) {
        $whereConditions[] = "$events.date <= '$endDate'";
    }

    if ($category === null) {
        $where = comcal_Database::whereAnd($whereConditions);
        $query = "SELECT * FROM $events $where ORDER BY date, time;";
    } else {
        $category_id = $category->getField('id');
        $whereConditions[] = "$evt_cat.category_id=$category_id";

        $where = comcal_Database::whereAnd($whereConditions);
        $query = "SELECT $events.* FROM $events "
        ."INNER JOIN $evt_cat ON $evt_cat.event_id=$events.id "
        ."$where ORDER BY $events.date, $events.time;";
    }
    $rows = $wpdb->get_results($query);
    return $rows;
}

