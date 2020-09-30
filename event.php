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
        return comcal_tableName_events();
    }

    // public static function queryEvent($eventId) {
    //     global $wpdb;
    //     $tableName = comcal_tableName_events();

    //     $rows = $wpdb->get_results("SELECT * FROM $tableName WHERE eventId='$eventId';");
    //     if (empty($rows)) {
    //         return null;
    //     }
    //     return new self($rows[0]);
    // }

    // function __construct($eventData = array()) {
    //     if (is_array($eventData)) {
    //         $this->data = (object) $eventData;
    //     } else {
    //         $this->data = $eventData;
    //     }
    // }
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
    function getHtml(): string {
        $editControls = '';
        if (comcal_currentUserCanSetPublic()) {
            $editControls = "<a class='editEvent' eventId='{$this->getField('eventId')}'>"
                            . "edit</a>";
        }
        $publicClass = '';
        if ($this->getField('public') == 0) {
            $publicClass = 'notPublic';
        }
        return <<<XML
        <table class='event $publicClass' eventId="{$this->getField('eventId')}"><tbody>
            <tr>
                <td class='time'>{$this->getDateTime()->getPrettyTime()}</td>
                <td class='title'>{$this->getField('title')}</td>
            </tr>
            <tr>
                <td>$editControls</td>
                <td class='organizer'>{$this->getField('organizer')}</td>
            </tr>
        </tbody></table>
XML;
    }
    function getCategoriesDetails() {
        $result = array();
        foreach ($this->getCategories() as $c) {
            $result[] = $c->getPublicFields();
        }
        return $result;
    }
}


class EventIterator implements Iterator {
    private $positition = -1;
    public $eventRows = null;

    public function __construct($publicOnly, $category=null, $calendarName='') {
        $this->eventRows = comcal_getAllEventRows($publicOnly, $category, $calendarName);
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


function comcal_addEvent($data) {
    global $wpdb;
    $event = new comcal_Event($data);
    return $event->store($wpdb);
}

function comcal_getAllEventRows($publicOnly=true, $category=null, $calendarName='') {
    global $wpdb;
    $events = comcal_tableName_events();
    $evt_cat = comcal_tableName_eventsVsCats();

    $whereConditions = array();
    $whereConditions[] = "($events.calendarName='$calendarName' OR $events.calendarName='')";
    if ($publicOnly) {
        $whereConditions[] = "$events.public='1'";
    }

    if ($category === null) {
        $where = comcal_whereAnd($whereConditions);
        $query = "SELECT * FROM $events $where ORDER BY date, time;";
    } else {
        $category_id = $category->getField('id');
        $whereConditions[] = "$evt_cat.category_id=$category_id";

        $where = comcal_whereAnd($whereConditions);
        $query = "SELECT $events.* FROM $events "
        ."INNER JOIN $evt_cat ON $evt_cat.event_id=$events.id "
        ."$where ORDER BY $events.date, $events.time;";
    }
    $rows = $wpdb->get_results($query);
    return $rows;
}