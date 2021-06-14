<?php declare(strict_types=1);

use PHPUnit\Framework\TestCase;

function create_testdata_multiday_display() {
    return array(
        create_event_data( 'Z', '2019-12-31', '2020-01-02' ),
        create_event_data( 'A', '2020-01-01', null, '10:00:00' ),
        create_event_data( 'B4', '2020-01-01', '2020-01-05' ),
        create_event_data( 'J1', '2020-01-01', '2020-01-05', null, false ),
        create_event_data( 'C3', '2020-01-02', '2020-01-04', '12:00:00' ),
        create_event_data( 'C1', '2020-01-02', '2020-01-02', '13:00:00' ),
        create_event_data( 'D1', '2020-01-04' ),
        create_event_data( 'E', '2020-02-02' ),
    );
}


/**
 * Test event renderer.
 */
class Test_Table_Event_Renderer extends Comcal_Event_Renderer {
    public function render( Comcal_Event $event, int $day ) : string {
        return $event->get_field( 'title' );
    }
}

/**
 * Test table builder.
 */
class Test_Table_Builder extends Comcal_Table_Builder {
    protected function __construct( $earliest_date = null, $latest_date = null ) {
        parent::__construct( $earliest_date, $latest_date );
        $this->event_renderer = new Test_Table_Event_Renderer();
    }

    protected function get_table_head( Comcal_Date_Time $date ) {
        return "M:{$date->get_month_title()}\n";
    }
    protected function get_table_foot() {
        return "-\n";
    }

    protected function create_day_row( $date_time, $text, $is_new_day = true ) {
        $content = '' === $text ? '' : " E: $text";
        if ( $is_new_day ) {
            $this->html .= "{$date_time->get_date_str()}{$content}\n";
        } else {
            assert( ! empty( $content ), 'Should not be called with no content' );
            $this->html .= "{$content}\n";
        }
    }
}


/**
 * Tests for event iterator.
 */
final class Comcal_Events_Display_Builder_Test extends TestCase {

    public function test_table_builder_all() {
        $iterator = new Comcal_Event_Iterator( create_testdata_multiday_display() );

        $display  = Test_Table_Builder::create_display(
            'Test_Table_Builder',
            $iterator,
            Comcal_Date_Time::from_date_str_time_str( '2019-12-30', '10:00:00' ),
            null
        );
        $expected = <<<XML
M:Dezember 2019
2019-12-30
2019-12-31 E: Z
-
M:Januar 2020
2020-01-01 E: A
 E: B4
 E: J1
 E: Z
2020-01-02 E: C3
 E: C1
 E: B4
 E: Z
2020-01-03 E: C3
 E: B4
2020-01-04 E: D1
 E: C3
 E: B4
2020-01-05 E: B4
2020-01-06
2020-01-07
2020-01-08
2020-01-09
2020-01-10
2020-01-11
2020-01-12
2020-01-13
2020-01-14
2020-01-15
2020-01-16
2020-01-17
2020-01-18
2020-01-19
2020-01-20
2020-01-21
2020-01-22
2020-01-23
2020-01-24
2020-01-25
2020-01-26
2020-01-27
2020-01-28
2020-01-29
2020-01-30
2020-01-31
-
M:Februar 2020
2020-02-01
2020-02-02 E: E
2020-02-03
2020-02-04
2020-02-05
2020-02-06
2020-02-07
2020-02-08
2020-02-09
2020-02-10
2020-02-11
2020-02-12
2020-02-13
2020-02-14
2020-02-15
2020-02-16
2020-02-17
2020-02-18
2020-02-19
2020-02-20
2020-02-21
2020-02-22
2020-02-23
2020-02-24
2020-02-25
2020-02-26
2020-02-27
2020-02-28
2020-02-29
-

XML;
        $this->assertEquals(
            $expected,
            $display->get_html()
        );
    }

    public function test_table_builder_later_start_date() {
        $iterator = new Comcal_Event_Iterator( create_testdata_multiday_display() );

        $display  = Test_Table_Builder::create_display(
            'Test_Table_Builder',
            $iterator,
            Comcal_Date_Time::from_date_str_time_str( '2020-01-01', '10:00:00' ),
            null
        );
        $expected = <<<XML
M:Januar 2020
2020-01-01 E: A
 E: B4
 E: J1
 E: Z
2020-01-02 E: C3
 E: C1
 E: B4
 E: Z
2020-01-03 E: C3
 E: B4
2020-01-04 E: D1
 E: C3
 E: B4
2020-01-05 E: B4
2020-01-06
2020-01-07
2020-01-08
2020-01-09
2020-01-10
2020-01-11
2020-01-12
2020-01-13
2020-01-14
2020-01-15
2020-01-16
2020-01-17
2020-01-18
2020-01-19
2020-01-20
2020-01-21
2020-01-22
2020-01-23
2020-01-24
2020-01-25
2020-01-26
2020-01-27
2020-01-28
2020-01-29
2020-01-30
2020-01-31
-
M:Februar 2020
2020-02-01
2020-02-02 E: E
2020-02-03
2020-02-04
2020-02-05
2020-02-06
2020-02-07
2020-02-08
2020-02-09
2020-02-10
2020-02-11
2020-02-12
2020-02-13
2020-02-14
2020-02-15
2020-02-16
2020-02-17
2020-02-18
2020-02-19
2020-02-20
2020-02-21
2020-02-22
2020-02-23
2020-02-24
2020-02-25
2020-02-26
2020-02-27
2020-02-28
2020-02-29
-

XML;
        $this->assertEquals(
            $expected,
            $display->get_html()
        );
    }

    public function test_table_builder_smaller_date_range() {
        $iterator = new Comcal_Event_Iterator( create_testdata_multiday_display() );

        $display  = Test_Table_Builder::create_display(
            'Test_Table_Builder',
            $iterator,
            Comcal_Date_Time::from_date_str_time_str( '2020-01-02', '10:00:00' ), // start date.
            Comcal_Date_Time::from_date_str_time_str( '2020-01-04', '10:00:00' ), // end date.
        );
        $expected = <<<XML
M:Januar 2020
2020-01-02 E: C3
 E: C1
 E: B4
 E: Z
2020-01-03 E: C3
 E: B4
2020-01-04 E: D1
 E: C3
 E: B4
-

XML;
        $this->assertEquals(
            $expected,
            $display->get_html()
        );
    }

    public function test_table_builder_latest_date_month_break() {
        $iterator = new Comcal_Event_Iterator( create_testdata_multiday_display() );

        $display  = Test_Table_Builder::create_display(
            'Test_Table_Builder',
            $iterator,
            Comcal_Date_Time::from_date_str_time_str( '2019-12-29', '10:00:00' ), // start date.
            Comcal_Date_Time::from_date_str_time_str( '2020-01-01', '10:00:00' ), // end date.
        );
        $expected = <<<XML
M:Dezember 2019
2019-12-29
2019-12-30
2019-12-31 E: Z
-
M:Januar 2020
2020-01-01 E: A
 E: B4
 E: J1
 E: Z
-

XML;
        $this->assertEquals(
            $expected,
            $display->get_html()
        );
    }
}
