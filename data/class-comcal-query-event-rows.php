<?php
/**
 * Helper for querying event data from the database.
 *
 * @package Community_Calendar
 */

/**
 * Query Event data from database.
 */
class Comcal_Query_Event_Rows {

    /**
     * Query events from database, ordered by date and time.
     *
     * @param Comcal_Category $category Only a certain category.
     * @param string          $calendar_name Name of the calendar.
     * @param string          $start_date Range start.
     * @param string          $end_date Range end.
     * @param int|null        $limit_userid Certain userid or any if null.
     *
     * @return array Database query result.
     */
    public static function query_events_by_date(
        $category = null,
        $calendar_name = '',
        $start_date = null,
        $end_date = null,
        $limit_userid = null
    ) {
        return static::query_events(
            $category,
            $calendar_name,
            $start_date,
            $end_date,
            $limit_userid,
            array( 'date', 'time' ),
            'ASC'
        );
    }

    /**
     * Query events from database, ordered by creation time
     *
     * @param Comcal_Category $category Only a certain category.
     * @param string          $calendar_name Name of the calendar.
     * @param string          $start_date Range start.
     * @param string          $end_date Range end.
     * @param int|null        $limit_userid Certain userid or any if null.

     * @return array Database query result.
     */
    public static function query_events_by_creation(
        $category = null,
        $calendar_name = '',
        $start_date = null,
        $end_date = null,
        $limit_userid = null
    ) {
        return static::query_events(
            $category,
            $calendar_name,
            $start_date,
            $end_date,
            $limit_userid,
            array( 'created' ),
            'DESC'
        );
    }


    /**
     * Query events from database.
     *
     * @param Comcal_Category  $category Only a certain category.
     * @param string           $calendar_name Name of the calendar.
     * @param Comcal_Date_Time $start_date Range start.
     * @param Comcal_Date_Time $end_date Range end.
     * @param int|null         $limit_userid Certain userid or any if null.
     * @param array            $order_by_columns list of columns in the ORDER BY statement.
     * @param string           $sort_direction ASC or DESC.
     *
     * @return array Database query result.
     */
    protected static function query_events(
        $category,
        $calendar_name,
        $start_date,
        $end_date,
        $limit_userid,
        $order_by_columns,
        $sort_direction
    ) {
        global $wpdb;
        $events_table  = Comcal_Event::get_table_name();
        $evt_cat_table = Comcal_Event_Vs_Category::get_table_name();

        $where_conditions = array();
        $where_query_args = array();

        // Which calendar?
        if ( '' !== $calendar_name ) {
            $where_conditions[] = "($events_table.calendarName=%s OR $events_table.calendarName='')";
            $where_query_args[] = $calendar_name;
        }

        // Which events?
        $which_events = array();
        if ( ! Comcal_User_Capabilities::administer_events() ) {
            $which_events[]     = "$events_table.public=%d";
            $where_query_args[] = 1;
        }
        if ( Comcal_User_Capabilities::edit_own_events() && ! empty( $which_events ) ) {
            // Also select the current user's events that are private.
            $userid             = Comcal_User_Capabilities::current_user_id();
            $which_events[]     = "$events_table.userid=%s";
            $where_query_args[] = $userid;
        }
        if ( ! empty( $which_events ) ) {
            $where_conditions[] = '(' . implode( ' OR ', $which_events ) . ')';
        }

        // Limit by user?
        if ( null !== $limit_userid ) {
            $where_conditions[] = "$events_table.userid=%d";
            $where_query_args[] = $limit_userid;
        }

        // What date range?
        if ( null !== $start_date ) {
            $where_conditions[] = "$events_table.dateEnd >= %s";
            $where_query_args[] = $start_date;
        }
        if ( null !== $end_date ) {
            $where_conditions[] = "$events_table.date <= %s";
            $where_query_args[] = $end_date;
        }

        if ( null === $category ) {
            $where    = Comcal_Database::where_and( $where_conditions );
            $order_by = static::create_order_by( $order_by_columns, $sort_direction, $events_table );

            $query = "SELECT * FROM $events_table $where $order_by;";
        } else {
            // Which category?
            $category_id        = $category->get_field( 'id' );
            $where_conditions[] = "$evt_cat_table.category_id=%s";
            $where_query_args[] = $category_id;

            $where    = Comcal_Database::where_and( $where_conditions );
            $order_by = static::create_order_by( $order_by_columns, $sort_direction, $events_table );

            $query = "SELECT $events_table.* FROM $events_table "
            . "INNER JOIN $evt_cat_table ON $evt_cat_table.event_id=$events_table.id "
            . "$where $order_by;";
        }

        if ( $where_query_args ) {
            $prepared_query = $wpdb->prepare( $query, $where_query_args );
        } else {
            $prepared_query = $query;
        }

        $rows = $wpdb->get_results( $prepared_query );
        return $rows;
    }

    public static function create_order_by( $order_by_columns, $sort_direction, $table_name = null ) {
        if ( ! $order_by_columns ) {
            return '';
        }

        $order_by_fields = array();
        if ( $table_name ) {
            foreach ( $order_by_columns as $order_by_field ) {
                $order_by_fields[] = "$table_name.$order_by_field";
            }
        } else {
            $order_by_fields = $order_by_columns;
        }

        return 'ORDER BY ' . implode( ', ', $order_by_fields ) . " $sort_direction";
    }

}
