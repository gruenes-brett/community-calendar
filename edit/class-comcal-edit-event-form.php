<?php
/**
 * Defines the edit event form
 *
 * @package Community_Calendar
 */

/**
 * Defines the add/edit event form fields and layout.
 */
class Comcal_Edit_Event_Form extends Comcal_Form {

    /**
     * Specifies the nonce name.
     *
     * @var string
     */
    protected static $nonce_name = 'verify-edit-event';
    /**
     * Specifies the action name.
     *
     * @var string
     */
    protected static $action_name = 'comcal_submit_event_data';

    /**
     * Map that defines what form field name corresponds to which field name
     * in the Comcal_Event object.
     *
     * @var array
     */
    protected static $form_field_to_model_field = array();

    /**
     * Boolean fields.
     *
     * @var array
     */
    protected static $boolean_fields = array(
        'public',
    );

    /**
     * Event instance if used to edit an existing event.
     *
     * @var Comcal_Event
     */
    protected Comcal_Event $event;

    /**
     * Name of the calendar to which to add the event.
     *
     * @var string
     */
    protected string $calendar_name;

    public function __construct( string $calendar_name = '', Comcal_Event $event = null ) {
        $this->event         = $event ?? new Comcal_Event();
        $this->calendar_name = $calendar_name;
    }

    protected function get_form_fields(): string {

        // By default, the form is initialized with empty event data.
        // The form will be filled dynamically in edit-event.js.
        $event_id = $this->event->get_entry_id();

        $organizer      = $this->event->get_field( 'organizer' );
        $location       = $this->event->get_field( 'location' );
        $title          = $this->event->get_field( 'title' );
        $date           = $this->event->get_field( 'date', date( 'Y-m-d' ) );
        $time           = $this->event->get_field( 'time', '12:00:00' );
        $date_end       = $this->event->get_field( 'dateEnd', date( 'Y-m-d' ) );
        $time_end       = $this->event->get_field( 'timeEnd', '20:00:00' );
        $url            = $this->event->get_field( 'url' );
        $description    = $this->event->get_field( 'description' );
        $image_url      = $this->event->get_field( 'imageUrl' );
        $public         = $this->event->get_field( 'public' );
        $public_control = comcal_get_public_control( $public );
        $categories     = comcal_edit_event_categories();

        $import_event_url_controls = comcal_get_import_event_url_controls();

        return <<<XML
            $import_event_url_controls
            <input name="eventId" id="eventId" value="$event_id" type="hidden">
            <input name="calendar_name" value="{$this->calendar_name}" type="hidden">
            $public_control

            <div class="form-group">
                <label for="eventTitle">Titel der Veranstaltung</label>
                <input type="text" class="form-control" name="title" id="eventTitle" aria-describedby="eventTitleHelp" placeholder="" value="$title" required>
                <small id="eventTitleHelp" class="form-text">Bitte den offiziellen Titel der Veranstaltung angeben (Schreibweise beachten).</small>
            </div>

            <div class="form-group">
                <label for="eventOrganizer">Veranstalter*in</label>
                <input type="text" class="form-control" name="organizer" id="eventOrganizer" aria-describedby="eventOrganizerHelp" placeholder="" value="$organizer" required>
                <small id="eventOrganizerHelp" class="form-text">Bitte den*die Organisator*in der Veranstaltung angeben (Schreibweise beachten).</small>
            </div>

            <div class="form-group">
                <label for="eventLocation">Ort</label>
                <input type="text" class="form-control" name="location" id="eventLocation" aria-describedby="eventLocationHelp" placeholder="" value="$location" required>
                <small id="eventLocationHelp" class="form-text">Adresse bzw. Ort der Veranstaltung oder "Online" falls nur im Internet.</small>
            </div>

            <div class="form-group">
                <label for="eventDateTime">Beginn</label>
                <div class="input-group" id="eventDateTime" aria-describedby="eventDateTimeHelp">
                    <input type="date" value="$date" class="form-control" name="date" id="eventDate" required>
                    <input type="time" value="$time" class="form-control" name="time" id="eventTime" required>
                </div>
                <small id="eventDateTimeHelp" class="form-text">Wann beginnt die Veranstaltung?</small>
            </div>

            <div class="form-group">
                <label for="eventDateTimeEnd">Ende</label>
                <div class="input-group" id="eventDateTimeEnd" aria-describedby="eventDateTimeEndHelp">
                    <input type="date" value="$date_end" class="form-control" name="dateEnd" id="eventDateEnd">
                    <input type="time" value="$time_end" class="form-control" name="timeEnd" id="eventTimeEnd">
                </div>
                <small id="eventDateTimeEndHelp" class="form-text">Wann endet die Veranstaltung?</small>
            </div>

            <div class="form-group">
                <label for="eventUrl">Link zur Veranstaltung</label>
                <input type="url" value="$url" class="form-control" name="url" id="eventUrl" pattern="http[s]?://.*" placeholder="https://...">
                <small id="eventUrlHelp" class="form-text">Sollte mit http:// oder https:// beginnen.</small>
            </div>

            <div class="form-group">
                <label for="eventDescription">Kurze Beschreibung</label>
                <textarea class="form-control" name="description" id="eventDescription" aria-describedby="eventTitleHelp" placeholder="">$description</textarea>
                <small id="eventTitleHelp" class="form-text">Worum geht es bei der Veranstaltung</small>
            </div>

            $categories

            <div class="form-group">
                <label for="eventImageUrl">Veranstaltungsbild</label>
                <!-- <input type="file" class="form-control" name="imageUrl" id="eventImageUrl" accept="image/jpeg,image/png" placeholder="Bitte Bild zum Hochladen auswählen"> -->
                <!-- <small id="eventImageUrlHelp" class="form-text">Bitte Bild zum Hochladen auswählen</small> -->
                <input type="url" value="$image_url" class="form-control" name="imageUrl" id="eventImageUrl" placeholder="https://...">
                <small id="eventImageUrlHelp" class="form-text">URL des Veranstaltungsbildes</small>
            </div>

            <div class="btn-group">
                <input type="button" class="btn btn-secondary comcal-cancel" value="Zurück">
                <input type="submit" class="btn btn-success comcal-send" value="Senden">
            </div>
XML;
    }

    protected static function process_data( $post_data ) : array {
        return static::update_event_from_array( $post_data );
    }

    /**
     * Make sure the post data does not contain invalid data. Such as
     * trying to modify an existing event.
     *
     * @param array $data Post data.
     * @return array Sanitized post data.
     */
    protected static function sanitize_post_data( $data ) {
        if ( ! Comcal_User_Capabilities::edit_own_events() ) {
            $data['public'] = 0;
        }
        foreach ( Comcal_Event::get_text_field_names() as $name ) {
            if ( isset( $data[ $name ] ) ) {
                $data[ $name ] = comcal_prevent_html( $data[ $name ] );
            }
        }
        return $data;
    }

    protected static function update_event_from_array( $data ) {
        $data         = static::sanitize_post_data( $data );
        $event        = new Comcal_Event( $data );
        $is_new_event = ! $event->exists();
        if ( ! $is_new_event && ! $event->current_user_can_edit() ) {
            return array( 500, 'Keine Berechtigung um ein Event zu aktualisieren!' );
        }

        if ( isset( $data['delete'] ) ) {
            return static::delete_event( $event );
        }

        $allow_submission = comcal_throttle_event_submissions();
        if ( true !== $allow_submission ) {
            return array( 403, $allow_submission );
        }

        if ( ! $event->store() ) {
            if ( $is_new_event ) {
                return array( 500, 'Event konnte nicht angelegt werden. Fehlerhafte Informationen?' );
            }
        }

        $event->remove_all_categories();
        $is_first = true;
        foreach ( static::extract_category_ids( $data ) as $cat_id ) {
            $cat = Comcal_Category::query_from_category_id( $cat_id );
            $event->add_category( $cat, $is_first );
            $is_first = false;
        }

        if ( $is_new_event ) {
            if ( Comcal_User_Capabilities::administer_events() ) {
                return array( 200, 'Event wurde angelegt.' );
            } else {
                return array(
                    200,
                    'Vielen Dank für deinen Eintrag! Nach einer Prüfung werden wir ihn '
                                        . 'so schnell wie möglich bei uns aufnehmen.',
                );
            }
        }

        return array( 200, 'Event wurde aktualisiert.' );
    }

    protected static function delete_event( $event ) {
        if ( $event->delete() ) {
            return array( 200, 'Event gelöscht' );
        } else {
            return array( 500, 'Fehler beim Löschen des Event' );
        }
    }

    /**
     * Extracts catgory ids from post data.
     *
     * The first id in the list will be treated as 'primary category'.
     *
     * @param array $post_data Post data.
     */
    protected static function extract_category_ids( $post_data ) {
        $cat_ids = array();
        foreach ( $post_data as $key => $value ) {
            if ( strpos( $key, 'category_' ) === 0 ) {
                $cat_ids[] = $value;
            }
        }
        return $cat_ids;
    }

}

Comcal_Edit_Event_Form::register_form();
