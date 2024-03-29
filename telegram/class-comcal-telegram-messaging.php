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
        $telegram = new Comcal_Telegram_Messaging();
        $telegram->send_or_update_weekly_overview(
            new Telegram_Bot_Agent(),
            Comcal_Date_Time::now(),
            '',
            ''
        );
    }

    public function send_or_update_weekly_overview(
        Telegram_Bot_Agent $bot_agent,
        Comcal_Date_Time $today,
        string $header_markdown = '',
        string $footer_markdown = ''
    ) {
        if ( ! Telegram_Options::is_weekly_enabled() ) {
            return;
        }

        list( $monday, $sunday ) = $this->get_weekly_date_range( $today );

        $old_message = Comcal_Telegram_Data::query_from_original_message_date( $monday );
        $messaging   = new self();
        $new_text    = $header_markdown . $messaging->get_events_markdown( $monday ) . $footer_markdown;

        if ( null === $old_message ) {
            // Post a new message.
            $response = $bot_agent->send_message( $new_text );
            if ( $response->ok ) {
                $new_message = Comcal_Telegram_Data::create_from_response( $monday, $new_text, $response );
                $new_message->store();
            }
        } elseif ( $new_text !== $old_message->get_last_message_content() ) {
            // Update an old message.
            try {
                $response = $bot_agent->update_message( $old_message->get_message_id(), $new_text );
                if ( $response->ok ) {
                    $old_message->update_from_response( $new_text, $response );
                    $old_message->store();
                }
            } catch ( TelegramException $e ) {
                if ( in_array(
                    $e->getMessage(),
                    array(
                        'Bad Request: MESSAGE_ID_INVALID',
                        'Bad Request: message to edit not found',
                    ),
                    true
                )
                ) {
                    // Original message was removed from Telegram Channel. Delete from database.
                    $old_message->delete();
                } else {
                    throw $e;
                }
            }
        }
    }

    public static function get_weekly_date_range( Comcal_Date_Time $today ): array {
        if ( $today->is_sunday() ) {
            $monday = $today->get_next_day( 1 );
        } else {
            $monday = $today->get_last_monday();
        }
        return array( $monday, $monday->get_next_day( 6 ) );
    }

    private function get_events_markdown( Comcal_Date_Time $today ): string {
        list( $start, $end ) = self::get_weekly_date_range( $today );
        $events_iterator     = Comcal_Event_Iterator::load_from_database(
            null,
            '',
            $start,
            $end
        );
        return $this->get_events_markdown_from_iterator( $start, $end, $events_iterator );
    }

    public function get_events_markdown_from_iterator( $start, $end, $iterator ) {
        $builder = Comcal_Markdown_Builder::create_from_iterator( $start, $end, $iterator );
        $header  = $this->get_header_markdown( $start, $end );
        return $header . $builder->get_html();
    }

    private function get_header_markdown( Comcal_Date_Time $start, Comcal_Date_Time $end ): string {
        $pretty_start = Comcal_Markdown_Builder::esc_markdown_all( $start->format( 'd.m.' ) );
        $pretty_end   = Comcal_Markdown_Builder::esc_markdown_all( $end->format( 'd.m.' ) );
        $title        = Comcal_Info::get()->get_website_title();
        $url          = Comcal_Info::get()->get_website_url();
        $header       = "[$title]($url)\n";
        $header      .= "🗓 *Woche vom $pretty_start bis $pretty_end:*\n\n";
        return $header;
    }
}

// TODO: use a useful schedule.
add_filter( 'cron_schedules', 'comcal_add_telegram_cron_interval' );
function comcal_add_telegram_cron_interval( $schedules ) {
    $interval = ( defined( 'WP_DEBUG' ) && WP_DEBUG ) ? 15 : 15 * 60;

    $schedules['telegram_channel_schedule'] = array(
        'interval' => $interval,
        'display'  => "Every $interval seconds",
    );
    return $schedules;
}
add_action( 'comcal_telegram_daily', array( 'Comcal_Telegram_Messaging', 'trigger_daily_send' ) );

function comcal_activate_cron() {
    if ( ! wp_next_scheduled( 'comcal_telegram_daily' ) ) {
        wp_schedule_event( time(), 'telegram_channel_schedule', 'comcal_telegram_daily' );
    }
}
add_action( 'wp', 'comcal_activate_cron' );
