<?php
/*
 * Functions for showing the details of an event in a modal div
 * The event details can be queried as JSON via an REST API endpoint
 * E.g.:
 *      example.com/wp-json/evtcal/v1/eventDisplay/event:1234abcd
 *          -> Prettified event details as HTML (for showing event details)
 *
 *      example.com/wp-json/evtcal/v1/eventNoHtml/event:1234abcd
 *          -> Event details as raw text (for the edit form)
*/

$evtcal_RestRoute = 'evtcal/v1/';

function evtcal_getShowEventBox() {
    return <<<XML
    <div class="modal-wrapper show-event">
        <div class="close">X</div>
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
            </div>
        </div>
    </div>
XML;
}


function evtcal_convertUrlsToLinks($input) {
    $pattern = '@[^\@](http(s)?://)?(([a-zA-Z])([-\w]+\.)+([^\s\.]+[^\s]*)+[^,.\s])@';
    return preg_replace($pattern, '<a target="_blank" href="http$2://$3">$0</a>', $input);
}

function __evtcal_queryEvent($data) {
    $event = evtcal_Event::queryEvent($data['eventId']);
    if ($event === null) {
        return new WP_Error(
            'no_event',
            "Event {$data['eventId']} not found",
            array('status' => 404)
        );
    }
    return $event->getPublicFields();
}

function evtcal_queryEventDisplay($data) {
    $result = __evtcal_queryEvent($data);
    $result['description'] = evtcal_convertUrlsToLinks($result['description']);
    if (!empty($result['url'])) {
        $result['url'] = "<a href='{$result['url']}' target='blank'>Offizieller Veranstaltungslink</a>";
    }
    $datetime = evtcal_DateTime::fromDateStrTimeStr($result['date'], $result['time']);
    $result['prettyDate'] = $datetime->getPrettyDate();
    $result['prettyTime'] = $datetime->getPrettyTime();
    $result['weekday'] = $datetime->getWeekday();
    foreach (evtcal_Event::getTextFieldNames() as $name) {
        $result[$name] = nl2br($result[$name]);
    }
    return $result;
}
add_action('rest_api_init', function () {
    global $evtcal_RestRoute;
    register_rest_route(
        $evtcal_RestRoute,
        'eventDisplay/(?P<eventId>event:[a-f0-9]+)',
        array(
            'methods' => 'GET',
            'callback' => 'evtcal_queryEventDisplay'
        )
    );
});

function evtcal_queryEventNoHtml($data) {
    $result = __evtcal_queryEvent($data);
    foreach (evtcal_Event::getTextFieldNames() as $name) {
        $result[$name] = htmlspecialchars_decode($result[$name]);
    }
    return $result;
}
add_action('rest_api_init', function () {
    global $evtcal_RestRoute;
    register_rest_route(
        $evtcal_RestRoute,
        '/eventNoHtml/(?P<eventId>event:[a-f0-9]+)',
        array(
            'methods' => 'GET',
            'callback' => 'evtcal_queryEventNoHtml'
        )
    );
});