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
        $pretty_event = new Comcal_Pretty_Event( $event, false );

        $organizer = Comcal_Markdown_Builder::esc_markdown_all( $pretty_event->organizer );
        if ( $organizer ) {
            $organizer .= ' \| ';
        }

        $md  = '*' . Comcal_Markdown_Builder::esc_markdown_all( $pretty_event->humanized_time ) . '* ';
        $md .= $organizer;
        $md .= '[' . Comcal_Markdown_Builder::esc_markdown_all( $pretty_event->title );
        $md .= '](' . $pretty_event->url_or_permalink . ')';

        return $md;
    }
}