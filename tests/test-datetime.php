<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once('data/datetime.php');

final class DateTimeTest extends TestCase {
    public function testFromDateStrTimeStr() {
        $dt = Comcal_Date_Time::from_date_str_time_str('2020-10-20', '12:23:45');
        $this->assertEquals('2020-10-20T12:23:45+00:00', $dt->format('c'));
    }

    public function testGetHumanizedDateAndTime() {
        $dt = Comcal_Date_Time::from_date_str_time_str('2020-10-20', '08:00');
        $this->assertEquals('8 Uhr', $dt->get_humanized_time());
        $this->assertEquals('Dienstag, 20.10.', $dt->get_humanized_date());
    }

}