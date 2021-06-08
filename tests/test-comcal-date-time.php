<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

final class Comcal_Date_Time_Test extends TestCase {
    public function testFromDateStrTimeStr() {
        $dt = Comcal_Date_Time::from_date_str_time_str( '2020-10-20', '12:23:45' );
        $this->assertEquals( '2020-10-20T12:23:45+00:00', $dt->format( 'c' ) );
    }

    public function testGetHumanizedDateAndTime() {
        $dt = Comcal_Date_Time::from_date_str_time_str( '2020-10-20', '08:00' );
        $this->assertEquals( '8 Uhr', $dt->get_humanized_time() );
        $this->assertEquals( 'Dienstag, 20.10.', $dt->get_humanized_date() );
    }

    public function test_is_in_date_range() {
        $dt   = Comcal_Date_Time::from_date_str_time_str( '2020-10-20', '10:00' );
        $low  = Comcal_Date_Time::from_date_str_time_str( '2020-10-20', '08:00' );
        $high = Comcal_Date_Time::from_date_str_time_str( '2020-10-20', '06:00' );

        $this->assertTrue( $dt->is_in_date_range( null, null ) );
        $this->assertTrue( $dt->is_in_date_range( $low, null ) );
        $this->assertTrue( $dt->is_in_date_range( null, $high ) );
        $this->assertTrue( $dt->is_in_date_range( $low, $high ) );

        $this->assertFalse( $dt->is_in_date_range( $low->get_next_day(), null ) );
        $this->assertFalse( $dt->is_in_date_range( null, $high->get_prev_day() ) );

    }

}
