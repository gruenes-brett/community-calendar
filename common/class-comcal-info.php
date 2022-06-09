<?php
/**
 * Helper functions
 *
 * @package Community_Calendar
 */

/**
 * Helper functions.
 */
class Comcal_Info {
    /**
     * Singleton instance of this class. Use Comcal_Info::get() for creation and access.
     *
     * @var Comcal_Info
     */
    private static $instance = null;

    public static function get(): Comcal_Info {
        if ( null === self::$instance ) {
            self::$instance = new Comcal_Info(
                get_bloginfo( 'name' ) . ' ' . get_bloginfo( 'description' ),
                get_home_url(),
                'veranstaltung'  // TODO determine dynamically ... but how?
            );
        }
        return self::$instance;
    }

    /**
     * For use in unit tests.
     */
    public static function setup_test_data(
        string $website_title,
        string $website_url,
        string $event_url_path
    ) {
        self::$instance = new Comcal_Info(
            $website_title,
            $website_url,
            $event_url_path
        );
    }

    public function __construct( string $website_title, string $website_url, string $event_url_path ) {
        $this->website_title  = $website_title;
        $this->website_url    = $website_url;
        $this->event_url_path = $event_url_path;
    }

    public function get_website_title() {
        return $this->website_title;
    }
    public function get_website_url() {
        return $this->website_url;
    }
    public function get_event_url( $event_id ) {
        return $this->website_url . "/$this->event_url_path/$event_id";
    }
}
