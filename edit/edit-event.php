<?php

/**
 * Functions for showing an edit event form in a modal div
 */


/**
 * Checks if the currently logged in user may edit/delete events and
 * other superior stuff.
 */
function comcal_currentUserCanSetPublic() {
    return current_user_can('edit_others_posts');
}

/**
 * Spam protection:
 * Checks if the limit 'events submitted per time' have been surpassed
 */
function comcal_throttleEventSubmissions() {
    if (comcal_currentUserCanSetPublic()) {
        return true;
    }
    $timeLimit = 5;
    $submitLimit = 10;
    $count = comcal_Event::countEvents($timeLimit);
    if ($count >= $submitLimit) {
        comcal_warning("Spam limit exceeded: $submitLimit submissions per $timeLimit minutes");
        sleep(3);
        return "Aktion aktuell nicht möglich. Bitte in $timeLimit Minuten erneut versuchen.";
    }
    if ($count >= $submitLimit/2) {
        // Slow down submission if nearing the limit
        sleep(3);
    }
    return true;
}

function comcal_getPublicControl($public) {
    if (comcal_currentUserCanSetPublic()) {
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

function comcal_getDeleteForm($adminAjaxUrl) {
    if (comcal_currentUserCanSetPublic()) {
        $deleteNonceField = wp_nonce_field('delete_event','verification-code-delete', true, false);
        return <<<XML
            <form id="deleteEvent" action="$adminAjaxUrl" method="post">
                <br/> <br/>
                <fieldset>
                    $deleteNonceField
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

function comcal_editEventCategories() {
    $cats = comcal_Category::getAll();

    $checkBoxes = '';

    $index = 0;
    foreach ($cats as $c) {
        $suffix = "_$index";
        $checkBoxes .= <<<XML
            <div class="form-check">
                <input class="form-check-input" type="checkbox" name="category$suffix" id="eventCategory$suffix" value="{$c->getField('categoryId')}" unchecked>
                <label class="form-check-label" for="eventCategory$suffix">{$c->getField('name')}</label>
            </div>
XML;
        $index++;
    }

    return <<<XML
    <div class="form-group">
        <label for="eventCategories">Kategorien</label>
        $checkBoxes
    </div>
XML;
}

function comcal_getImportEventUrlControls() {
    return <<<XML
        <input type="button" class="btn btn-info comcal-import-event-url" value="Event von URL importieren">
XML;
}

function comcal_getEditForm($calendarName='') {
    $event = new comcal_Event();
    $eventId = '';

    $adminAjaxUrl = admin_url('admin-ajax.php');
    $nonceField = wp_nonce_field('submit_new_event','verification-code-edit', true, false);
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
    $publicControl = comcal_getPublicControl($public);
    $deleteForm = comcal_getDeleteForm($adminAjaxUrl);
    $categories = comcal_editEventCategories();
    $importEventUrl = comcal_getImportEventUrlControls();

    return <<<XML
    <div class="comcal-modal-wrapper edit-dialog">
        <div class="comcal-close">X</div>
        <div class="form-popup" id="editEvent">
            <h2></h2>
            <p><small>Pflichtfelder sind gelb, fehlerhafte Felder rosa hinterlegt</small><p>
            $importEventUrl

            <div class="alert alert-warning hide fade" role="alert"></div>

            <form id="editEvent" action="$adminAjaxUrl" method="post">
                <fieldset>
                    $nonceField
                    <input name="eventId" id="eventId" value="$eventId" type="hidden">
                    <input name="action" value="submit_new_event" type="hidden">
                    <input name="calendarName" value="$calendarName" type="hidden">
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

                    $categories

                    <div class="btn-group">
                        <input type="button" class="btn btn-secondary comcal-cancel" value="Zurück">
                        <input type="submit" class="btn btn-success comcal-send" value="Senden">
                    </div>

                </fieldset>
            </form>

            $deleteForm

            <div class="alert alert-warning hide fade" role="alert"></div>
        </div>
    </div>
XML;
}


// handle new event request
function comcal_submitNewEvent_func() {
    if ( empty($_POST) || !wp_verify_nonce($_POST['verification-code-edit'], 'submit_new_event') ) {
        echo 'You targeted the right function, but sorry, your nonce did not verify.';
        wp_die('You targeted the right function, but sorry, your nonce did not verify.', 'Error in submission',
            array('response' => 500));
    } else {
        $res = comcal_updateEventFromArray($_POST);
        if ($res[0] == 200) {
            wp_die($res[1], 'Datenübertragung erfolgreich');
        } else {
            wp_die($res[1], 'Fehler', array('response' => $res[0]));
        }
    }
}
add_action('wp_ajax_nopriv_submit_new_event', 'comcal_submitNewEvent_func');
add_action('wp_ajax_submit_new_event', 'comcal_submitNewEvent_func');

function comcal_deleteEvent_func() {
    if ( empty($_POST) || !wp_verify_nonce($_POST['verification-code-delete'], 'delete_event') ) {
        echo 'You targeted the right function, but sorry, your nonce did not verify.';
        wp_die('You targeted the right function, but sorry, your nonce did not verify.', 'Error in submission',
            array('response' => 500));
    } else {
        $res = comcal_deleteEvent($_POST['eventId']);
        if ($res[0] == 200) {
            wp_die($res[1], 'Event wurde gelöscht');
        } else {
            wp_die($res[1], 'Fehler', array('response' => $res[0]));
        }
    }
}
add_action('wp_ajax_nopriv_delete_event', 'comcal_deleteEvent_func');
add_action('wp_ajax_delete_event', 'comcal_deleteEvent_func');


function comcal_prevent_html($str) {
    return stripslashes(htmlspecialchars($str));
}

function comcal_filterPostData($data) {
    if (!comcal_currentUserCanSetPublic()) {
        // Forbid to set an event public if not logged in
        $data['public'] = 0;
        // Forbid to modify an existing event if not logged in
        $data['eventId'] = '';
    }
    foreach (comcal_Event::getTextFieldNames() as $name) {
        $data[$name] = comcal_prevent_html($data[$name]);
    }
    return $data;
}

function comcal_filterCategories($data) {
    $cats = array();
    foreach ($data as $key => $value) {
        if (strpos($key, 'category_') === 0) {
            $cats[] = comcal_Category::query_from_category_id($value);
        }
    }
    return $cats;
}

function comcal_updateEventFromArray($data) {
    $data = comcal_filterPostData($data);
    $event = new comcal_Event($data);
    $isNewEvent = !$event->exists();
    if (!comcal_currentUserCanSetPublic() && !$isNewEvent) {
        return array(500, 'Keine Berechtigung um ein Event zu aktualisieren!');
    }

    $allowSubmission = comcal_throttleEventSubmissions();
    if ($allowSubmission !== true) {
        return array(403, $allowSubmission);
    }

    if (!$event->store()) {
        if ($isNewEvent) {
            return array(500, 'Event konnte nicht angelegt werden. Fehlerhafte Informationen?');
        } else {
            return array(500, 'Fehler beim Update des Event');
        }
    } else {
        $event->removeAllCategories();
        foreach (comcal_filterCategories($data) as $cat) {
            $event->addCategory($cat);
        }
    }

    if ($isNewEvent) {
        if (comcal_currentUserCanSetPublic()) {
            return array(200, 'Event wurde angelegt.');
        } else {
            return array(200, 'Vielen Dank für deinen Eintrag! Nach einer Prüfung werden wir ihn '
                . 'so schnell wie möglich bei uns aufnehmen.');
        }
    }

    return array(200, 'Event wurde aktualisiert.');
}

function comcal_deleteEvent($eventId) {
    $event = comcal_Event::queryByEntryId($eventId);
    if ($event == null) {
        return array(500, 'Event kann nicht gelöscht werden, da nicht vorhanden');
    }
    if ($event->delete()) {
        return array(200, 'Event gelöscht');
    } else {
        return array(500, 'Fehler beim Löschen des Event');
    }
}