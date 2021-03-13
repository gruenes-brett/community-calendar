<?php
/**
 * Functions for retrieving and updating categories
 *
 * @package CommunityCalendar
 */

/**
 * Category definition.
 */
class comcal_Category extends comcal_DbTable {
    const IDPREFIX = 'category:';

    public static function get_id_field_name() {
        return 'categoryId';
    }
    public static function get_all_field_names() {
        return array( 'categoryId', 'name' );
    }

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'comcal_cats';
    }

    protected static function get_create_table_query() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE [T] (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            categoryId tinytext NOT NULL,
            name tinytext NOT NULL,
            PRIMARY KEY  (id)
            ) $charset_collate;";
        return $sql;
    }

    public static function query_from_name( $name ) {
        $row = self::queryRow( 'SELECT * FROM [T] WHERE name=%s;', array( $name ) );
        if ( empty( $row ) ) {
            return null;
        }
        return new self( $row );
    }

    public static function query_from_category_id( $categoryId ) {
        $row = self::queryRow( 'SELECT * FROM [T] WHERE categoryId=%s;', array( $categoryId ) );
        if ( empty( $row ) ) {
            return null;
        }
        return new self( $row );
    }

    public static function create( $name ) {
        return new self(
            array(
                'name' => $name,
                'categoryId' => uniqid( self::IDPREFIX ),
            )
        );
    }

    public function get_public_fields() {
        $data = $this->getFullData();
        $data['html'] = comcal_categoryButton( $data['categoryId'], $data['name'], true );
        return $data;
    }
}


/**
 * NxM correlation table for events and categories.
 */
class comcal_EventVsCategory extends comcal_DbTable {
    public static function create( $event, $category ) {
        return new self(
            array(
                'event_id' => $event->getField( 'id' ),
                'category_id' => $category->getField( 'id' ),
            )
        );
    }
    public static function isset( $event, $category ) {
        return self::create( $event, $category )->exists();
    }
    public static function remove_event( $event ) {
        global $wpdb;
        $table_name = self::get_table_name();
        $result     = $wpdb->delete( $table_name, array( 'event_id' => $event->getField( 'id' ) ) );
        return false !== $result && $result;
    }

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'comcal_evt_vs_cats';
    }
    protected static function get_create_table_query() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE [T] (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            event_id mediumint(9) NOT NULL,
            category_id mediumint(9) NOT NULL,
            PRIMARY KEY  (id)
            ) $charset_collate;";
        return $sql;
    }
    public static function get_all_field_names() {
        return array( 'event_id', 'category_id' );
    }
    public static function get_id_field_name() {
        // no specific id-field.
        return 'id';
    }
    public function exists() {
        $where = 'WHERE event_id=%d AND category_id=%d';
        $row = self::queryRow( "SELECT id from [T] $where;", array( $this->getField( 'event_id' ), $this->getField( 'category_id' ) ) );
        return ! empty( $row );
    }
    public static function get_categories( $event ) {
        $cats_table = comcal_Category::get_table_name();
        $event_id   = $event->getField( 'id' );
        $query      = "SELECT $cats_table.* FROM $cats_table "
        . "INNER JOIN [T] ON [T].category_id=$cats_table.id "
        . 'WHERE [T].event_id=%d;';

        $cats = array();
        $rows = static::query( $query, array( $event_id ) );
        foreach ( $rows as $row ) {
            $cats[] = new comcal_Category( $row );
        }
        return $cats;
    }
}
