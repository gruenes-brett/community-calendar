<?php
/**
 * Format an event as Markdown.
 *
 * @package Community_Calendar
 */

/**
 * Renders event as markdown.
 */
class Comcal_Markdown_Event_Renderer extends Comcal_Event_Renderer {
    public function render( Comcal_Event $event, int $day ) : string {
        $date_time = $event->get_start_date_time( 0 );

        $md  = '*' . Comcal_Markdown_Builder::esc_markdown_all( $date_time->get_humanized_time() ) . '* ';
        $md .= Comcal_Markdown_Builder::esc_markdown_all( $event->get_field( 'organizer' ) );
        $md .= ' \| ';
        $md .= '[' . Comcal_Markdown_Builder::esc_markdown_all( $event->get_field( 'title' ) );
        $md .= '](' . $event->get_field( 'url' ) . ')';

        return $md;
    }
}