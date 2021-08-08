<?php
/**
 * Functions for retrieving and updating categories
 *
 * @package Community_Calendar
 */

/**
 * Category definition.
 */
class Comcal_Category extends Comcal_Database_Table {
    const IDPREFIX = 'ct';

    public static function get_id_field_name() {
        return 'categoryId';
    }
    public static function get_all_field_names() {
        return array( 'categoryId', 'name', 'style' );
    }

    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'comcal_cats';
    }

    protected static function get_create_table_query() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql             = "CREATE TABLE [T] (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            categoryId tinytext NOT NULL,
            name tinytext NOT NULL,
            style tinytext NOT NULL,
            PRIMARY KEY  (id)
            ) $charset_collate;";
        return $sql;
    }

    public static function query_from_name( $name ) {
        $row = self::query_row( 'SELECT * FROM [T] WHERE name=%s;', array( $name ) );
        if ( empty( $row ) ) {
            return null;
        }
        return new self( $row );
    }

    public static function query_from_category_id( $category_id ) {
        $row = self::query_row( 'SELECT * FROM [T] WHERE categoryId=%s;', array( $category_id ) );
        if ( empty( $row ) ) {
            return null;
        }
        return new self( $row );
    }

    public static function create( $name, $style = 'red,white' ) {
        return new self(
            array(
                'name'       => $name,
                'categoryId' => static::generate_id(),
                'style'      => $style,
            )
        );
    }

    public function get_public_fields() {
        $data         = $this->get_full_data();
        $data['html'] = comcal_category_button( $data['categoryId'], $data['name'], true );
        return $data;
    }

    public function get_background_foreground_colors() {
        $style = $this->get_field( 'style' );
        if ( ! $style ) {
            return comcal_create_unique_colors( $this->get_field( 'name' ) );
        }
        $background = strtok( $style, ',' );
        $foreground = strtok( ',' );
        return array( $background, $foreground );
    }

    public function get_background_color() {
        list( $bg, $fg ) = $this->get_background_foreground_colors();
        return $bg;
    }

    public function get_foreground_color() {
        list( $bg, $fg ) = $this->get_background_foreground_colors();
        return $fg;
    }
}


/**
 * NxM correlation table for events and categories.
 */
class Comcal_Event_Vs_Category extends Comcal_Database_Table {
    public static function create( $event, $category, bool $is_primary_category ) {
        return new self(
            array(
                'event_id'            => $event->get_field( 'id' ),
                'category_id'         => $category->get_field( 'id' ),
                'is_primary_category' => $is_primary_category,
            )
        );
    }
    public static function isset( $event, $category, bool $is_primary_category ) {
        return self::create( $event, $category, $is_primary_category )->exists();
    }
    public static function remove_event( $event ) {
        global $wpdb;
        $table_name = self::get_table_name();
        $result     = $wpdb->delete( $table_name, array( 'event_id' => $event->get_field( 'id' ) ) );
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
            is_primary_category tinyint(2) NOT NULL,
            PRIMARY KEY  (id)
            ) $charset_collate;";
        return $sql;
    }
    public static function get_all_field_names() {
        return array( 'event_id', 'category_id', 'is_primary_category' );
    }
    public static function get_id_field_name() {
        // no specific id-field.
        return 'id';
    }
    public function exists() {
        $where = 'WHERE event_id=%d AND category_id=%d AND is_primary_category=%d';
        $row   = self::query_row(
            "SELECT id from [T] $where;",
            array(
                $this->get_field( 'event_id' ),
                $this->get_field( 'category_id' ),
                $this->get_field( 'is_primary_category' ),
            )
        );
        return ! empty( $row );
    }
    public static function get_categories( $event, $primary_only = false ) {
        $cats_table = Comcal_Category::get_table_name();
        $event_id   = $event->get_field( 'id' );
        $where      = '[T].event_id=%d';

        if ( $primary_only ) {
            $where .= ' AND [T].is_primary_category=1';
        }
        $query      = "SELECT $cats_table.* FROM $cats_table "
        . "INNER JOIN [T] ON [T].category_id=$cats_table.id "
        . "WHERE $where;";

        $cats = array();
        $rows = static::query( $query, array( $event_id ) );
        foreach ( $rows as $row ) {
            $cats[] = new Comcal_Category( $row );
        }
        return $cats;
    }
}
