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
     * @return string HTML.
     */
    abstract public function render( Comcal_Event $event ) : string;

    /**
     * Produces a button that shows the edit event dialog.
     *
     * @param Comcal_Event $event Event instance.
     * @return string HTML.
     */
    protected function get_edit_link( $event ) {
        if ( comcal_current_user_can_set_public() ) {
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
    public function get_show_popup_javascript_call( $event, $prevent_default = true ) {
        return "comcal_showEventPopup(event, '{$event->get_field('eventId')}', {$prevent_default});";
    }
}

/**
 * Simple event renderer.
 */
class Comcal_Default_Event_Renderer extends Comcal_Event_Renderer {
    public function render( Comcal_Event $event ) : string {
        $title     = $event->get_field( 'title' );
        $time      = $event->get_start_date_time()->get_pretty_time();
        $location  = $event->get_field( 'location' );
        $url       = $event->get_field( 'url' );
        $edit_link = $this->get_edit_link( $event );
        return <<<XML
      <article>
        <h2><a href="$url" target="_blank">$title</a></h2>
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
    public function render( Comcal_Event $event ) : string {
        $edit_controls = $this->get_edit_link( $event );
        $public_class  = '';
        if ( $event->get_field( 'public' ) == 0 ) {
            $public_class = 'notPublic';
        }
        return <<<XML
        <table class='event $public_class' eventId="{$event->get_field('eventId')}"><tbody>
            <tr>
                <td class='time'>{$event->get_start_date_time()->get_pretty_time()}</td>
                <td class='title'>{$event->get_field('title')}</td>
            </tr>
            <tr>
                <td>$edit_controls</td>
                <td class='organizer'>{$event->get_field('organizer')}</td>
            </tr>
        </tbody></table>
XML;
    }

}

/**
 * Renders event as markdown.
 */
class Comcal_Markdown_Event_Renderer extends Comcal_Event_Renderer {
    public function render( Comcal_Event $event ) : string {
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
