<?php
/**
 * REST API endpoints for importing event data from e.g. Facebook.
 *
 * @package Community_Calendar
 */

/**
 * REST API endpoint wp-json/comcal/v1/import-event-url
 *
 * Returns the event data of a Facebook event as JSON.
 *
 * Parameters:
 *          url   ... URL of Facebook event
 *          direct... Set to directly query from Facebook instead of using service.
 *
 * @param object $data Request data.
 *
 * @return array Imported event JSON data.
 */
function comcal_api_import_event_url( $data ) {
    if ( ! isset( $data->get_params()['url'] ) ) {
        return new WP_Error( 'import-event-url', "Expected parameter 'url'", array( 'status' => 500 ) );
    }

    $url = $data->get_params()['url'];
    if ( ! _comcal_check_valid_import_url( $url ) ) {
        return new WP_Error( 'import-event-url', 'Es werden nur Facebook-Events unterstützt.', array( 'status' => 500 ) );
    }

    if ( isset( $data->get_params()['direct'] ) ) {
        $response_json = _comcal_request_facebook_event( $url );
    } else {
        $response_json = _comcal_request_event_via_service( $url );
    }

    if ( is_wp_error( $response_json ) ) {
        return $response_json;
    }

    // Evaluate response.
    try {
        if ( false === $response_json ) {
            return new WP_Error( 'import-event-url', 'Es wurden keine Event-Informationen gefunden.', array( 'status' => 500 ) );
        }
        return _comcal_transform_imported_event_json( $response_json );
    } catch ( Exception $exception ) {
        return new WP_Error( 'import-event-url', "Fehler bei der Datenverarbeitung: $exception", array( 'status' => 500 ) );
    }
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

/**
 * Query Facebook event via a dedicated service.
 *
 * @param string $url Event url.
 */
function _comcal_request_event_via_service( $url ) {
    $scraper_url = 'http://127.0.0.1:5050/api/scrape';
    $response    = wp_remote_get(
        $scraper_url . '?' . http_build_query( array( 'url' => $url ) )
    );

    $response_json = json_decode( $response['body'] );
    return $response_json->data;
}

/**
 * Directly query event data from Facebook.
 *
 * @param string $url Event url.
 */
function _comcal_request_facebook_event( $url ) {
    // Define request headers.
    $user_agent = 'Mozilla/5.0 (X11; Ubuntu; Linux x86_64; rv:89.0) Gecko/20100101 Firefox/89.0';
    $headers    = array(
        'Content-Type'    => 'text/html; charset=utf-8',
        'Accept'          => '*/*',
        'Accept-Language' => 'en-US;q=0.5',
        'User-Agent'      => $user_agent,
    );

    // Send request.
    $response = wp_remote_get(
        $url,
        array(
            'headers'    => $headers,
            'user-agent' => $user_agent,
        )
    );

    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'import-event-url', "Could not reach $url", array( 'status' => 500 ) );
    }
    $response_json = _comcal_extract_event_data( $response['body'] );
    return $response_json;
}

/**
 * Find event JSON data within Facebook page.
 *
 * @param string $text Event page HTML.
 */
function _comcal_extract_event_data( $text ) {
    $pattern = '/<script type="application\/ld\+json".*>(.*"startDate".*"name".*)<\/script>/';
    $matches = array();
    $result  = preg_match( $pattern, $text, $matches );
    if ( false === $result || 0 === $result ) {
        return false;
    }
    return json_decode( $matches[1] );
}

/**
 * Transform Facebook-JSON to JSON that is compatible with Community Calendar.
 *
 * @param stdClass $json JSON as parsed from Facebook event.
 */
function _comcal_transform_imported_event_json( $json ) {
    $start = Comcal_Date_Time::from_date_time_str( $json->startDate );
    if ( isset( $json->endDate ) ) {
        $end = Comcal_Date_Time::from_date_time_str( $json->endDate );
    } else {
        $end = $start;
    }
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

/**
 * Check if it is an URL that we are potentially able to import.
 * Rules:
 *  1. Must start with http:// or https://
 *  2. Domain must be facebook.* or fb.me
 *
 * @param string $url URL to check.
 * @return bool true if valid.
 */
function _comcal_check_valid_import_url( $url ) : bool {
    $pattern = '/^https?:\/\/?([a-zA-Z-]*\.?facebook.*|fb\.me)/';
    $result  = preg_match( $pattern, $url );
    return false !== $result && $result > 0;
}
