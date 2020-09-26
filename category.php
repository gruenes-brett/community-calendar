<?php
/*
 * Functions for retrieving and updating categories
 */

class evtcal_Category extends evtcal_DbTable {
    const IDPREFIX = 'category:';

    static function getIdFieldName() {
        return 'name';
    }
    static function getAllFieldNames() {
        return array('categoryId', 'name');
    }

    static function getTableName() {
        return evtcal_tableName_categories();
    }

    static function queryFromName($name) {
        $row = self::queryRow("SELECT * FROM [T] WHERE name='$name';");
        if (empty($row)) {
            return null;
        }
        return new self($row);
    }

    static function create($name) {
        return new self(array('name' => $name));
    }

    function getId() {
        return $this->getField('id', -1);
    }
}


class evtcal_EventVsCategory extends evtcal_DbTable {
    static function create($event, $category) {
        return new self(array(
            'event_id' => $event->getField('id'),
            'category_id' => $category->getField('id'),
        ));
    }
    static function getTableName() {
        return evtcal_tableName_eventsVsCats();
    }
    static function getAllFieldNames() {
        return array('event_id', 'category_id');
    }
    static function getIdFieldName() {
        // no specific id-field
        return 'id';
    }
    function exists() {
        $where = "WHERE event_id={$this->getField('event_id')} AND category_id={$this->getField('category_id')}";
        $row = self::queryRow("SELECT id from [T] $where;");
        return !empty($row);
    }
}