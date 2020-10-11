<?php

function comcal_api_importEventUrl($data) {
    $ch = curl_init('http://127.0.0.1:5000/api/scrape?' . http_build_query($data->get_params()));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, 0);
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    if ($info['http_code'] !== 200) {
        return new WP_Error('import-event-url', $response, array('status' => $info['http_code']));
    }
    $responseJson = json_decode($response);
    return _comcal_transformImportedEventJson($responseJson->data);
}

add_action('rest_api_init', function () {
    global $comcal_RestRoute;
    register_rest_route(
        $comcal_RestRoute,
        'import-event-url',
        array(
            'methods' => 'GET',
            'callback' => 'comcal_api_importEventUrl'
        )
    );
});


function _comcal_transformImportedEventJson($json) {
    $start = comcal_DateTime::fromDateTimeStr($json->startDate);
    $end = comcal_DateTime::fromDateTimeStr($json->endDate);
    return array(
        'title' => $json->name,
        // 'organizer' => $json->
        'location' => $json->location->name,
        'description' => $json->description,
        'url' => $json->url,

        'date' => $start->getDateStr(),
        'time' => $start->getTimeStr(),
        'dateEnd' => $end->getDateStr(),
        'timeEnd' => $end->getTimeStr(),
    );
}