<?php
/**
 * Popups with event details that are called via AJAX.
 *
 * @package Community_Calendar
 */

/**
 * Defines a basic ajax event detail popup.
 *
 * A derived class should at least override the render() method to specify
 * how the popup looks like.
 *
 * The static verify_popup_initialized() method must be called at least once
 * before the popup may be used.
 */
abstract class Comcal_Ajax_Event_Popup {
    /**
     * Standard name for event popup nonce.
     *
     * @var string
     */
    protected static $nonce_name = 'verification-event-popup';

    /**
     * Keeps track of initialized event popup classes.
     *
     * @var array
     */
    protected static $initialized_popups = array();

    /**
     * Registers the AJAX handlers for the specific event popup class.
     */
    protected static function intialize() {
        $action        = static::get_ajax_action_name();
        $ajax_function = array( static::class, 'ajax_get_popup_content' );
        add_action( "wp_ajax_nopriv_$action", $ajax_function );
        add_action( "wp_ajax_$action", $ajax_function );
        self::$initialized_popups[ $action ] = true;
    }

    /**
     * Makes sure the current class's AJAX handlers are registered
     *
     * @return boolean True, if called for the first time.
     */
    public static function verify_popup_initialized() {
        $action = static::get_ajax_action_name();
        if ( ! isset( self::$initialized_popups[ $action ] ) ) {
            static::intialize();
            return true;
        }
        return false;
    }

    /**
     * Checks if the popup is initialized.
     *
     * @throws RuntimeException If not.
     */
    protected static function check_popup_initialized() {
        $action = static::get_ajax_action_name();
        if ( ! isset( self::$initialized_popups[ $action ] ) ) {
            $class_name = static::class;
            throw new RuntimeException(
                'Popup must be initialized first! Please call ' .
                "$class_name::verify_popup_initialized() in a global scope."
            );
        }
    }

    protected static function get_ajax_action_name() {
        return static::class . '_event_popup';
    }

    /**
     * Returns an AJAX URL that will retrieve the popup HTML for the given $event.
     *
     * @param Comcal_Event $event Event Instance.
     * @param bool         $create_copy Create URL that edits a copy of the event.
     * @return string Parameterized admin-ajax.php URL.
     */
    public static function get_event_ajax_url( Comcal_Event $event, bool $create_copy = false ) {
        static::check_popup_initialized();
        $action = static::get_ajax_action_name();
        $url    = wp_nonce_url( admin_url( 'admin-ajax.php' ), $action, static::$nonce_name );
        $url    = add_query_arg( 'eventId', $event->get_entry_id(), $url );
        if ( $create_copy ) {
            $url = add_query_arg( 'copyEvent', 'true', $url );
        }
        return add_query_arg( 'action', $action, $url );
    }

    /**
     * This is the AJAX callback. It validates the nonce
     * and if a valid eventId argument is provided, static::render( $event )
     * will be called to generate the event popup HTML.
     */
    public static function ajax_get_popup_content() {
        $action      = static::get_ajax_action_name();
        $valid_nonce = isset( $_GET[ static::$nonce_name ] ) && wp_verify_nonce(
            sanitize_text_field( wp_unslash( $_GET[ static::$nonce_name ] ) ),
            $action
        );
        if ( ! $valid_nonce ) {
            $message = 'Request not verified';
            static::render_bad_event( $message );
            wp_die( $message, 403 );
        }
        if ( ! isset( $_GET['eventId'] ) ) {
            static::render_bad_event( 'Event unbekannt' );
            wp_die();
        }
        $event_id = sanitize_text_field( wp_unslash( $_GET['eventId'] ) );
        $event    = Comcal_Event::query_by_entry_id( $event_id );
        if ( null === $event ) {
            static::render_bad_event( "Keine Event mit id $event_id vorhanden" );
            wp_die();
        }

        if ( isset( $_GET['copyEvent'] ) && 'true' === $_GET['copyEvent'] ) {
            $event->duplicate();
        }
        static::render( $event );
        wp_die();
    }

    /**
     * Override to echo the event popup HTML.
     *
     * @param Comcal_Event $event Event instance that is to be rendered.
     */
    abstract protected static function render( Comcal_Event $event ) : void;

    /**
     * Outputs an error message if the event could not be found. Override to customize.
     *
     * @param string $message Error message.
     */
    protected static function render_bad_event( string $message ) {
        echo comcal_make_error_box( $message );
    }
}

/**
 * Shows event details in an featherlight.js popup.
 */
class Comcal_Featherlight_Event_Popup extends Comcal_Ajax_Event_Popup {

    /**
     * Creates a string that defines the 'data-featherlight' attribute that will open
     * a popup with the details to $event.
     *
     * @param Comcal_Event $event Event instance.
     * @param bool         $create_copy Create URL that edits a copy of the event.
     * @return string Attribute definition.
     */
    public static function get_featherlight_attribute( Comcal_Event $event, bool $create_copy = false ) {
        $url = static::get_event_ajax_url( $event, $create_copy );
        return "data-featherlight='$url'";
    }

    protected static function render( Comcal_Event $event ) : void {
        $title       = $event->get_field( 'title' );
        $time        = $event->get_start_date_time()->get_humanized_time();
        $date        = $event->get_start_date_time()->get_humanized_date();
        $description = $event->get_field( 'description' );
        $url         = $event->get_field( 'url' );
        $image_url   = esc_url( $event->get_field( 'imageUrl' ) );
        if ( ! $image_url ) {
            $image_url = esc_url( get_stylesheet_directory_uri() . '/img/placeholder.png' );
        }
        // TODO: schoener machen.
        echo <<<XML
            <h4 id="title">$title</h4>
            <p><span id="weekday"></span>, <span id="prettyDate"></span> um <span id="prettyTime"></span></p>
            <p>Ort: <span id="location"></span></p>
            <p>Veranstalter: <span id="organizer"></span></p>
            <p><a id="url" target="_blank"></a></p>
            <b>Beschreibung:</b>
            <p id="description">$description</b>
            <p id="categories" class="comcal-categories"></p>
XML;
    }
}
