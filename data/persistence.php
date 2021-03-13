<?php
/*
 * Database table description for events and categories
 */


class comcal_Database {
    static function initTables() {
        global $wpdb;
        $wpdb->show_errors();

        comcal_Event::createTable();
        comcal_Category::createTable();
        comcal_EventVsCategory::createTable();
    }

    static function deleteTables() {
        global $wpdb;
        $wpdb->show_errors();

        comcal_Event::dropTable();
        comcal_Category::dropTable();
        comcal_EventVsCategory::dropTable();
    }

    static function whereAnd($conditions) {
        if (empty($conditions)) {
            return '';
        }
        return 'WHERE ' . implode(' AND ', $conditions);
    }

}

abstract class comcal_DbTable {
    /*
     * Base class for an object stored in a table
     */
    var $data = null;  // stdClass
    const IDPREFIX = 'x:';

    /* abstract static methods */
    abstract static function get_table_name();
    abstract static function get_all_field_names();
    abstract static function get_id_field_name();
    abstract protected static function get_create_table_query();

    /* database admin functions */
    static function createTable() {
        $sql = static::get_create_table_query();
        $sql = static::prepareQuery($sql);
        dbDelta($sql);
    }
    static function dropTable() {
        global $wpdb;
        $wpdb->query(static::prepareQuery("DROP TABLE [T];"));
    }

    /* static query helper functions */

    static function prepareQuery($sql, $args=[]) {
        global $wpdb;
        $sql = str_replace('[T]', static::get_table_name(), $sql);
        if (strpos($sql, '%') !== false) {
            $sql = $wpdb->prepare($sql, $args);
        }
        return $sql;
    }
    static function queryRow($sql, $args=[]) {
        if (stripos($sql, ' limit ') === false) {
            $sql = trim($sql, ';') . ' LIMIT 1;';
        }
        $rows = static::query($sql, $args);
        if (empty($rows)) {
            return null;
        }
        return $rows[0];
    }
    static function query($sql, $args=[]) {
        global $wpdb;
        return $wpdb->get_results(static::prepareQuery($sql, $args));
    }
    static function getAll() {
        global $wpdb;
        $tableName = static::get_table_name();
        $rows = $wpdb->get_results("SELECT * from $tableName;");
        $all = array();
        foreach ($rows as $row) {
            $all[] = new static($row);
        }
        return $all;
    }
    static function queryByEntryId($elementId) {
        $idField = static::get_id_field_name();
        $row = self::queryRow("SELECT * FROM [T] WHERE $idField=%s;", [$elementId]);
        if (empty($row)) {
            return null;
        }
        return new static($row);
    }
    static function count($where=null, $args=[]) {
        global $wpdb;
        $sql = 'SELECT COUNT(*) FROM [T]';
        if ($where) {
            $sql .= " WHERE $where;";
        }
        $sql = static::prepareQuery($sql, $args);
        $count = $wpdb->get_var($sql);
        return $count;
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
        $idFieldName = $this->get_id_field_name();
        $row = $this->queryRow("SELECT $idFieldName FROM [T] WHERE $idFieldName=%s;", [$this->getField($idFieldName)]);
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
        $tableName = $this->get_table_name();
        if ($this->exists()) {
            $where = array($this->get_id_field_name() => $this->getEntryId());
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
        $tableName = $this->get_table_name();
        $result = $wpdb->delete($tableName, array($this->get_id_field_name() => $this->getEntryId()));
        return $result !== false && $result;
    }

    function getField($name, $default=null) {
        if (strcmp($name, $this->get_id_field_name()) === 0) {
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
        return $this->getField($this->get_id_field_name());
    }
    function setField($name, $value) {
        if ($this->data->$name !== $value) {
            $this->data->$name = $value;
            return true;
        }
        return false;
    }

    private function initEntryId() {
        $idFieldName = $this->get_id_field_name();
        if (!isset($this->data->$idFieldName) || strcmp($this->data->$idFieldName, '') === 0) {
            $this->data->$idFieldName = uniqid(static::IDPREFIX);
        }
    }

    function getFullData() {
        $data = array();
        foreach ($this->get_all_field_names() as $fieldName) {
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