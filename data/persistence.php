<?php
/*
 * Database table description for events and categories
 */


function comcal_tableName_events() {
    global $wpdb;
    return $wpdb->prefix . 'comcal';
}

function comcal_tableName_categories() {
    global $wpdb;
    return $wpdb->prefix . 'comcal_cats';
}

function comcal_tableName_eventsVsCats() {
    global $wpdb;
    return $wpdb->prefix . 'comcal_evt_vs_cats';
}


function comcal_initTables() {
    global $wpdb;
    $wpdb->show_errors();
    $eventsTableName = comcal_tableName_events();
    $categoriesTableName = comcal_tableName_categories();
    $eventsVsCatsTableName = comcal_tableName_eventsVsCats();
    $charset_collate = $wpdb->get_charset_collate();

    // Events
    $sql = "CREATE TABLE $eventsTableName (
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
    dbDelta( $sql );

    // Categories
    $sql = "CREATE TABLE $categoriesTableName (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        categoryId tinytext NOT NULL,
        name tinytext NOT NULL,
        PRIMARY KEY  (id)
        ) $charset_collate;";
    dbDelta( $sql );

    // Events vs. Categories (many-to-many relationship)
    $sql = "CREATE TABLE $eventsVsCatsTableName (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        event_id mediumint(9) NOT NULL,
        category_id mediumint(9) NOT NULL,
        PRIMARY KEY  (id)
        ) $charset_collate;";
    dbDelta( $sql );
}

function comcal_deleteTables() {
    global $wpdb;
    $wpdb->show_errors();

    foreach ([
        comcal_tableName_eventsVsCats(),
        comcal_tableName_events(),
        comcal_tableName_categories(),
    ] as $tableName) {
        $wpdb->query("DROP TABLE $tableName;");
    }

}

function comcal_whereAnd($conditions) {
    if (empty($conditions)) {
        return '';
    }
    return 'WHERE ' . implode(' AND ', $conditions);
}


abstract class comcal_DbTable {
    /*
     * Base class for an object stored in a table
     */
    var $data = null;  // stdClass
    const IDPREFIX = 'x:';

    /* abstract static methods */
    abstract static function getTableName();
    abstract static function getAllFieldNames();
    abstract static function getIdFieldName();

    /* static query helper functions */
    static function queryRow($sql) {
        $rows = static::query($sql);
        if (empty($rows)) {
            return null;
        }
        return $rows[0];
    }
    static function query($sql) {
        global $wpdb;
        $sql = str_replace('[T]', static::getTableName(), $sql);
        return $wpdb->get_results($sql);
    }
    static function getAll() {
        global $wpdb;
        $tableName = static::getTableName();
        $rows = $wpdb->get_results("SELECT * from $tableName;");
        $all = array();
        foreach ($rows as $row) {
            $all[] = new static($row);
        }
        return $all;
    }
    static function queryByEntryId($elementId) {
        $idField = static::getIdFieldName();
        $row = self::queryRow("SELECT * FROM [T] WHERE $idField='$elementId';");
        if (empty($row)) {
            return null;
        }
        return new static($row);
    }

    /* instance methods */
    function __construct($data=array()) {
        if (is_array($data)) {
            $this->data = (object) $data;
        } else {
            $this->data = $data;
        }
    }

    /**
     * Checks if an entry with the current entry-ID exists in the database
     */
    function exists() {
        global $wpdb;
        $idFieldName = $this->getIdFieldName();
        $tableName = $this->getTableName();
        $row = $wpdb->get_row("SELECT $idFieldName FROM $tableName WHERE $idFieldName='{$this->getField($idFieldName)}';");
        return !empty($row);
    }

    /**
     * Store this object to the table
     * If the entry-ID is set and present in the tabel, the corresponding row will be updated 
     * with the current data.
     * If not, a new entry is created.
     */
    function store() {
        global $wpdb;
        $tableName = $this->getTableName();
        if ($this->exists()) {
            $where = array($this->getIdFieldName() => $this->getEntryId());
            $affectedRows = $wpdb->update(
                $tableName,
                $this->getFullData(),
                $where
            );
        } else {
            $affectedRows = $wpdb->insert($tableName, $this->getFullData());
        }
        $e = $wpdb->last_error;
        return $affectedRows;
    }

    /**
     * Deletes all entries with the current entry-ID
     */
    function delete() {
        global $wpdb;
        $tableName = $this->getTableName();
        $result = $wpdb->delete($tableName, array($this->getIdFieldName() => $this->getEntryId()));
        return $result !== false && $result;
    }

    function getField($name, $default=null) {
        if (strcmp($name, $this->getIdFieldName()) === 0) {
            $this->initEntryId();
        }
        if (isset($this->data->$name)) {
            return $this->data->$name;
        }
        if ($name === 'id') {
            // update 'id' with value from database
            $tempEntry = static::queryByEntryId($this->getEntryId());
            if ($tempEntry !== null) {
                $this->data->id = $tempEntry->getField('id');
                return $this->data->id;
            }
        }
        return $this->getDefault($name, $default);
    }

    function getEntryId() {
        return $this->getField($this->getIdFieldName());
    }
    function setField($name, $value) {
        if ($this->data->$name !== $value) {
            $this->data->$name = $value;
            return true;
        }
        return false;
    }

    private function initEntryId() {
        $idFieldName = $this->getIdFieldName();
        if (!isset($this->data->$idFieldName) || strcmp($this->data->$idFieldName, '') === 0) {
            $this->data->$idFieldName = uniqid(static::IDPREFIX);
        }
    }

    function getFullData() {
        $data = array();
        foreach ($this->getAllFieldNames() as $fieldName) {
            $data[$fieldName] = $this->getField($fieldName);
        }
        return $data;
    }

    static function DEFAULTS() {
        return array();
    }
    function getDefault($name, $default=null) {
        if ($default != null) {
            return $default;
        }
        if (isset(static::DEFAULTS()[$name])) {
            return static::DEFAULTS()[$name];
        }
        return '';
    }
}