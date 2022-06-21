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

class Bot_Agent_Dummy extends Telegram_Bot_Agent {

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
[Testing Only Website](https://test.com)
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

    public function test_send_or_update_weekly_overview() {
        // arrange.
        set_option(
            'comcal_settings_option_name',
            array(
                'telegram_bot_token_0' => 'abc123',
                'telegram_channel_1'   => '@xyz',
                'schedule_2'           => 'weekly',
            )
        );
        $telegram = new Comcal_Telegram_Messaging();
        $bot      = new Bot_Agent_Dummy();
        $today    = Comcal_Date_Time::from_date_str_time_str( '2019-12-31', '12:12' );  // Tuesday.

        setup_wp_remote_post_mock(
            array(
                'ok'     => true,
                'result' => array(
                    'message_id' => '123',
                    'chat'       => array(
                        'id' => -1234,
                    ),
                ),
            )
        );
        global $testenv_wp_remote_post;
        // act.
        $telegram->send_or_update_weekly_overview(
            $bot,
            $today,
            '',
            ''
        );

        // check wp_remote_post() was called correctly.
        $this->assertEquals(
            'https://api.telegram.org/botabc123/sendMessage',
            $testenv_wp_remote_post['param_url']
        );
        $this->assertEquals(
            json_encode(
                array(
                    'chat_id'                  => '@xyz',
                    'text'                     => "[Testing Only Website](https://test.com)\n🗓 *Woche vom 30\.12\. bis 05\.01\.:*\n\n",
                    'parse_mode'               => 'markdownV2',
                    'disable_web_page_preview' => true,
                )
            ),
            $testenv_wp_remote_post['param_args']['body']
        );
        global $wpdb;
        $table = $wpdb->get_table_data( 'testing_comcal_telegram' );
        $this->assertEquals( 1, count( $table ) );
        $this->assertEquals( '2019-12-30 12:12:00', $table[0]['original_message_date'] );
        setup_wp_remote_post_mock( array() );

        // no update on next day.
        $telegram->send_or_update_weekly_overview(
            $bot,
            $today->get_next_day(),
            '',
            ''
        );

        // check wp_remote_post() was NOT CALLED again.
        $this->assertEquals(
            null,
            $testenv_wp_remote_post['param_url']
        );
        // check table data.
        $table = $wpdb->get_table_data( 'testing_comcal_telegram' );
        $this->assertEquals( 1, count( $table ) );
        $this->assertEquals( '2019-12-30 12:12:00', $table[0]['original_message_date'] );

        // update on next day if message changed.
        setup_wp_remote_post_mock(
            array(
                'ok'     => true,
                'result' => array(
                    'message_id' => '123',
                    'chat'       => array(
                        'id' => -1234,
                    ),
                ),
            )
        );
        $telegram->send_or_update_weekly_overview(
            $bot,
            $today->get_next_day(),
            'some change',
            ''
        );

        // check wp_remote_post() was called with editMessageText.
        $this->assertEquals(
            'https://api.telegram.org/botabc123/editMessageText',
            $testenv_wp_remote_post['param_url']
        );
        $this->assertEquals(
            json_encode(
                array(
                    'chat_id'                  => '@xyz',
                    'message_id'               => 123,
                    'text'                     => "some change[Testing Only Website](https://test.com)\n🗓 *Woche vom 30\.12\. bis 05\.01\.:*\n\n",
                    'parse_mode'               => 'markdownV2',
                    'disable_web_page_preview' => true,
                )
            ),
            $testenv_wp_remote_post['param_args']['body']
        );
        // check table data.
        $table = $wpdb->get_table_data( 'testing_comcal_telegram' );
        $this->assertEquals( 1, count( $table ) );
        $this->assertEquals( '2019-12-30 12:12:00', $table[0]['original_message_date'] );

        // create new message if called next week.
        setup_wp_remote_post_mock(
            array(
                'ok'     => true,
                'result' => array(
                    'message_id' => '124',
                    'chat'       => array(
                        'id' => -1234,
                    ),
                ),
            )
        );
        global $testenv_wp_remote_post;
        // act.
        $telegram->send_or_update_weekly_overview(
            $bot,
            $today->get_next_day( 7 ),
            '',
            ''
        );

        // check wp_remote_post() was called correctly.
        $this->assertEquals(
            'https://api.telegram.org/botabc123/sendMessage',
            $testenv_wp_remote_post['param_url']
        );
        $this->assertEquals(
            json_encode(
                array(
                    'chat_id'                  => '@xyz',
                    'text'                     => "[Testing Only Website](https://test.com)\n🗓 *Woche vom 06\.01\. bis 12\.01\.:*\n\n",
                    'parse_mode'               => 'markdownV2',
                    'disable_web_page_preview' => true,
                )
            ),
            $testenv_wp_remote_post['param_args']['body']
        );

        // validate database table.
        global $wpdb;
        $table = $wpdb->get_table_data( 'testing_comcal_telegram' );
        $this->assertEquals( 2, count( $table ) );
        $this->assertEquals( '2019-12-30 12:12:00', $table[0]['original_message_date'] );
        $this->assertEquals( '2020-01-06 12:12:00', $table[1]['original_message_date'] );
    }
}

class WP_Error_Dummy extends Exception {
}

function setup_wp_remote_post_mock( $body_json ) {
    $GLOBALS['testenv_wp_remote_post']['return_value'] = array(
        'body' => json_encode( $body_json ),
    );

    global $testenv_wp_remote_post;
    $testenv_wp_remote_post['param_url']  = null;
    $testenv_wp_remote_post['param_args'] = null;

    if ( ! function_exists( 'wp_remote_post' ) ) {
        function wp_remote_post( $url, $args ) {
            global $testenv_wp_remote_post;
            $testenv_wp_remote_post['param_url']  = $url;
            $testenv_wp_remote_post['param_args'] = $args;
            return $testenv_wp_remote_post['return_value'];
        }

        function is_wp_error( $thing ) {
            return $thing instanceof WP_Error_Dummy;
        }

        function wp_remote_retrieve_body( $response ) {
            return $response['body'];
        }
    }
}
