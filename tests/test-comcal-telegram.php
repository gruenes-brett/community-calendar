<?php
/**
 * Implementation of unit tests.
 *
 * @package comcal_telegram
 */

declare(strict_types=1);
use PHPUnit\Framework\TestCase;


function create_telegram_testdata() {
    return array(
        create_event_data( 'su', '2019-12-29', '2020-01-02' ),  // Sunday.
        create_event_data( 'mo', '2019-12-30', '2020-01-02' ),  // Monday.
        create_event_data( 'we1', '2020-01-01', null, '10:00:00' ),  // Wednesday.
        create_event_data( 'we2', '2020-01-01', '2020-01-05' ),
        create_event_data( 'we3', '2020-01-01', '2020-01-05', '22:34:00', false ),
        create_event_data( 'th1', '2020-01-02', '2020-01-04', '17:00:12' ),  // Thursday.
        create_event_data( 'th2', '2020-01-02', '2020-01-02', '18:00:00', false, 'org' ),
        create_event_data( 'sa', '2020-01-04' ), // Saturday.
        create_event_data( 'mo2', '2020-01-06' ), // Monday.
        create_event_data( 'february', '2020-02-02' ),  // out of range.
    );
}

/**
 * Unit tests for Comcal_Telegram_Messaging etc.
 */
final class Comcal_Telegram_Test extends TestCase {
    public function test_get_events_markdown_from_iterator() {
        $days = array(
            Comcal_Date_Time::from_date_str_time_str( '2019-12-29', '12:12' ),  // Sunday.
            Comcal_Date_Time::from_date_str_time_str( '2019-12-30', '12:12' ),  // Monday.
            Comcal_Date_Time::from_date_str_time_str( '2019-12-31', '12:12' ),  // Tuesday.
            Comcal_Date_Time::from_date_str_time_str( '2020-01-01', '12:12' ),  // Wednesday.
            Comcal_Date_Time::from_date_str_time_str( '2020-01-02', '12:12' ),  // Thursday.
            Comcal_Date_Time::from_date_str_time_str( '2020-01-03', '12:12' ),  // Friday.
            Comcal_Date_Time::from_date_str_time_str( '2020-01-04', '12:12' ),  // Saturday.
        );

        $expected = <<<XML
🗓 *Woche vom 30\.12\. bis 05\.01\.:*

🕑 *Montag, 30\.12\.*

*12 Uhr* [mo](https://mo.com)

🕑 *Dienstag, 31\.12\.*

    _\(bis jetzt noch nichts\)_

🕑 *Mittwoch, 01\.01\.*

*10 Uhr* [we1](https://we1.com)

*12 Uhr* [we2](https://we2.com)

*22:34 Uhr* [we3](https://we3.com)

🕑 *Donnerstag, 02\.01\.*

*17 Uhr* [th1](https://th1.com)

*18 Uhr* org \| [th2](https://th2.com)

🕑 *Freitag, 03\.01\.*

    _\(bis jetzt noch nichts\)_

🕑 *Samstag, 04\.01\.*

*12 Uhr* [sa](https://sa.com)

🕑 *Sonntag, 05\.01\.*

    _\(bis jetzt noch nichts\)_


XML;

        foreach ( $days as $day ) {
            list( $start, $end ) = Comcal_Telegram_Messaging::get_weekly_date_range( $day );
            $iterator            = new Comcal_Event_Iterator( create_telegram_testdata() );

            $telegram = new Comcal_Telegram_Messaging();
            $markdown = $telegram->get_events_markdown_from_iterator( $start, $end, $iterator );
            $this->assertEquals( $expected, $markdown, "failed for {$day->get_humanized_date()} " );
        }
    }
}
