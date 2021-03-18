<?php
/**
 * Helpers for converting Comcal_Event fields into pretty HTML.
 *
 * @package Community_Calendar
 */

/**
 * Extracts public fields from an event and generates some
 * prettified fields.
 */
class Comcal_Pretty_Event extends stdClass {
    /**
     * A copy of the data fields of the Comcal_Event object.
     *
     * @var array
     */
    protected $fields;

    /**
     * A callback map that maps custom field names to functions that generate
     * pretty names.
     *
     * @var array( string => function )
     */
    protected $prettier_map;

    /**
     * Comcal_Date_Time instance, initialized from the event object.
     *
     * @var Comcal_Date_time
     */
    protected $datetime;

    /**
     * Override and/or extend to define custom pretty fields.
     *
     * @return array( string => function ) Map of custom field functions.
     */
    protected function initialize_prettier_map() {
        return array(
            'prettyDate' => function() {
                return $this->datetime->get_pretty_date();
            },
            'prettyTime' => function() {
                return $this->datetime->get_pretty_time();
            },
            'weekday'    => function() {
                return $this->datetime->get_weekday();
            },
        );
    }

    public function __construct( Comcal_Event $event ) {
        $this->fields       = $event->get_public_fields();
        $this->datetime     = Comcal_Date_Time::from_date_str_time_str(
            $this->fields['date'],
            $this->fields['time']
        );
        $this->prettier_map = $this->initialize_prettier_map();
    }

    /**
     * Magic method that returns Comcal_Event-field and custom field values.
     *
     * @param string $name Field name.
     * @return string|int|object Field value.
     * @throws RuntimeException If the field name is not valid.
     */
    public function __get( $name ) {
        if ( isset( $this->fields[ $name ] ) ) {
            $value = $this->fields[ $name ];
            if ( is_string( $value ) ) {
                $value = nl2br( $value );
            }
            return $value;
        }

        if ( isset( $this->prettier_map[ $name ] ) ) {
            return $this->prettier_map[ $name ]();
        }
        throw new RuntimeException( "Unknown event field '$name'!" );
    }
}
