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

    /**
     * Increase this value if any of the table schemas change.
     */
    private const DATABASE_VERSION = '13';

    public static function init_tables() {
        global $wpdb;
        $wpdb->show_errors();

        Comcal_Event::create_table();
        Comcal_Category::create_table();
        Comcal_Event_Vs_Category::create_table();
        Comcal_Telegram_Data::create_table();
    }

    public static function delete_tables() {
        global $wpdb;
        $wpdb->show_errors();

        Comcal_Event::drop_table();
        Comcal_Category::drop_table();
        Comcal_Event_Vs_Category::drop_table();
        Comcal_Telegram_Data::drop_table();
    }

    public static function where_and( $conditions ) {
        if ( empty( $conditions ) ) {
            return '';
        }
        return 'WHERE ' . implode( ' AND ', $conditions );
    }

    /**
     * Makes sure all tables are updated to the latest schema version.
     */
    public static function update_check() {
        $current_db_version = get_option( 'evtcal_db_version' );
        if ( static::DATABASE_VERSION !== $current_db_version ) {
            static::init_tables();
            update_option( 'evtcal_db_version', static::DATABASE_VERSION );
        }
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
    const IDPREFIX  = 'xx';

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

        $all_fields = static::get_all_field_names();
        static::verify_all_fields_in_sql( $all_fields, $sql );

        dbDelta( $sql );
    }

    /**
     * Sanity checks if the result of get_all_field_names() exists in the
     * create table SQL query and vice versa.
     *
     * @param array  $all_fields List of all field names.
     * @param string $sql SQL query string that is used to create the table.
     * @throws RuntimeException If the fields are inconsistent.
     */
    public static function verify_all_fields_in_sql( $all_fields, $sql ) {
        $fields_in_sql = array();
        preg_match_all( '/^\s*([[:alnum:]_]+).*/m', $sql, $fields_in_sql );

        $ignored    = array( 'CREATE', 'PRIMARY' );
        $sql_fields = array_diff( $fields_in_sql[1], $ignored );

        $missing_fields = array_diff( $sql_fields, $all_fields );
        if ( ! empty( $missing_fields ) ) {
            $fieldnames = implode( ', ', $missing_fields );
            throw new RuntimeException( "Missing fields in all_fields: {$fieldnames}" );
        }
        $extra_fields = array_diff( $all_fields, $sql_fields );
        if ( ! empty( $extra_fields ) ) {
            $fieldnames = implode( ', ', $extra_fields );
            throw new RuntimeException( "Extra fields in all_fields: {$fieldnames}" );
        }
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
        $this->sanitize_data();
        if ( $this->exists() ) {
            $where         = array( $this->get_id_field_name() => $this->get_entry_id() );
            $affected_rows = $wpdb->update(
                $table_name,
                $this->get_available_data(),
                $where
            );
        } else {
            $affected_rows = $wpdb->insert( $table_name, $this->get_full_data() );
        }
        return $affected_rows;
    }

    /**
     * Duplicates the current event in memoiry by reassigning it a new ID. The duplicate still
     * must be stored explicitely.
     */
    public function duplicate() {
        $this->init_entry_id( true );
    }

    /**
     * Is called before storing the data to the database. Can be used to fill
     * empty or invalid fields with correct values.
     */
    protected function sanitize_data() { }

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
        assert(
            in_array(
                $name,
                $this->get_all_field_names(),
                true
            ),
            "Field $name does not exist in " . get_class( $this )
        );

        if ( $name === $this->get_id_field_name() ) {
            // Make sure we have a valid id.
            $this->init_entry_id();
        }
        if ( isset( $this->data->$name ) ) {
            return $this->data->$name;
        }

        // Check if entry exists in database.
        $temp_entry = static::query_by_entry_id( $this->get_entry_id() );
        if ( null !== $temp_entry ) {
            $this->data->$name = $temp_entry->get_field( $name );
            return $this->data->$name;
        }

        return $this->get_default( $name, $default );
    }

    public function get_entry_id() {
        return $this->get_field( $this->get_id_field_name() );
    }

    public function set_field( $name, $value ) {
        if ( ! isset( $this->data->$name ) || $this->data->$name !== $value ) {
            $this->data->$name = $value;
            return true;
        }
        return false;
    }

    /**
     * Initialize the id field with a random id, if not already set.
     *
     * @param bool $force Create a new ID even if it is set already.
     */
    private function init_entry_id( $force = false ) {
        $id_field_name = $this->get_id_field_name();
        if ( $force || ! isset( $this->data->$id_field_name ) || '' === $this->data->$id_field_name ) {
            $this->data->$id_field_name = $this->generate_id();
        }
    }

    /**
     * Create a new random id.
     *
     * @return String Id value including prefix.
     */
    protected static function generate_id() {
        $value = uniqid( static::IDPREFIX, true );
        return static::IDPREFIX . substr( md5( $value ), 0, 8 );
    }

    /**
     * Returns all field values as defined by get_all_field_names(). Fields that are
     * not set are initialized with default values.
     */
    public function get_full_data() {
        $data = array();
        foreach ( $this->get_all_field_names() as $field_name ) {
            $data[ $field_name ] = $this->get_field( $field_name );
        }
        return $data;
    }

    /**
     * Returns all field values that are actually initialized.
     */
    public function get_available_data() {
        $data = array();
        foreach ( $this->get_all_field_names() as $field_name ) {
            if ( ! isset( $this->data->$field_name ) ) {
                continue;
            }
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
