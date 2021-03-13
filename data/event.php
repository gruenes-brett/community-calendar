<?php
/**
 * Functions for retrieving and updating events from the database.
 *
 * @package CommunityCalendar
 */

/**
 * Event data from database.
 */
class comcal_Event extends comcal_DbTable {
    var $date_time = null;  // comcal_DateTimeWrapper ... don't use directly, always use get_date_time().
    var $categories = null;  // array of comcal_Category objects.
    const IDPREFIX = 'event:';
    static function get_defaults() {
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
        $prevDateTime = comcal_DateTimeWrapper::now()->get_prev_minutes($withinLastMinutes);
        return static::count("created >= %s", [$prevDateTime->format('Y-m-d H:i:s')]);
    }

    /* overridden static methods */
    static function get_id_field_name() {
        return 'eventId';
    }
    static function get_all_field_names() {
        return array(
            'eventId', 'date', 'time', 'dateEnd', 'timeEnd', 'organizer', 'location',
            'title', 'description', 'url', 'public', 'created', 'calendarName',
        );
    }
    static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'comcal';
    }
    protected static function get_create_table_query() {
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
        comcal_EventVsCategory::remove_event($this);
    }
    function get_categories() {
        return comcal_EventVsCategory::get_categories($this);
    }

    function get_public_fields() {
        /* returns fields and values for display */
        return array(
            'eventId' => $this->get_field('eventId'),
            'date' => $this->get_field('date'),
            'time' => $this->get_field('time'),
            'dateEnd' => $this->get_field('dateEnd'),
            'timeEnd' => $this->get_field('timeEnd'),
            'organizer' => $this->get_field('organizer'),
            'location' => $this->get_field('location'),
            'title' => $this->get_field('title'),
            'description' => $this->get_field('description'),
            'url' => $this->get_field('url'),
            'public' => $this->get_field('public'),
            'created' => $this->get_field('created'),
            'categories' => $this->getCategoriesDetails(),
            'calendarName' => $this->get_field('calendarName'),
            'number_of_days' => $this->getNumberOfDays(),
        );
    }
    static function getTextFieldNames() {
        return array('eventId', 'organizer', 'title', 'description', 'url');
    }

    function get_date_str(): string {
        return $this->get_field('date');
    }
    function get_date_time(): comcal_DateTimeWrapper {
        if ($this->date_time === null) {
            // initialize on first use
            $this->date_time = comcal_DateTimeWrapper::from_date_str_time_str($this->get_field('date'), $this->get_field('time'));
        }
        return $this->date_time;
    }

    function getCategoriesDetails() {
        $result = array();
        foreach ($this->get_categories() as $c) {
            $result[] = $c->get_public_fields();
        }
        return $result;
    }
    function getNumberOfDays() {
        $startDate = comcal_DateTimeWrapper::from_date_str_time_str($this->get_field('date'), '00:00');
        $endDate = comcal_DateTimeWrapper::from_date_str_time_str($this->get_field('dateEnd'), '00:00');
        $diff = $endDate->get_date_time_difference($startDate);
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
    $events = comcal_Event::get_table_name();
    $evt_cat = comcal_EventVsCategory::get_table_name();

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
        $where = comcal_Database::where_and($whereConditions);
        $query = "SELECT * FROM $events $where ORDER BY date, time;";
    } else {
        $category_id = $category->get_field('id');
        $whereConditions[] = "$evt_cat.category_id=$category_id";

        $where = comcal_Database::where_and($whereConditions);
        $query = "SELECT $events.* FROM $events "
        ."INNER JOIN $evt_cat ON $evt_cat.event_id=$events.id "
        ."$where ORDER BY $events.date, $events.time;";
    }
    $rows = $wpdb->get_results($query);
    return $rows;
}
