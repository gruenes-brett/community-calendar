<?php
/**
 * Defines the shortcodes.
 *
 * @package GruenesBrett
 */

$comcal_calendar_already_shown = false;

/**
 * Shortcode [community-calendar-table]
 *
 * @param array $atts Shortcode attributes.
 * @return string Resulting HTML.
 */
function comcal_table_func( $atts ) {
    global $comcal_calendar_already_shown;
    if ( $comcal_calendar_already_shown ) {
        return comcal_make_error_box( 'Error: only a single calendar is allowed per page!' );
    }
    $a = shortcode_atts(
        array(
            'start'      => null,  // show events starting from ... 'today', 'next-monday', '2020-10-22', ...
            'name'       => '',
            'style'      => 'table',
            'days'       => null,  // number of days to show (excluding start day).
            'categories' => true,  // show category buttons.
        ),
        $atts
    );

    $calendar_name   = $a['name'];
    $style           = $a['style'];
    $days            = $a['days'];
    $start           = $a['start'];
    $show_categories = $a['categories'];

    // Determine category.
    $category = null;
    if ( isset( $_GET['comcal_category'] ) ) {
        $category = Comcal_Category::query_from_category_id( $_GET['comcal_category'] );
    }

    // Determine date range.
    $latest_date = null;
    $start_date  = null;
    if ( strtolower( $start ) === 'today' ) {
        $start_date = Comcal_Date_Time::now();
    } elseif ( strtolower( $start ) === 'next-monday' ) {
        $start_date = Comcal_Date_Time::next_monday();
    } elseif ( null !== $start ) {
        try {
            $start_date = Comcal_Date_Time::from_date_str_time_str( $start, '00:00:00' );
        } catch ( Exception $e ) {
            return comcal_make_error_box( "Error in 'start' attribute:<br>{$e->getMessage()}" );
        }
    }
    if ( null !== $days && null !== $start_date ) {
        $latest_date = $start_date->get_next_day( $days );
    }

    $events_iterator = Comcal_Event_Iterator::load_from_database(
        $category,
        $calendar_name,
        $start_date ? $start_date->get_date_str() : null,
        $latest_date ? $latest_date->get_date_str() : null,
    );

    $output = Comcal_Events_Display_Builder::create_display( $style, $events_iterator, $start_date, $latest_date );

    $comcal_calendar_already_shown = true;

    $all_html = '';
    if ( $show_categories ) {
        $all_html .= comcal_get_category_buttons( $category );
    }
    $all_html .= $output->get_html() . Comcal_Basic_Event_Popup::get_popup_html() . comcal_get_edit_form( $calendar_name );
    if ( Comcal_User_Capabilities::edit_categories() ) {
        $all_html .= comcal_get_edit_categories_dialog();
    }
    return $all_html;
}
add_shortcode( 'community-calendar-table', 'comcal_table_func' );
