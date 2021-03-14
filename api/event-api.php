<?php
/**
 * REST API endpoints for event data.
 *
 * Functions for showing the details of an event in a modal div
 * The event details can be queried as JSON via an REST API endpoint
 * E.g.:
 *      - example.com/wp-json/comcal/v1/eventDisplay/event:1234abcd
 *          -> Prettified event details as HTML (for showing event details)
 *
 *      - example.com/wp-json/comcal/v1/eventRaw/event:1234abcd
 *          -> Event details as raw text (for the edit form)
 *
 * @package Community_Calendar
 */

$comcal_rest_route = 'comcal/v1';

function comcal_get_show_event_box() {
    return <<<XML
    <div class="comcal-modal-wrapper show-event">
        <div class="comcal-close">X</div>
        <div class="show-event-content">
            <span id="loading">Informationen werden abgerufen</span>
            <div id="content">
                <h4 id="title">no title</h4>
                <p><span id="weekday"></span>, <span id="prettyDate"></span> um <span id="prettyTime"></span></p>
                <p>Ort: <span id="location"></span></p>
                <p>Veranstalter: <span id="organizer"></span></p>
                <p><a id="url" target="_blank"></a></p>
                <b>Beschreibung:</b>
                <p id="description"></b>
                <p id="categories" class="comcal-categories"></p>
            </div>
        </div>
    </div>
XML;
}


function comcal_convert_urls_to_links( $input ) {
    $pattern = '@[^\@](http(s)?://)?(([a-zA-Z])([-\w]+\.)+([^\s\.]+[^\s]*)+[^,.\s])@';
    return preg_replace( $pattern, '<a target="_blank" href="http$2://$3">$0</a>', $input );
}

function _comcal_query_event( $data ) {
    $event = comcal_Event::query_by_entry_id( $data['eventId'] );
    if ( null === $event ) {
        return new WP_Error(
            'no_event',
            "Event {$data['eventId']} not found",
            array( 'status' => 404 )
        );
    }
    return $event->get_public_fields();
}

/**
 * API method that returns event data formatted for displaying it to the user.
 *
 * @param array $data JSON data containing an 'eventId' key.
 *
 * @return array Event JSON data.
 */
function comcal_query_event_display( $data ) {
    $result = _comcal_query_event( $data );

    $result['description'] = comcal_convert_urls_to_links( $result['description'] );
    if ( ! empty( $result['url'] ) ) {
        $result['url'] = "<a href='{$result['url']}' target='blank'>Ursprungslink</a>";
    }
    $datetime = comcal_DateTimeWrapper::from_date_str_time_str( $result['date'], $result['time'] );

    $result['prettyDate'] = $datetime->get_pretty_date();
    $result['prettyTime'] = $datetime->get_pretty_time();
    $result['weekday']    = $datetime->get_weekday();
    foreach ( comcal_Event::get_text_field_names() as $name ) {
        $result[ $name ] = nl2br( $result[ $name ] );
    }
    return $result;
}
add_action(
    'rest_api_init',
    function () {
        global $comcal_rest_route;
        register_rest_route(
            $comcal_rest_route,
            'eventDisplay/(?P<eventId>event:[a-f0-9]+)',
            array(
                'methods'             => 'GET',
                'callback'            => 'comcal_query_event_display',
                'permission_callback' => '__return_true',
            )
        );
    }
);

/**
 * API method that returns raw event data for usage in a form.
 *
 * @param array $data JSON data containing an 'eventId' key.
 *
 * @return array Event JSON data.
 */
function comcal_query_event_raw( $data ) {
    $result = _comcal_query_event( $data );
    foreach ( comcal_Event::get_text_field_names() as $name ) {
        $result[ $name ] = htmlspecialchars_decode( $result[ $name ] );
    }
    return $result;
}
add_action(
    'rest_api_init',
    function () {
        global $comcal_rest_route;
        register_rest_route(
            $comcal_rest_route,
            '/eventRaw/(?P<eventId>event:[a-f0-9]+)',
            array(
                'methods'             => 'GET',
                'callback'            => 'comcal_query_event_raw',
                'permission_callback' => '__return_true',
            )
        );
    }
);
