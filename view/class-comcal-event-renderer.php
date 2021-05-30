<?php
/**
 * Functions for rendering events.
 *
 * @package Community_Calendar
 */

/**
 * Base class for event renderes.
 */
abstract class Comcal_Event_Renderer {

    /**
     * Returns HTML for the given event.
     *
     * @param Comcal_Event $event Event instance.
     * @param int          $day This is the n-th day instance of this event (starting at 0).
     *                     Check $event->get_number_of_days() for total amount of days.
     * @return string HTML.
     */
    abstract public function render( Comcal_Event $event, int $day ) : string;

    /**
     * Produces a button that shows the edit event dialog.
     *
     * @param Comcal_Event $event Event instance.
     * @return string HTML.
     */
    protected static function get_edit_link( $event ) {
        if ( $event->current_user_can_edit() ) {
            return "<a class='editEvent' eventId='{$event->get_field('eventId')}'>edit</a> &mdash; ";
        }
        return '';
    }

    /**
     * Returns a JavaScript call that initiates the event display popup.
     *
     * @param Comcal_Event $event Event instance.
     * @param bool         $prevent_default Prevent default click action.
     * @return string JavaScript function call.
     */
    public static function get_show_popup_javascript_call( $event, $prevent_default = true ) {
        return "comcal_showEventPopup(event, '{$event->get_field('eventId')}', {$prevent_default});";
    }
}

/**
 * Simple event renderer.
 */
class Comcal_Default_Event_Renderer extends Comcal_Event_Renderer {
    public function render( Comcal_Event $event, int $day ) : string {
        $title    = $event->get_field( 'title' );
        $time     = $event->get_start_date_time()->get_pretty_time();
        $location = $event->get_field( 'location' );

        $edit_link  = $this->get_edit_link( $event );
        $show_popup = $this->get_show_popup_javascript_call( $event );

        return <<<XML
      <article>
        <h2><a href="" onclick="$show_popup">$title</a></h2>
        <section class="meta">
          $edit_link $time, $location
        </section>
      </article>
XML;
    }
}

/**
 * Renders an event as table.
 */
class Comcal_Table_Event_Renderer extends Comcal_Event_Renderer {
    public function render( Comcal_Event $event, int $day ) : string {
        $edit_link    = $this->get_edit_link( $event );
        $show_popup   = $this->get_show_popup_javascript_call( $event );
        $public_class = '';
        if ( 0 === $event->get_field( 'public' ) ) {
            $public_class = 'notPublic';
        }
        return <<<XML
        <table class='event $public_class' eventId="{$event->get_field('eventId')}"
               onclick="$show_popup">
            <tbody>
                <tr>
                    <td class='time'>{$event->get_start_date_time()->get_pretty_time()}</td>
                    <td class='title'>{$event->get_field('title')}</td>
                </tr>
                <tr>
                    <td>$edit_link</td>
                    <td class='organizer'>{$event->get_field('organizer')}</td>
                </tr>
            </tbody>
        </table>
XML;
    }

}

/**
 * Renders event as markdown.
 */
class Comcal_Markdown_Event_Renderer extends Comcal_Event_Renderer {
    public function render( Comcal_Event $event, int $day ) : string {
        $date_time = $event->get_start_date_time();

        $md  = '**' . $date_time->get_humanized_time() . '** ';
        $md .= $event->get_field( 'organizer' );
        $md .= ' | ';
        $md .= $event->get_field( 'title' );
        $md .= ' ';
        $md .= $event->get_field( 'url' );

        return $md;
    }
}
