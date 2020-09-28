<?php
/*
 * Functions for retrieving and updating events from the database
 */

class comcal_Event {
    var $data = null;  // stdClass
    var $dateTime = null;  // comcal_DateTime
    var $categories = null;  // array of comcal_Category objects
    const DEFAULTS = array(
        'date' => '2019-01-01',
        'time' => '12:00:00',
        'eventId' => '',
        'public' => 0,
    );

    public static function queryEvent($eventId) {
        global $wpdb;
        $tableName = comcal_tableName_events();

        $rows = $wpdb->get_results("SELECT * FROM $tableName WHERE eventId='$eventId';");
        if (empty($rows)) {
            return null;
        }
        return new self($rows[0]);
    }

    function __construct($eventData = array()) {
        if (is_array($eventData)) {
            $this->data = (object) $eventData;
        } else {
            $this->data = $eventData;
        }
        $this->dateTime = comcal_DateTime::fromDateStrTimeStr($this->getField('date'), $this->getField('time'));
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
    function getDefault($name, $default=null) {
        if ($default != null) {
            return $default;
        }
        if (isset(self::DEFAULTS[$name])) {
            return self::DEFAULTS[$name];
        }
        if ($name == 'created') {
            return current_time('mysql');
        }
        return '';
    }
    function getField($name, $default=null) {
        if (strcmp($name, 'eventId') === 0) {
            $this->initEventId();
        }
        if (isset($this->data->$name)) {
            return $this->data->$name;
        }
        if ($name === 'id') {
            $tempEvent = self::queryEvent($this->getField('eventId'));
            if ($tempEvent !== null) {
                $this->data->id = $tempEvent->getField('id');
                return $this->data->id;
            }
        }
        return $this->getDefault($name, $default);
    }
    function getPublicFields() {
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
        );
    }
    private function getFullData() {
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
        );
    }
    static function getTextFieldNames() {
        return array('eventId', 'organizer', 'title', 'description', 'url');
    }
    private function initEventId() {
        if (!isset($this->data->eventId) || strcmp($this->data->eventId, '') === 0) {
            $this->data->eventId = uniqid('event:');
        }
    }
    function eventExists() {
        global $wpdb;
        $tableName = comcal_tableName_events();
        $row = $wpdb->get_row("SELECT eventId FROM $tableName WHERE eventId='{$this->getField('eventId')}';");
        return !empty($row);
    }
    function store() {
        global $wpdb;
        $tableName = comcal_tableName_events();
        if ($this->eventExists()) {
            $affectedRows = $wpdb->update(
                $tableName,
                $this->getFullData(),
                array('eventId' => $this->getField('eventId'))
            );
        } else {
            $affectedRows = $wpdb->insert($tableName, $this->getFullData());
        }
        return $affectedRows;
    }
    function delete() {
        global $wpdb;
        $tableName = comcal_tableName_events();
        return $wpdb->delete($tableName, array('eventId' => $this->getField('eventId'))) !== false;
    }
    function getDateStr(): string {
        return $this->getField('date');
    }
    function getDateTime(): comcal_DateTime {
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
                <td class='time'>{$this->dateTime->getPrettyTime()}</td>
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

    public function __construct($publicOnly, $category=null) {
        $this->eventRows = comcal_getAllEventRows($publicOnly, $category);
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

function comcal_getAllEventRows($publicOnly=true, $category=null) {
    global $wpdb;
    $events = comcal_tableName_events();
    $evt_cat = comcal_tableName_eventsVsCats();

    $whereConditions = array();
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