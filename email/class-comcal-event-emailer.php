<?php
/**
 * Functions for sending emails about events to different people.
 *
 * @package Community_Calendar
 */

/**
 * Event emailer helper class.
 */
class Comcal_Event_Emailer {
    const NOONE           = 0;
    const ALL_ADMINS      = 1;
    const ALL_EDITORS     = 2;
    const EVENT_SUBMITTER = 4;

    /**
     * The used email templater.
     *
     * @var Comcal_Email_Templates
     */
    private static $email_templates = null;

    /**
     * Returns the currently set templates.
     *
     * @return Comcal_Email_Templates
     */
    private static function get_templates() {
        if ( null === self::$email_templates ) {
            self::$email_templates = new Comcal_Email_Templates();
        }
        return self::$email_templates;
    }

    /**
     * Set the desired templates.
     *
     * @param Comcal_Email_Templates $templates Custom templates instance.
     */
    public static function set_templates( Comcal_Email_Templates $templates ) {
        self::$email_templates = $templates;
    }

    /**
     * Sends a 'new event' email to all recipients.
     *
     * @param Comcal_Event $event The newly created event.
     * @param int          $recipients Categories of recipients.
     *                                 E.g., Comcal_Event_Emailer::ALL_EDITORS | Comcal_Event_Emailer::EVENT_SUBMITTER.
     */
    public static function send_event_submitted_email( Comcal_Event $event, int $recipients ) {

        $submitter_name = $event->get_field( 'submitterName' );
        if ( $recipients & self::EVENT_SUBMITTER ) {
            list($subject, $body) = self::get_templates()->create_event_submitted_email( $event, $submitter_name );
            wp_mail(
                $event->get_field( 'submitterEmail' ),
                $subject,
                $body
            );
        }

        if ( $recipients & self::ALL_EDITORS ) {

            $users = get_users(
                array(
                    'role'   => 'editor',
                    'fields' => array(
                        'display_name',
                        'user_email',
                    ),
                )
            );

            foreach ( $users as $user ) {
                list($subject, $body) = self::get_templates()->create_event_submitted_email( $event, $user->display_name );
                wp_mail(
                    $user->user_email,
                    "$subject (von $submitter_name)",
                    $body
                );
            }
        }
    }

}
