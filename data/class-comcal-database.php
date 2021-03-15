<?php
/**
 * Database helpers and table base class.
 *
 * @package Community_Calendar
 */

/**
 * Database helper functions.
 */
class Comcal_Database {
    public static function init_tables() {
        global $wpdb;
        $wpdb->show_errors();

        Comcal_Event::create_table();
        Comcal_Category::create_table();
        Comcal_Event_Vs_Category::create_table();
    }

    public static function delete_tables() {
        global $wpdb;
        $wpdb->show_errors();

        Comcal_Event::drop_table();
        Comcal_Category::drop_table();
        Comcal_Event_Vs_Category::drop_table();
    }

    public static function where_and( $conditions ) {
        if ( empty( $conditions ) ) {
            return '';
        }
        return 'WHERE ' . implode( ' AND ', $conditions );
    }

}

/**
 * Base class for an object stored in a table.
 *
 * Besides the unique numeric id, which is used as primary key, the table may also
 * contain a column for the 'element id' which is used to identify
 * and adress the element on the application layer (specified by IDPREFIX
 * and get_id_field_name()). This id is randomly created.
 */
abstract class Comcal_Database_Table {

    /**
     * Table row data.
     *
     * @var stdClass $data
     */
    protected $data = null;
    const IDPREFIX  = 'x:';

    /**
     * Specifies table name.
     */
    abstract public static function get_table_name();

    /**
     * Specifies table column names.
     */
    abstract public static function get_all_field_names();


    /**
     * Specifies which field contains the element id.
     */
    abstract public static function get_id_field_name();

    /**
     * Specifies the SQL query for creating the table.
     */
    abstract protected static function get_create_table_query();

    /**
     * Creates the table.
     */
    public static function create_table() {
        $sql = static::get_create_table_query();
        $sql = static::prepare_query( $sql );
        dbDelta( $sql );
    }

    /**
     * Drops the table.
     */
    public static function drop_table() {
        global $wpdb;
        $wpdb->query( static::prepare_query( 'DROP TABLE [T];' ) );
    }

    /**
     * Prepares the query by replacing '[T]' with the table name.
     *
     * @param string $sql Query statement. May contain % placeholders.
     * @param array  $args Query arguments. Values for the % placeholders.
     * @return string Modified query.
     */
    public static function prepare_query( $sql, $args = array() ) {
        global $wpdb;
        $sql = str_replace( '[T]', static::get_table_name(), $sql );
        $sql = str_replace( '[ID]', static::get_id_field_name(), $sql );
        if ( strpos( $sql, '%' ) !== false ) {
            $sql = $wpdb->prepare( $sql, $args );
        }
        return $sql;
    }

    /**
     * Gets one row from the database that matches the sql statement.
     * 'LIMIT 1' will be added automatically.
     *
     * @param string $sql Query statement. May contain % placeholders.
     * @param array  $args Query arguments. Values for the % placeholders.
     * @return array Query result.
     */
    public static function query_row( $sql, $args = array() ) {
        if ( stripos( $sql, ' limit ' ) === false ) {
            $sql = trim( $sql, ';' ) . ' LIMIT 1;';
        }
        $rows = static::query( $sql, $args );
        if ( empty( $rows ) ) {
            return null;
        }
        return $rows[0];
    }

    /**
     * Prepares and executes an SQL query
     *
     * @param string $sql Query statement. May contain % placeholders.
     * @param array  $args Query arguments. Values for the % placeholders.
     * @return array Query result.
     */
    public static function query( $sql, $args = array() ) {
        global $wpdb;
        return $wpdb->get_results( static::prepare_query( $sql, $args ) );
    }

    /**
     * Returns objects for all rows in the table.
     *
     * @return array Instances of this class for all rows.
     */
    public static function get_all() {
        global $wpdb;
        $rows = static::query( 'SELECT * from [T];' );
        $all  = array();
        foreach ( $rows as $row ) {
            $all[] = new static( $row );
        }
        return $all;
    }

    /**
     * Returns an instance of this class for the given element id.
     *
     * @param any $element_id Element id of the desired object.
     * @return object|null Instance of this class or null if not found.
     */
    public static function query_by_entry_id( $element_id ) {
        $row = self::query_row( 'SELECT * FROM [T] WHERE [ID]=%s;', array( $element_id ) );
        if ( empty( $row ) ) {
            return null;
        }
        return new static( $row );
    }

    /**
     * Cound how many rows match a specific condition.
     *
     * @param string|null $where SQL condition. May contain %-placeholders.
     * @param array       $args Values for the %-placeholders.
     * @return int Number of rows found.
     */
    public static function count( $where = null, $args = array() ) {
        global $wpdb;
        $sql = 'SELECT COUNT(*) FROM [T]';
        if ( $where ) {
            $sql .= " WHERE $where;";
        }
        $sql = static::prepare_query( $sql, $args );
        $count = $wpdb->get_var( $sql );
        return $count;
    }

    /**
     * Instantiate object. Optionally providing initialization data.
     *
     * @param array $data Array containing field values.
     */
    public function __construct( $data = array() ) {
        if ( is_array( $data ) ) {
            $this->data = (object) $data;
        } else {
            $this->data = $data;
        }
    }

    /**
     * Checks if an entry with the current entry-ID exists in the database
     *
     * @return bool true if found in database.
     */
    public function exists() {
        $row = $this->query_row(
            'SELECT [ID] FROM [T] WHERE [ID]=%s;',
            array( $this->get_entry_id() )
        );
        return ! empty( $row );
    }

    /**
     * Store this object to the table
     * If the entry-ID is set and present in the tabel, the corresponding row will be updated
     * with the current data.
     * If not, a new entry is created.
     */
    public function store() {
        global $wpdb;
        $table_name = $this->get_table_name();
        if ( $this->exists() ) {
            $where = array( $this->get_id_field_name() => $this->get_entry_id() );
            $affected_rows = $wpdb->update(
                $table_name,
                $this->get_full_data(),
                $where
            );
        } else {
            $affected_rows = $wpdb->insert( $table_name, $this->get_full_data() );
        }
        $e = $wpdb->last_error;
        return $affected_rows;
    }

    /**
     * Deletes all entries with the current entry-ID
     */
    public function delete() {
        global $wpdb;
        $table_name = $this->get_table_name();
        $result     = $wpdb->delete(
            $table_name,
            array( $this->get_id_field_name() => $this->get_entry_id() )
        );
        return false !== $result && $result;
    }

    public function get_field( $name, $default = null ) {
        if ( strcmp( $name, $this->get_id_field_name() ) === 0 ) {
            $this->init_entry_id();
        }
        if ( isset( $this->data->$name ) ) {
            return $this->data->$name;
        }
        if ( 'id' === $name ) {
            // update 'id' with value from database.
            $temp_entry = static::query_by_entry_id( $this->get_entry_id() );
            if ( null !== $temp_entry ) {
                $this->data->id = $temp_entry->get_field( 'id' );
                return $this->data->id;
            }
        }
        return $this->get_default( $name, $default );
    }

    public function get_entry_id() {
        return $this->get_field( $this->get_id_field_name() );
    }
    public function set_field( $name, $value ) {
        if ( $this->data->$name !== $value ) {
            $this->data->$name = $value;
            return true;
        }
        return false;
    }

    private function init_entry_id() {
        $id_field_name = $this->get_id_field_name();
        if ( ! isset( $this->data->$id_field_name ) || 0 === strcmp( $this->data->$id_field_name, '' ) ) {
            $this->data->$id_field_name = uniqid( static::IDPREFIX );
        }
    }

    public function get_full_data() {
        $data = array();
        foreach ( $this->get_all_field_names() as $field_name ) {
            $data[ $field_name ] = $this->get_field( $field_name );
        }
        return $data;
    }

    public static function get_defaults() {
        return array();
    }
    public function get_default( $name, $default = null ) {
        if ( null !== $default ) {
            return $default;
        }
        if ( isset( static::get_defaults()[ $name ] ) ) {
            return static::get_defaults()[ $name ];
        }
        return '';
    }
}
