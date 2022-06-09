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
        $args     = array(
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
            return $response;
        } else {
            $body          = wp_remote_retrieve_body( $response );
            $json_response = json_decode( $body );
            if ( ! $json_response->ok ) {
                throw new Exception( $json_response->description );
            }
            return $json_response;
        }
    }

    public function send_message( string $message ) {
        $data = array(
            'chat_id'    => Telegram_Options::get_option_value( 'channel' ),
            'text'       => $message,
            'parse_mode' => 'markdownV2',
        );
        return $this->post( 'sendMessage', $data );
    }

    public function update_message( int $message_id, string $message ) {
        $data = array(
            'chat_id'    => Telegram_Options::get_option_value( 'channel' ),
            'message_id' => $message_id,
            'text'       => $message,
            'parse_mode' => 'markdownV2',
        );
        return $this->post( 'editMessageText', $data );
    }
}
