<?php
/**
 * REST API endpoints for importing event data from e.g. Facebook.
 *
 * @package CommunityCalendar
 */

/**
 * REST API endpoint wp-json/comcal/v1/import-event-url
 *
 * @param object $data Request data.
 *
 * @return array Imported event JSON data.
 */
function comcal_api_import_event_url( $data ) {
    $scraper_url = 'http://127.0.0.1:5010/api/scrape?';

    $ch = curl_init( $scraper_url . http_build_query( $data->get_params() ) );
    curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
    curl_setopt( $ch, CURLOPT_HEADER, 0 );

    $response = curl_exec( $ch );
    $info     = curl_getinfo( $ch );
    curl_close( $ch );
    if ( false === $response ) {
        return new WP_Error( 'import-event-url', "Could not reach $scraper_url", array( 'status' => 500 ) );
    }
    if ( 200 !== $info['http_code'] ) {
        return new WP_Error( 'import-event-url', $response, array( 'status' => $info['http_code'] ) );
    }
    $response_json = json_decode( $response );
    return _comcal_transform_imported_event_json( $response_json->data );
}

add_action(
    'rest_api_init',
    function () {
        global $comcal_rest_route;
        register_rest_route(
            $comcal_rest_route,
            'import-event-url',
            array(
                'methods'             => 'GET',
                'callback'            => 'comcal_api_import_event_url',
                'permission_callback' => '__return_true',
            )
        );
    }
);

function _comcal_transform_imported_event_json( $json ) {
    $start = comcal_DateTimeWrapper::from_date_time_str( $json->startDate );
    $end   = comcal_DateTimeWrapper::from_date_time_str( $json->endDate );
    return array(
        'title'       => $json->name ?? '',
        // 'organizer' ...
        'location'    => $json->location->name ?? 'keine Ortsangabe',
        'description' => $json->description ?? '',
        'url'         => $json->url ?? '',

        'date'        => $start->get_date_str(),
        'time'        => $start->get_time_str(),
        'dateEnd'     => $end->get_date_str(),
        'timeEnd'     => $end->get_time_str(),
    );
}
