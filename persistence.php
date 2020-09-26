<?php
/*
 * Functions for handling the event data stored in a database table
 */


function evtcal_tableName() {
    global $wpdb;
    return $wpdb->prefix . 'evtcal';
}


function evtcal_initTables() {
    global $wpdb;
    $wpdb->show_errors();
    $tableName = evtcal_tableName();
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE $tableName (
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
        PRIMARY KEY  (id)
        ) $charset_collate;";

    dbDelta( $sql );
}

function evtcal_deleteTables() {
    global $wpdb;
    $wpdb->show_errors();
    $tableName = evtcal_tableName();
    $charset_collate = $wpdb->get_charset_collate();

    $wpdb->query("DROP TABLE $tableName;");

}

class evtcal_Event {
    var $data = null;
    var $dateTime = null;
    const DEFAULTS = array(
        'date' => '2019-01-01',
        'time' => '12:00:00',
        'eventId' => '',
        'public' => 0,
    );

    public static function queryEvent($eventId) {
        global $wpdb;
        $tableName = evtcal_tableName();

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
        $this->dateTime = evtcal_DateTime::fromDateStrTimeStr($this->getField('date'), $this->getField('time'));
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
        );
    }
    function getFullData() {
        return array(
            'eventId' => $this->getField('eventId'),
            'date' => $this->getField('date'),
            'time' => $this->getField('time'),
            'dateEnd' => $this->getField('dateEnd'),
            'timeEnd' => $this->getField('timeEnd'),
            'organizer' => $this->getField('organizer'),
            'location' => $this->getField('location'),
            'title' => $this->getField('title'),
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
        $tableName = evtcal_tableName();
        $row = $wpdb->get_row("SELECT eventId FROM $tableName WHERE eventId='{$this->getField('eventId')}';");
        return !empty($row);
    }
    function store() {
        global $wpdb;
        $tableName = evtcal_tableName();
        if ($this->eventExists()) {
            $affectedRows = $wpdb->update(
                $tableName,
                $this->getFullData(),
                array('eventId' => $this->getField('eventId'))
            );
        } else {
            $affectedRows = $wpdb->insert($tableName, $this->getFullData());
        }
        $e = $wpdb->last_error;
        return $affectedRows;
    }
    function delete() {
        global $wpdb;
        $tableName = evtcal_tableName();
        return $wpdb->delete($tableName, array('eventId' => $this->getField('eventId'))) !== false;
    }
    function getDateStr(): string {
        return $this->getField('date');
    }
    function getDateTime(): evtcal_DateTime {
        return $this->dateTime;
    }
    function getHtml(): string {
        $editControls = '';
        if (evtcal_currentUserCanSetPublic()) {
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
}


class EventIterator implements Iterator {
    private $positition = -1;
    public $eventRows = null;

    public function __construct($publicOnly) {
        $this->eventRows = evtcal_getAllEventRows($publicOnly);
        $this->positition = 0;
    }

    public function rewind() {
        $this->positition = 0;
    }

    public function current() {
        if ($this->positition == -1) {
            return null;
        }
        return new evtcal_Event($this->eventRows[$this->positition]);
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


function evtcal_addEvent($data) {
    global $wpdb;
    $event = new evtcal_Event($data);
    return $event->store($wpdb);
}

function evtcal_getAllEventRows($publicOnly=true) {
    global $wpdb;
    $tableName = evtcal_tableName();

    $where = '';
    if ($publicOnly) {
        $where = "WHERE public='1'";
    }

    $rows = $wpdb->get_results("SELECT * FROM $tableName $where ORDER BY date, time;");
    return $rows;
}