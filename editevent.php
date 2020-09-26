<?php

/*
 * Functions for showing an edit event form in a modal div
 */

function evtcal_currentUserCanSetPublic() {
    return current_user_can('edit_others_posts');
}

function evtcal_getPublicControl($public) {
    if (evtcal_currentUserCanSetPublic()) {
        $checked = $public == 1 ? 'checked' : 'unchecked';
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

function evtcal_getDeleteForm($adminAjaxUrl) {
    if (evtcal_currentUserCanSetPublic()) {
        $deleteNonceField = wp_nonce_field('delete_event','verification-code', true, false);
        return <<<XML
            <form id="deleteEvent" action="$adminAjaxUrl" method="post">
                <br/> <br/>
                <fieldset>
                    $deleteNonceField
                    <input name="eventId" id="eventId" value="" type="hidden">
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

function evtcal_editEventForm() {
    $event = new evtcal_Event();
    $eventId = '';

    $adminAjaxUrl = admin_url('admin-ajax.php');
    $nonceField = wp_nonce_field('submit_new_event','verification-code', true, false);
    $organizer = $event->getField('organizer');
    $location = $event->getField('location');
    $title = $event->getField('title');
    $date = $event->getField('date', date('Y-m-d'));
    $time = $event->getField('time', '12:00:00');
    $dateEnd = $event->getField('dateEnd', date('Y-m-d'));
    $timeEnd = $event->getField('timeEnd', '20:00:00');
    $url = $event->getField('url');
    $description = $event->getField('description');
    $public = $event->getField('public');
    $publicControl = evtcal_getPublicControl($public);
    $deleteForm = evtcal_getDeleteForm($adminAjaxUrl);

    return <<<XML
    <div class="evtcal-modal-wrapper edit-dialog">
        <div class="close">X</div>
        <div class="form-popup" id="editEvent">
            <h2></h2>
            <p><small>Pflichtfelder sind gelb, fehlerhafte Felder rosa hinterlegt</small><p>
            <form id="editEvent" action="$adminAjaxUrl" method="post">
                <fieldset>
                    $nonceField
                    <input name="eventId" id="eventId" value="$eventId" type="hidden">
                    <input name="action" value="submit_new_event" type="hidden">
                    $publicControl

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
                            <input type="date" value="$dateEnd" class="form-control" name="dateEnd" id="eventDateEnd">
                            <input type="time" value="$timeEnd" class="form-control" name="timeEnd" id="eventTimeEnd">
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

                    <div class="btn-group">
                        <input type="button" class="btn btn-secondary" id="cancel" value="Zurück">
                        <input type="submit" class="btn btn-success" id="send" value="Senden">
                    </div>

                </fieldset>
            </form>

            $deleteForm

            <div class="alert alert-warning hide fade" role="alert"></div>
        </div>
    </div>
XML;
}

function evtcal_getEditForm() {
	return evtcal_editEventForm();
}

// handle new event request
function evtcal_submitNewEvent_func() {
    if ( empty($_POST) || !wp_verify_nonce($_POST['verification-code'], 'submit_new_event') ) {
        echo 'You targeted the right function, but sorry, your nonce did not verify.';
        wp_die('You targeted the right function, but sorry, your nonce did not verify.', 'Error in submission',
            array('response' => 500));
    } else {
        $res = evtcal_updateEventFromArray($_POST);
        if ($res[0] == 200) {
            wp_die($res[1], 'Datenübertragung erfolgreich');
        } else {
            wp_die($res[1], 'Fehler', array('response' => $res[0]));
        }
    }
}
add_action('wp_ajax_nopriv_submit_new_event', 'evtcal_submitNewEvent_func');
add_action('wp_ajax_submit_new_event', 'evtcal_submitNewEvent_func');

function evtcal_deleteEvent_func() {
    if ( empty($_POST) || !wp_verify_nonce($_POST['verification-code'], 'delete_event') ) {
        echo 'You targeted the right function, but sorry, your nonce did not verify.';
        wp_die('You targeted the right function, but sorry, your nonce did not verify.', 'Error in submission',
            array('response' => 500));
    } else {
        $res = evtcal_deleteEvent($_POST['eventId']);
        if ($res[0] == 200) {
            wp_die($res[1], 'Event wurde gelöscht');
        } else {
            wp_die($res[1], 'Fehler', array('response' => $res[0]));
        }
    }
}
add_action('wp_ajax_nopriv_delete_event', 'evtcal_deleteEvent_func');
add_action('wp_ajax_delete_event', 'evtcal_deleteEvent_func');


function evtcal_prevent_html($str) {
    return stripslashes(htmlspecialchars($str));
}

function evtcal_filterPostData($data) {
    if (!evtcal_currentUserCanSetPublic()) {
        // Forbid to set an event public if not logged in
        $data['public'] = 0;
        // Forbid to modify an existing event if not logged in
        $data['eventId'] = '';
    }
    foreach (evtcal_Event::getTextFieldNames() as $name) {
        $data[$name] = evtcal_prevent_html($data[$name]);
    }
    return $data;
}

function evtcal_updateEventFromArray($data) {
    $data = evtcal_filterPostData($data);
    $event = new evtcal_Event($data);
    $isNewEvent = !$event->eventExists();
    if (!evtcal_currentUserCanSetPublic() && !$isNewEvent) {
        return array(500, 'Keine Berechtigung um ein Event zu aktualisieren!');
    }

    if (!$event->store()) {
        if ($isNewEvent) {
            return array(500, 'Event konnte nicht angelegt werden. Fehlerhafte Informationen?');
        } else {
            return array(500, 'Fehler beim Update des Event');
        }
    }

    if ($isNewEvent) {
        if (evtcal_currentUserCanSetPublic()) {
            return array(200, 'Event wurde angelegt. Seite neu laden um es anzuzeigen.');
        } else {
            return array(200, 'Vielen Dank für deinen Eintrag! Nach einer Prüfung werden wir ihn '
                . 'so schnell wie möglich bei uns aufnehmen.');
        }
    }

    return array(200, 'Event wurde aktualisiert. Bitte Seite neu laden um Änderungen zu sehen.');
}

function evtcal_deleteEvent($eventId) {
    $event = evtcal_Event::queryEvent($eventId);
    if ($event == null) {
        return array(500, 'Event kann nicht gelöscht werden, da nicht vorhanden');
    }
    if ($event->delete()) {
        return array(200, 'Event gelöscht');
    } else {
        return array(500, 'Fehler beim Löschen des Event');
    }
}