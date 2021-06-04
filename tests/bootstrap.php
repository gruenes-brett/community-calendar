<?php

require_once 'data/class-comcal-date-time.php';
require_once 'data/class-comcal-database.php';
require_once 'data/class-comcal-event.php';
require_once 'data/class-comcal-event-iterator.php';

function create_event_data( $title, $date, $date_end = null, $time = null ) {
    if ( null === $date_end ) {
        $date_end = $date;
    }
    if ( null === $time ) {
        $time = '12:00:00';
    }
    $time_end = '23:00:00';

    return (object) array(
        'title'   => $title,
        'date'    => $date,
        'time'    => $time,

        'dateEnd' => $date_end,
        'timeEnd' => $time_end,
    );
}