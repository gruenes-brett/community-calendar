<?php
/**
 * Functions that provide default email templates for the Event_Emailer.
 *
 * @package Community_Calendar
 */

 /**
  * Defines default email templates. Derive from this class for custom templates.
  */
class Comcal_Email_Templates {

    /**
     * Returns the full URL to view an event.
     *
     * @param Comcal_Event $event The event.
     */
    public function create_event_link( Comcal_Event $event ) : string {
        return esc_url( get_home_url() . '/veranstaltung/' . $event->get_field( 'eventId' ) );
    }

    /**
     * Creates subject and body of an 'event submitted' email for the submitter of the event
     *
     * @param Comcal_Event $event The event.
     * @return array subject and message.
     */
    public function create_event_submitted_email_for_submitter( Comcal_Event $event ) {
        $pretty  = new Comcal_Pretty_Event( $event );
        $name    = $event->get_field( 'submitterName' );
        $link    = $this->create_event_link( $event );
        $message = <<<XML
Hallo $name,

die Veranstaltung "{$pretty->title}" wurde eingetragen und wartet auf Freigabe.

Link zur Veranstaltung: $link
XML;

        $subject = 'Neue Veranstaltung eingetragen';
        return array( $subject, trim( $message ) );
    }

}
