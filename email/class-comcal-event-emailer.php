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
    const ON_SUBMITTED    = 8;  // Every editor and admin that has 'on_submitted' activated in his email settings.

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
            $email = $event->get_field( 'submitterEmail' );
            if ( ! Comcal_Settings_Common::is_email_blacklisted( $email ) ) {
                list($subject, $body) = self::get_templates()->create_event_submitted_email( $event, $submitter_name );
                wp_mail(
                    $email,
                    $subject,
                    $body
                );
            }
        }

        if ( $recipients & self::ON_SUBMITTED ) {

            $users = get_users(
                array(
                    'role__in' => array( 'editor', 'administrator' ),
                    'fields'   => array(
                        'ID',
                        'display_name',
                        'user_email',
                    ),
                )
            );

            foreach ( $users as $user ) {
                $email_settings = Comcal_Email_Settings::get_email_settings( $user->ID );

                if ( ! $email_settings['on_submitted'] ) {
                    continue;
                }

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

/**
 * Class for setting and getting email settings from user meta data.
 */
class Comcal_Email_Settings {
    /**
     * Gets email settings for the given user from user meta data.
     *
     * Creates a default object, if not set.
     *
     * @param int $user_id ID of the requested user.
     * @return array Email settings
     */
    public static function get_email_settings( $user_id ) {
        $email_settings = get_user_meta( $user_id, 'comcal_email_settings', true );
        if ( ! $email_settings ) {
            $email_settings = array();
        }

        if ( ! isset( $email_settings['on_submitted'] ) ) {
            $email_settings['on_submitted'] = true;
        }
        return $email_settings;
    }

    /**
     * Sets email settings for the given user to user meta data.
     *
     * @param int  $user_id ID of the requested user.
     * @param bool $on_submitted Whether to send the user an email for newly submitted events.
     */
    public static function set_email_settings( int $user_id, bool $on_submitted ) {
        $email_settings = array(
            'on_submitted' => $on_submitted,
        );

        update_user_meta( $user_id, 'comcal_email_settings', $email_settings );
    }

    public static function is_email_settings_enabled( $user_id ) {
        if ( ! Comcal_User_Capabilities::administer_events() ) {
            return false;
        }
        if ( ! current_user_can( 'edit_user', $user_id ) ) {
            return false;
        }
        $user_meta = get_userdata( $user_id );
        return in_array( 'editor', $user_meta->roles, true )
            || in_array( 'administrator', $user_meta->roles, true );
    }
}

function comcal_user_email_settings_form( WP_User $user ) {
    if ( ! Comcal_Email_Settings::is_email_settings_enabled( $user->ID ) ) {
        return;
    }

    $email_settings = Comcal_Email_Settings::get_email_settings( $user->ID );
    $checked        = $email_settings['on_submitted'] ? 'checked' : 'unchecked';
    echo <<<XML
    <h2>Community Calendar: E-Mail-Einstellungen</h2>
        <table class="form-table">
            <tr>
                <th><label for="user_email_on_submitted">E-Mail bei neuen Events</label></th>
                <td>
                    <input
                        type="checkbox"
                        value="yes"
                        name="user_email_on_submitted"
                        id="user_email_on_submitted"
                        $checked
                    >
                    <span class="description">E-Mail empfangen, wenn ein nicht-registrierter Benutzer eine Veranstaltung einträgt.</span>
                </td>
            </tr>
        </table>
XML;
}

add_action( 'show_user_profile', 'comcal_user_email_settings_form' ); // editing your own profile.
add_action( 'edit_user_profile', 'comcal_user_email_settings_form' ); // editing another user.

function comcal_user_email_settings_form_save( $user_id ) {
    if ( ! Comcal_Email_Settings::is_email_settings_enabled( $user_id ) ) {
        return;
    }

    $on_submitted = false;
    if ( isset( $_REQUEST['user_email_on_submitted'] ) ) {
        $on_submitted = 'yes' === $_REQUEST['user_email_on_submitted'];
    }

    Comcal_Email_Settings::set_email_settings( $user_id, $on_submitted );
}
add_action( 'personal_options_update', 'comcal_user_email_settings_form_save' );
add_action( 'edit_user_profile_update', 'comcal_user_email_settings_form_save' );
