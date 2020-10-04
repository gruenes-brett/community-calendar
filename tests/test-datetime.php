<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

require_once('data/datetime.php');

final class DateTimeTest extends TestCase {
    public function testFromDateStrTimeStr() {
        $dt = comcal_DateTime::fromDateStrTimeStr('2020-10-20', '12:23:45');
        $this->assertEquals('2020-10-20T12:23:45+00:00', $dt->format('c'));
    }

    public function testGetHumanizedDateAndTime() {
        $dt = comcal_DateTime::fromDateStrTimeStr('2020-10-20', '08:00');
        $this->assertEquals('8 Uhr', $dt->getHumanizedTime());
        $this->assertEquals('Dienstag, 20.10.', $dt->getHumanizedDate());
    }

}