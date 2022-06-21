<?php
/**
 * Common settings page for Community Calendar.
 *
 * @package Community_Calendar
 */

/**
 * Common settings section.
 */
class Comcal_Settings_Common extends Comcal_Settings {

    public static function get_section_title() {
        return 'Allgemein';
    }

    public static function get_section_tag() {
        return 'common';
    }

    public static function get_option_ids() {
        return array(
            'email_blacklist' => 'email_blacklist_0',
        );
    }

    public static function get_option_labels() {
        return array(
            'email_blacklist' => 'Blockierte E-Mail-Adressen',
        );
    }

    public function email_blacklist_0_callback() {
        $value = self::get_option_value( 'email_blacklist' );
        printf(
            '<input class="regular-text" type="text" name="comcal_settings_option_name[email_blacklist_0]" id="email_blacklist_0" value="%s" placeholder="abc@example.com, xyz@mail.me">',
            esc_attr( $value )
        );
        ?>
        <p class="description">
            Kommagetrennte Liste von E-Mail-Adressen, an welche keine Bestätigungsmails verschickt werden sollen.
        </p>
        <?php
    }

}

if ( is_admin() ) {
    Comcal_Settings::add_settings_instance( new Comcal_Settings_Common() );
}
