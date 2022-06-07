<?php
/**
 * Implementation of the Telegram bot logic.
 *
 * @package comcal_telegram
 */

 /**
  * Telegram Bot Agent.
  */
class Telegram_Bot_Agent {
    public function __construct() {

    }

    private function get_url( $endpoint ) {
        $token = Telegram_Options::get_option_value( 'bot_token' );
        return "https://api.telegram.org/bot$token/$endpoint";
    }

    private function post( $endpoint, array $data ) {
        $args = array(
            'body'        => json_encode( $data ),
            'headers'     => array(
                'Content-Type' => 'application/json; charset=utf-8',
            ),
            'timeout'     => 15,
            'blocking'    => true,
            'data_format' => 'body',
        );
        $response = wp_remote_post( $this->get_url( $endpoint ), $args );
        if ( is_wp_error( $response ) ) {
            return $response->errors;
        } else {
            $body = wp_remote_retrieve_body( $response );
            return json_decode( $body );
        }
    }

    public function send_message_to_channel( $message ) {
        $data = array(
            'chat_id'    => Telegram_Options::get_option_value( 'channel' ),
            'text'       => $message,
            'parse_mode' => 'markdownV2',
        );
        $this->post( 'sendMessage', $data );
    }
}
