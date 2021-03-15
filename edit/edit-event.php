<?php
/**
 * Functions for showing an edit event form in a modal div
 *
 * @package Community_Calendar
 */

/**
 * Checks if the currently logged in user may edit/delete events and
 * other superior stuff.
 *
 * @return bool true if logged in.
 */
function comcal_current_user_can_set_public() {
    return current_user_can( 'edit_others_posts' );
}

/**
 * Spam protection:
 * Checks if the limit 'events submitted per time' have been surpassed
 *
 * @return bool
 */
function comcal_throttle_event_submissions() {
    if ( comcal_current_user_can_set_public() ) {
        return true;
    }
    $time_limit   = 5;
    $submit_limit = 10;
    $count        = Comcal_Event::count_events( $time_limit );
    if ( $count >= $submit_limit ) {
        comcal_warning( "Spam limit exceeded: $submit_limit submissions per $time_limit minutes" );
        sleep( 3 );
        return "Aktion aktuell nicht möglich. Bitte in $time_limit Minuten erneut versuchen.";
    }
    if ( $count >= $submit_limit / 2 ) {
        // Slow down submission if nearing the limit.
        sleep( 3 );
    }
    return true;
}

/**
 * If loggedin, a checkbox for setting events public is created.
 *
 * @param bool $public Current 'public' state of the event.
 * @return string HTML form elements.
 */
function comcal_get_public_control( $public ) {
    if ( comcal_current_user_can_set_public() ) {
        $checked = 1 === $public ? 'checked' : 'unchecked';
        return <<<XML
            <div class="form-group">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="public" id="eventPublic" aria-describedby="eventPublicHelp" value="1" checked='$checked'>
                    <label class="form-check-label" for="eventPublic">Veranstaltung veröffentlichen</label>
                </div>
                <small id="eventPublicHelp" class="form-text">Soll die Veranstaltung im Kalender angzeigt werden?</small>
            </div>
XML;
    } else {
        return <<<XML
            <input type="hidden" name="public" value="$public">
XML;
    }
}

/**
 * If logged in, creates a form for deleting the event.
 *
 * @param string $admin_ajax_url AJAX URL.
 * @return string HTML form.
 */
function comcal_delete_form( $admin_ajax_url ) {
    if ( comcal_current_user_can_set_public() ) {
        $delete_nonce_field = wp_nonce_field( 'delete_event', 'verification-code-delete', true, false );
        return <<<XML
            <form id="deleteEvent" action="$admin_ajax_url" method="post">
                <br/> <br/>
                <fieldset>
                    $delete_nonce_field
                    <input name="eventId" id="eventId_delete" value="" type="hidden">
                    <input name="action" value="delete_event" type="hidden">
                    <div class="btn-group">
                        <input type="submit" class="btn btn-danger" name="delete" id="delete" value="Löschen">
                    </div>
                </fieldset>
            </form>
XML;
    } else {
        return '';
    }
}

/**
 * Creates form elements for setting event categories.
 *
 * @return string HTML form elements.
 */
function comcal_edit_event_categories() {
    $cats        = Comcal_Category::get_all();
    $check_boxes = '';
    $index       = 0;
    foreach ( $cats as $c ) {
        $suffix       = "_$index";
        $check_boxes .= <<<XML
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="category$suffix" id="eventCategory$suffix" value="{$c->get_field('categoryId')}" unchecked>
                <label class="form-check-label" for="eventCategory$suffix">{$c->get_field('name')}</label>
            </div>
XML;
        $index++;
    }

    return <<<XML
    <div class="form-group">
        <label for="eventCategories">Kategorien</label>
        $check_boxes
    </div>
XML;
}

/**
 * Creates form elements for importing an event URL.
 */
function comcal_get_import_event_url_controls() {
    return <<<XML
        <input type="button" class="btn btn-info comcal-import-event-url" value="Event von URL importieren">
XML;
}

/**
 * Produces the event edit form.
 *
 * @param string $calendar_name Name of the calendar.
 */
function comcal_get_edit_form( $calendar_name = '' ) {

    // By default, the form is initialized with empty event data.
    // The form will be filled dynamically in edit.js.
    $event    = new Comcal_Event();
    $event_id = '';

    $admin_ajax_url = admin_url( 'admin-ajax.php' );
    $nonce_field    = wp_nonce_field( 'submit_new_event', 'verification-code-edit', true, false );
    $organizer      = $event->get_field( 'organizer' );
    $location       = $event->get_field( 'location' );
    $title          = $event->get_field( 'title' );
    $date           = $event->get_field( 'date', date( 'Y-m-d' ) );
    $time           = $event->get_field( 'time', '12:00:00' );
    $date_end       = $event->get_field( 'dateEnd', date( 'Y-m-d' ) );
    $time_end       = $event->get_field( 'timeEnd', '20:00:00' );
    $url            = $event->get_field( 'url' );
    $description    = $event->get_field( 'description' );
    $image_url      = $event->get_field( 'imageUrl' );
    $public         = $event->get_field( 'public' );
    $public_control = comcal_get_public_control( $public );
    $delete_form    = comcal_delete_form( $admin_ajax_url );
    $categories     = comcal_edit_event_categories();

    $import_event_url_controls = comcal_get_import_event_url_controls();

    return <<<XML
    <div class="comcal-modal-wrapper edit-dialog">
        <div class="comcal-close">X</div>
        <div class="form-popup" id="editEvent">
            <h2></h2>
            <p><small>Pflichtfelder sind gelb, fehlerhafte Felder rosa hinterlegt</small><p>
            $import_event_url_controls

            <div class="alert alert-warning hide fade" role="alert"></div>

            <form id="editEvent" action="$admin_ajax_url" method="post">
                <fieldset>
                    $nonce_field
                    <input name="eventId" id="eventId" value="$event_id" type="hidden">
                    <input name="action" value="submit_new_event" type="hidden">
                    <input name="calendar_name" value="$calendar_name" type="hidden">
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

                </fieldset>
            </form>

            $delete_form

            <div class="alert alert-warning hide fade" role="alert"></div>
        </div>
    </div>
XML;
}

/**
 * Handle new event form submission.
 */
function comcal_submit_new_event_func() {
    $nonce_field = 'verification-code-edit';
    $action      = 'submit_new_event';
    $valid_nonce = isset( $_POST[ $nonce_field ] ) && wp_verify_nonce(
        sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ),
        $action
    );
    if ( ! $valid_nonce ) {
        $message = 'You targeted the right function, but sorry, your nonce did not verify.';
        echo $message;
        wp_die(
            $message,
            'Error in submission',
            array( 'response' => 500 )
        );
    } else {
        $res = comcal_update_event_from_array( $_POST );
        if ( 200 === $res[0] ) {
            wp_die( $res[1], 'Datenübertragung erfolgreich' );
        } else {
            wp_die( $res[1], 'Error', array( 'response' => $res[0] ) );
        }
    }
}
add_action( 'wp_ajax_nopriv_submit_new_event', 'comcal_submit_new_event_func' );
add_action( 'wp_ajax_submit_new_event', 'comcal_submit_new_event_func' );

/**
 * Handles delete event form submissions.
 */
function comcal_delete_event_func() {
    $nonce_field = 'verification-code-delete';
    $action      = 'delete_event';
    $valid_nonce = isset( $_POST[ $nonce_field ] ) && wp_verify_nonce(
        sanitize_text_field( wp_unslash( $_POST[ $nonce_field ] ) ),
        $action
    );
    if ( ! $valid_nonce ) {
        echo 'You targeted the right function, but sorry, your nonce did not verify.';
        wp_die(
            'You targeted the right function, but sorry, your nonce did not verify.',
            'Error in submission',
            array( 'response' => 500 )
        );
    } elseif ( isset( $_POST['eventId'] ) ) {
        $res = comcal_delete_event( sanitize_text_field( wp_unslash( $_POST['eventId'] ) ) );
        if ( 200 === $res[0] ) {
            wp_die( $res[1], 'Event wurde gelöscht' );
        } else {
            wp_die( $res[1], 'Error', array( 'response' => $res[0] ) );
        }
    } else {
        wp_die( 'eventId not specified', 'Error', array( 'response' => 500 ) );
    }
}
add_action( 'wp_ajax_nopriv_delete_event', 'comcal_delete_event_func' );
add_action( 'wp_ajax_delete_event', 'comcal_delete_event_func' );


function comcal_prevent_html( $str ) {
    return stripslashes( htmlspecialchars( $str ) );
}

/**
 * Make sure the post data does not contain invalid data. Such as
 * trying to modify an existing event.
 *
 * @param array $data Post data.
 * @return array Sanitized post data.
 */
function comcal_sanitize_post_data( $data ) {
    if ( ! comcal_current_user_can_set_public() ) {
        // Forbid to set an event public if not logged in.
        $data['public'] = 0;
        // Forbid to modify an existing event if not logged in.
        $data['eventId'] = '';
    }
    foreach ( Comcal_Event::get_text_field_names() as $name ) {
        $data[ $name ] = comcal_prevent_html( $data[ $name ] );
    }
    return $data;
}

/**
 * Instantiates category instances for each category found in data.
 *
 * @param array $data Post data.
 */
function comcal_filter_categories( $data ) {
    $cats = array();
    foreach ( $data as $key => $value ) {
        if ( strpos( $key, 'category_' ) === 0 ) {
            $cats[] = Comcal_Category::query_from_category_id( $value );
        }
    }
    return $cats;
}

/**
 * Updates an event from given data.
 *
 * @param array $data Post data.
 */
function comcal_update_event_from_array( $data ) {
    $data         = comcal_sanitize_post_data( $data );
    $event        = new Comcal_Event( $data );
    $is_new_event = ! $event->exists();
    if ( ! comcal_current_user_can_set_public() && ! $is_new_event ) {
        return array( 500, 'Keine Berechtigung um ein Event zu aktualisieren!' );
    }

    $allow_submission = comcal_throttle_event_submissions();
    if ( true !== $allow_submission ) {
        return array( 403, $allow_submission );
    }

    if ( ! $event->store() ) {
        if ( $is_new_event ) {
            return array( 500, 'Event konnte nicht angelegt werden. Fehlerhafte Informationen?' );
        } else {
            return array( 500, 'Fehler beim Update des Event' );
        }
    } else {
        $event->remove_all_categories();
        foreach ( comcal_filter_categories( $data ) as $cat ) {
            $event->add_category( $cat );
        }
    }

    if ( $is_new_event ) {
        if ( comcal_current_user_can_set_public() ) {
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

function comcal_delete_event( $event_id ) {
    $event = Comcal_Event::query_by_entry_id( $event_id );
    if ( null === $event ) {
        return array( 500, 'Event kann nicht gelöscht werden, da nicht vorhanden' );
    }
    if ( $event->delete() ) {
        return array( 200, 'Event gelöscht' );
    } else {
        return array( 500, 'Fehler beim Löschen des Event' );
    }
}
