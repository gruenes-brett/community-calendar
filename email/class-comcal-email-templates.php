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
     * @param string       $recipient_name The name of the receiver of the email.
     * @return array subject and message.
     */
    public function create_event_submitted_email( Comcal_Event $event, string $recipient_name ) {
        $pretty  = new Comcal_Pretty_Event( $event, false );
        $link    = $this->create_event_link( $event );
        $message = <<<XML
Hallo $recipient_name,

die Veranstaltung "{$pretty->title}" wurde eingetragen. Sie wird nun von unserer Redaktion
geprüft und anschließend veröffentlicht.

Link zur Veranstaltung: $link

Danke!
XML;

        $subject = 'Neue Veranstaltung eingetragen';
        return array( $subject, trim( $message ) );
    }

}
