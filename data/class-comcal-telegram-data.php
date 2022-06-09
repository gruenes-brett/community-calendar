<?php
/**
 * Persisting data needed for the Telegram Bot functionality.
 *
 * @package Community_Calendar
 */

/**
 * Telegram data from database.
 */
class Comcal_Telegram_Data extends Comcal_Database_Table {
    const IDPREFIX = 'tmsg';

    public static function get_defaults() {
        return array();
    }
    public static function get_table_name() {
        global $wpdb;
        return $wpdb->prefix . 'comcal_telegram';
    }
    public static function get_id_field_name() {
        return 'identifier';
    }
    public static function get_all_field_names() {
        return array(
            'id',
            'identifier',
            'original_message_date',
            'last_update_date',
            'last_message_content',
            'message_id',
            'chat_id',
            'response',
        );
    }
    protected static function get_create_table_query() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE [T] (
            id mediumint(9) NOT NULL AUTO_INCREMENT,
            identifier tinytext NOT NULL,
            original_message_date timestamp DEFAULT 0 NOT NULL,
            last_update_date timestamp DEFAULT 0 NOT NULL,
            last_message_content text NOT NULL,
            message_id int NOT NULL,
            chat_id bigint NOT NULL,
            response text NOT NULL,
            PRIMARY KEY  (id)
            ) $charset_collate;";
        return $sql;
    }

    public static function query_from_original_message_date( Comcal_Date_Time $orignal_message_date ) {
        $row = self::query_row(
            'SELECT * FROM [T] WHERE DATE(original_message_date)=%s',
            $orignal_message_date->get_date_str()
        );
        if ( null === $row ) {
            return null;
        }
        return new self( $row );
    }

    public static function create_from_response( Comcal_Date_Time $orignal_message_date, stdClass $response_json ) {
        $new = new self(
            array(
                'original_message_date' => $orignal_message_date->get_database_timestamp(),
                'message_id'            => $response_json->result->message_id,
                'chat_id'               => $response_json->result->chat->id,
            )
        );
        $new->update_from_response( $response_json );
        return $new;
    }

    public function get_message_id() {
        return $this->get_field( 'message_id' );
    }

    public function update_from_response( stdClass $response_json ) {
        $data = array(
            'last_update_date'     => current_time( 'mysql' ),
            'last_message_content' => $response_json->result->text,
            'response'             => json_encode( $response_json ),
        );
        foreach ( $data as $name => $value ) {
            $this->set_field( $name, $value );
        }
    }

    public function get_last_message_content() {
        return $this->get_field( 'last_message_content' );
    }
}
