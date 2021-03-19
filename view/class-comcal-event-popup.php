<?php
/**
 * Popups with event details.
 *
 * @deprecated Use Comcal_Ajax_Event_Popup instead!
 *
 * @package Community_Calendar
 */

/**
 * Defines a basic event detail popup.
 */
abstract class Comcal_Event_Popup {
    /**
     * Call this on a concrete class to put the popup HTML into the document.
     *
     * @param string $css_class Additional CSS class for the main div.
     * @return string HTML of the popup.
     */
    public static function get_popup_html( $css_class = '' ) : string {
        $instance = new static();
        $content  = $instance->render();
        return <<<XML
        <div class="comcal-modal-wrapper show-event $css_class">
            <div class="comcal-close">X</div>
            <div class="show-event-content">
                <span id="loading">Informationen werden abgerufen</span>
                <div id="content">
                    $content
                </div>
            </div>
        </div>
XML;
    }

    /**
     * The concrete implementation of this method should create the contents of the
     * As placeholders for the values from the event data use
     *
     * <span id="fieldId"></span>
     *
     * or similiar. The empty spans are filled dynamically in popup-event.js.
     * This is invoked by the JavaScript call that is generated in
     * Comcal_Event_Renderer::get_show_popup_javascript_call( $event )
     *
     * Valid id values are those defined in Comcal_Event->get_public_fields() and
     * in comcal_query_event_display() in event-api.php.
     */
    abstract protected function render() : string;
}

/**
 * Renders a basic event popup.
 */
class Comcal_Basic_Event_Popup extends Comcal_Event_Popup {
    protected function render() : string {
        return <<<XML
            <h4 id="title">no title</h4>
            <p><span id="weekday"></span>, <span id="prettyDate"></span> um <span id="prettyTime"></span></p>
            <p>Ort: <span id="location"></span></p>
            <p>Veranstalter: <span id="organizer"></span></p>
            <p><a id="url" target="_blank"></a></p>
            <b>Beschreibung:</b>
            <p id="description"></b>
            <p id="categories" class="comcal-categories"></p>
XML;
    }
}
