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

        // TODO: gather events of next day and send to channel
        // $bot = new Telegram_Bot_Agent();
        // $bot->send_message_to_channel( 'some text' );
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
