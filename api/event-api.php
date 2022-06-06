<?php
/**
 * REST API endpoints for event data.
 *
 * Functions for showing the details of an event in a modal div
 * The event details can be queried as JSON via an REST API endpoint
 * E.g.:
 *      - example.com/wp-json/comcal/v1/event/ev1234abcd
 *          -> Event details as raw text (for the edit form)
 *
 *      - example.com/wp-json/comcal/v1/event/ev1234abcd?display
 *          -> Prettified event details as HTML (for showing event details)
 *
 *      - example.com/wp-json/comcal/v1/event/byCategory/Whatever
 *          -> Return all events that belong to a certain category
 *
 *      - example.com/wp-json/comcal/v1/event/all/
 *          -> Show all future events (may be many)
 *
 * @package Community_Calendar
 */

$comcal_rest_route = 'comcal/v1';


/**
 * Error container for API errors.
 */
class Comcal_Api_Error extends Exception {

    /**
     * Error code.
     *
     * @var string
     */
    public string $id;

    public function __construct( $message, $code, $id ) {
        parent::__construct( $message, $code );
        $this->id = $id;
    }
};

/**
 * Wrapper function that catches errors an returns them as WP_Error objects.
 *
 * @param Callable $func Function that contains the API logic.
 */
function _comcal_api_execute( $func ) {
    try {
        return $func();
    } catch ( Comcal_Api_Error $error ) {
        return new WP_Error( $error->id, $error->getMessage(), array( 'status' => $error->getCode() ) );
    } catch ( Exception $error ) {
        return new WP_Error( 'comcal-api', $error->getMessage(), array( 'status' => 500 ) );
    }
}

function comcal_convert_urls_to_links( $input ) {
    $pattern = '@[^\@](http(s)?://)?(([a-zA-Z])([-\w]+\.)+([^\s\.]+[^\s]*)+[^,.\s])@';
    return preg_replace( $pattern, '<a target="_blank" href="http$2://$3">$0</a>', $input );
}

function _comcal_query_event( $event_id ) {
    $event = Comcal_Event::query_by_entry_id( $event_id );
    if ( null === $event ) {
        throw new Comcal_Api_Error(
            "Event {$event_id} not found",
            404,
            'no_event'
        );
    }
    return $event;
}

function _comcal_query_category( $category_name ) {
    $category = Comcal_Category::query_from_name( $category_name );
    if ( null === $category ) {
        throw new Comcal_Api_Error(
            "Category '{$category_name}' not found",
            404,
            'no_category'
        );
    }
    return $category;
}

function _comcal_get_event_public_fields_data_as_json_raw( Comcal_Event $event ) : array {
    $result = $event->get_public_fields();
    foreach ( Comcal_Event::get_text_field_names() as $name ) {
        if ( isset( $result[ $name ] ) ) {
            $result[ $name ] = htmlspecialchars_decode( $result[ $name ] );
        }
    }
    return $result;
}

function _comcal_get_event_public_fields_data_as_json_display( Comcal_Event $event ) : array {
    $result = $event->get_public_fields();

    $result['description'] = comcal_convert_urls_to_links( $result['description'] );
    if ( ! empty( $result['url'] ) ) {
        $result['url'] = "<a href='{$result['url']}' target='blank'>Ursprungslink</a>";
    }
    $datetime = Comcal_Date_Time::from_date_str_time_str( $result['date'], $result['time'] );

    $result['prettyDate'] = $datetime->get_pretty_date();
    $result['prettyTime'] = $datetime->get_pretty_time();
    $result['weekday']    = $datetime->get_weekday();
    foreach ( Comcal_Event::get_text_field_names() as $name ) {
        if ( isset( $result[ $name ] ) ) {
            $result[ $name ] = nl2br( $result[ $name ] );
        }
    }
    return $result;
}

function _comcal_get_event_public_data_as_json( Comcal_Event $event, bool $display = false ) {
    if ( $display ) {
        return _comcal_get_event_public_fields_data_as_json_display( $event );
    } else {
        return _comcal_get_event_public_fields_data_as_json_raw( $event );
    }
}

/**
 * API method that returns event data for usage in a form.
 *
 * @param WP_REST_Request $data JSON data containing an 'eventId' key.
 *
 * @return array Event JSON data.
 */
function comcal_api_query_event( WP_REST_Request $data ) {
    return _comcal_api_execute(
        function() use ( $data ) {
            $event = _comcal_query_event( $data['eventId'] );
            return _comcal_get_event_public_data_as_json( $event, $data->has_param( 'display' ) );
        }
    );
}
add_action(
    'rest_api_init',
    function () {
        global $comcal_rest_route;
        register_rest_route(
            $comcal_rest_route,
            '/event/(?P<eventId>ev[a-f0-9]+)',
            array(
                'methods'             => 'GET',
                'callback'            => 'comcal_api_query_event',
                'permission_callback' => '__return_true',
            )
        );
    }
);


/**
 * API method that returns event data for all future events.
 *
 * @param WP_REST_Request $data JSON data containing an 'categoryName' key.
 *
 * @return array Events JSON data.
 */
function comcal_api_query_all_events( WP_REST_Request $data ) {
    return _comcal_api_execute(
        function() use ( $data ) {
            $events = Comcal_Event_Iterator::load_from_database(
                null,
                '',
                Comcal_Date_time::now()->get_date_str(),
                null
            );
            $result = array();
            foreach ( $events as $event ) {
                $result[] = _comcal_get_event_public_data_as_json( $event, $data->has_param( 'display' ) );
            }
            return $result;
        }
    );
}
add_action(
    'rest_api_init',
    function () {
        global $comcal_rest_route;
        register_rest_route(
            $comcal_rest_route,
            '/event/all/',
            array(
                'methods'             => 'GET',
                'callback'            => 'comcal_api_query_all_events',
                'permission_callback' => '__return_true',
            )
        );
    }
);

/**
 * API method that returns event data. Filtered by category.
 *
 * @param WP_REST_Request $data JSON data containing an 'categoryName' key.
 *
 * @return array Events JSON data.
 */
function comcal_api_query_event_by_category( WP_REST_Request $data ) {
    return _comcal_api_execute(
        function() use ( $data ) {
            $category_name = urldecode( $data['categoryName'] );
            $category      = _comcal_query_category( $category_name );

            $events = Comcal_Event_Iterator::load_from_database(
                $category,
                '',
                Comcal_Date_time::now()->get_date_str(),
                null
            );
            $result = array();
            foreach ( $events as $event ) {
                $result[] = _comcal_get_event_public_data_as_json( $event, $data->has_param( 'display' ) );
            }
            return $result;
        }
    );
}
add_action(
    'rest_api_init',
    function () {
        global $comcal_rest_route;
        register_rest_route(
            $comcal_rest_route,
            '/event/byCategory/(?P<categoryName>.+)',
            array(
                'methods'             => 'GET',
                'callback'            => 'comcal_api_query_event_by_category',
                'permission_callback' => '__return_true',
            )
        );
    }
);


/**
 * API method that returns event data. Filtered by organizer.
 *
 * @param WP_REST_Request $data JSON data containing an 'organizerName' key.
 *
 * @return array Events JSON data.
 */
function comcal_api_query_event_by_organizer( WP_REST_Request $data ) {
    return _comcal_api_execute(
        function() use ( $data ) {
            $organizer_name = urldecode( $data['organizerName'] );

            $events = new Comcal_Event_Iterator(
                Comcal_Query_Event_Rows::query_events_by_date(
                    null,
                    '',
                    Comcal_Date_time::now()->get_date_str(),
                    null,
                    null,
                    $organizer_name
                )
            );
            $result = array();
            foreach ( $events as $event ) {
                $result[] = _comcal_get_event_public_data_as_json( $event, $data->has_param( 'display' ) );
            }
            return $result;
        }
    );
}
add_action(
    'rest_api_init',
    function () {
        global $comcal_rest_route;
        register_rest_route(
            $comcal_rest_route,
            '/event/byOrganizer/(?P<organizerName>.+)',
            array(
                'methods'             => 'GET',
                'callback'            => 'comcal_api_query_event_by_organizer',
                'permission_callback' => '__return_true',
            )
        );
    }
);
