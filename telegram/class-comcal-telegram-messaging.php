<?php
/**
 * Send events overview to Telegram channel.
 *
 * @package Community_Calendar
 */

/**
 * Prepares and sends event overviews.
 */
class Comcal_Telegram_Messaging {
    public static function trigger_daily_send() {
        $schedule = Telegram_Options::get_option_value( 'schedule' );
        if ( ! Telegram_Options::is_configured() || false === strstr( $schedule, 'daily' ) ) {
            return;
        }

        $bot = new Telegram_Bot_Agent();
        // $bot->send_message_to_channel( self::get_events_markdown( 'weekly' ) );
        // $bot->send_message_to_channel('blubb');
    }

    private static function get_start_end_dates( $schedule ): array {
        if ( 'daily' === $schedule ) {
            $today = Comcal_Date_Time::now();
            return array( $today, $today );
        } elseif ( 'weekly' === $schedule ) {
            $today = Comcal_Date_Time::now();
            return array( $today, $today->get_next_day( 6 ) );
        } else {
            return array( null, null );
        }
    }

    private static function get_events_markdown( $schedule ): string {
        list( $start, $end ) = self::get_start_end_dates( $schedule );
        if ( null === $start ) {
            return "bad schedule $schedule";
        }
        $builder = Comcal_Markdown_Builder::get_instance( $start, $end );
        $header  = self::get_header_markdown( $schedule );
        return $header . $builder->get_html();
    }

    private static function get_header_markdown( $schedule ): string {
        list( $start, $end ) = self::get_start_end_dates( $schedule );
        if ( null === $start ) {
            return "bad schedule $schedule";
        }
        $pretty_start = Comcal_Markdown_Builder::esc_markdown_all( $start->format( 'd.m.' ) );
        $pretty_end   = Comcal_Markdown_Builder::esc_markdown_all( $end->format( 'd.m.' ) );
        $header       = '🗓 ';
        if ( 'daily' === $schedule ) {
            $header .= "*Veranstaltungen am $pretty_start:*\n\n";
        } elseif ( 'weekly' === $schedule ) {
            $header .= "*Woche vom $pretty_start bis $pretty_end:*\n\n";
        }
        return $header;
    }
}

// TODO: use a useful schedule
add_filter( 'cron_schedules', 'example_add_cron_interval' );
function example_add_cron_interval( $schedules ) {
    $schedules['fifteen_secs'] = array(
        'interval' => 15,
        'display'  => esc_html__( 'Every Five Seconds' ),
    );
    return $schedules;
}

$dis = Telegram_Options::is_configured();
add_action( 'comcal_telegram_daily', array( 'Comcal_Telegram_Messaging', 'trigger_daily_send' ) );

function comcal_activate_cron() {
    if ( ! wp_next_scheduled( 'comcal_telegram_daily' ) ) {
        wp_schedule_event( time(), 'fifteen_secs', 'comcal_telegram_daily' );
    }
}
add_action( 'wp', 'comcal_activate_cron' );
