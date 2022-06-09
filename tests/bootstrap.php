<?php
/**
 * Helper functions for testing and dummies of WordPress functionality.
 *
 * @package Community_Calendar
 */

setup_dummies();
require_once 'common/class-comcal-info.php';
require_once 'common/class-comcal-user-capabilities.php';
require_once 'data/class-comcal-date-time.php';
require_once 'data/class-comcal-database.php';
require_once 'data/class-comcal-event.php';
require_once 'data/class-comcal-event-iterator.php';
require_once 'view/class-comcal-events-display-builder.php';
require_once 'view/class-comcal-event-renderer.php';
require_once 'view/class-comcal-pretty-event.php';
require_once 'data/class-comcal-category.php';
require_once 'telegram/class-comcal-telegram-messaging.php';
require_once 'telegram/class-telegram-bot-agent.php';
require_once 'view/markdown/class-comcal-markdown-builder.php';
require_once 'view/markdown/class-comcal-markdown-event-renderer.php';

$current_id = 0;

function create_event_data(
    $title,
    $date,
    $date_end = null,
    $time = null,
    $join_daily = true,
    $organizer = '',
    $url = ''
) {
    global $current_id;
    $current_id++;
    if ( null === $date_end ) {
        $date_end = $date;
    }
    if ( null === $time ) {
        $time = '12:00:00';
    }
    $time_end = '23:00:00';

    return (object) array(
        'title'     => $title,
        'date'      => $date,
        'time'      => $time,

        'dateEnd'   => $date_end,
        'timeEnd'   => $time_end,
        'joinDaily' => $join_daily,

        'id'        => $current_id,
        'organizer' => $organizer,
        'url'       => $url ? $url : "https://$title.com",
    );
}


function setup_dummies() {
    // @codingStandardsIgnoreStart
    /**
     * Dummy function for WordPress current_time().
     */
    function current_time( $type, $gmt = 0 ) {
        assert( 'mysql' === $type, "type $type not supported in testing" );
        $format = 'Y-m-d H:i:s';
        $timezone = new DateTimeZone( 'Europe/Berlin' );
        $datetime = new DateTime( 'now', $timezone );
        return $datetime->format( $format );
    }

    /**
     * Dummy functionality of class wpdb.
     */
    class Wpdb_Dummy {
        public $prefix = 'testing_';

        public function get_results( $query ) {
            return array();
        }

        public function prepare( $query, ...$args ) {
            if ( is_array( $args[0] ) && count( $args ) === 1 ) {
                $args = $args[0];
            }
            $prepared = vsprintf( $query, $args );
            return $prepared;
        }
    }
    $wpdb_dummy      = new Wpdb_Dummy();
    $GLOBALS['wpdb'] = $wpdb_dummy;

    function get_current_user_id() {
        return 123;
    }

    function esc_url( $url, $protocols = null, $_context = 'display' ) {
        return urlencode( $url );
    }

    function get_bloginfo( $show = '', $filter = 'raw' ) {
        switch ( $show ) {
            case 'name':
                return 'Testing Only';
            case 'description':
                return 'Website';
            default:
                throw new Exception( "Info for $show not supported in testing" );
        }
    }

    function get_home_url() {
        return 'https://test.com';
    }

    function add_filter( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
        return true;
    }
    function add_action( $tag, $function_to_add, $priority = 10, $accepted_args = 1 ) {
        return true;
    }
    // @codingStandardsIgnoreEnd
}
