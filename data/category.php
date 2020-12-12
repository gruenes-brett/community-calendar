<?php
/*
 * Functions for retrieving and updating categories
 */

class comcal_Category extends comcal_DbTable {
    const IDPREFIX = 'category:';


    static function getIdFieldName() {
        return 'categoryId';
    }
    static function getAllFieldNames() {
        return array('categoryId', 'name');
    }

    static function getTableName() {
        return comcal_tableName_categories();
    }

    static function queryFromName($name) {
        $row = self::queryRow("SELECT * FROM [T] WHERE name=%s;", [$name]);
        if (empty($row)) {
            return null;
        }
        return new self($row);
    }

    static function queryFromCategoryId($categoryId) {
        $row = self::queryRow("SELECT * FROM [T] WHERE categoryId=%s;", [$categoryId]);
        if (empty($row)) {
            return null;
        }
        return new self($row);
    }

    static function create($name) {
        return new self(array(
            'name' => $name,
            'categoryId' => uniqid(self::IDPREFIX),
        ));
    }

    function getPublicFields() {
        $data = $this->getFullData();
        $data['html'] = comcal_categoryButton($data['categoryId'], $data['name'], true);
        return $data;
    }
}


class comcal_EventVsCategory extends comcal_DbTable {
    static function create($event, $category) {
        return new self(array(
            'event_id' => $event->getField('id'),
            'category_id' => $category->getField('id'),
        ));
    }
    static function isset($event, $category) {
        return self::create($event, $category)->exists();
    }
    static function removeEvent($event) {
        global $wpdb;
        $tableName = self::getTableName();
        $result = $wpdb->delete($tableName, array('event_id' => $event->getField('id')));
        return $result !== false && $result;
    }

    static function getTableName() {
        return comcal_tableName_eventsVsCats();
    }
    static function getAllFieldNames() {
        return array('event_id', 'category_id');
    }
    static function getIdFieldName() {
        // no specific id-field
        return 'id';
    }
    function exists() {
        $where = "WHERE event_id=%d AND category_id=%d";
        $row = self::queryRow("SELECT id from [T] $where;", [$this->getField('event_id'), $this->getField('category_id')]);
        return !empty($row);
    }
    static function getCategories($event) {
        $catsTable = comcal_tableName_categories();
        $event_id = $event->getField('id');
        $query = "SELECT $catsTable.* FROM $catsTable "
        . "INNER JOIN [T] ON [T].category_id=$catsTable.id "
        . "WHERE [T].event_id=%d;";
        $cats = array();
        $rows = static::query($query, [$event_id]);
        foreach ($rows as $row) {
            $cats[] = new comcal_Category($row);
        }
        return $cats;
    }
}