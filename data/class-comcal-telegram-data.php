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
            PRIMARY KEY  (id)
            ) $charset_collate;";
        return $sql;
    }
}
