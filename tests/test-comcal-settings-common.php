<?php
/**
 * Implementation of unit tests.
 *
 * @package Community_Calendar
 */

declare(strict_types=1);
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for Comcal_Settings_Common.
 */
class Comcal_Settings_Common_Test extends TestCase {
    public function test_is_email_blacklisted() {
        set_option(
            'comcal_settings_option_name',
            array(
                'email_blacklist_0' => '',
            )
        );
        $this->assertFalse( Comcal_Settings_Common::is_email_blacklisted( 'asdf' ) );

        set_option(
            'comcal_settings_option_name',
            array(
                'email_blacklist_0' => 'abc123',
            )
        );
        $this->assertFalse( Comcal_Settings_Common::is_email_blacklisted( 'abc' ) );
        $this->assertTrue( Comcal_Settings_Common::is_email_blacklisted( 'abc123' ) );
        $this->assertTrue( Comcal_Settings_Common::is_email_blacklisted( ' ABC123   ' ) );

        set_option(
            'comcal_settings_option_name',
            array(
                'email_blacklist_0' => 'abc123, qwer@xy.z',
            )
        );
        $this->assertFalse( Comcal_Settings_Common::is_email_blacklisted( 'abc' ) );
        $this->assertTrue( Comcal_Settings_Common::is_email_blacklisted( 'abc123' ) );
        $this->assertTrue( Comcal_Settings_Common::is_email_blacklisted( ' ABC123   ' ) );
        $this->assertTrue( Comcal_Settings_Common::is_email_blacklisted( 'qwer@xy.z' ) );
        $this->assertTrue( Comcal_Settings_Common::is_email_blacklisted( ' QWEr@xy.z ' ) );

    }
}
