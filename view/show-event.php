<?php

abstract class comcal_EventRenderer {
    abstract function render( comcal_Event $event) : string;

    function get_edit_link( $event ) {
        if ( comcal_currentUserCanSetPublic() ) {
            return "<a class='editEvent' eventId='{$event->getField('eventId')}'>edit</a> &mdash; ";
        }
        return '';
    }
}


class comcal_DefaultEventRenderer extends comcal_EventRenderer {
    public function render( comcal_Event $event ) : string {
        $title     = $event->getField( 'title' );
        $time      = $event->get_date_time()->get_pretty_time();
        $location  = $event->getField( 'location' );
        $url       = $event->getField( 'url' );
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

class comcal_TableEventRenderer extends comcal_EventRenderer {
    function render( comcal_Event $event ) : string {

        $editControls = $this->get_edit_link( $event );
        $publicClass = '';
        if ( $event->getField( 'public' ) == 0 ) {
            $publicClass = 'notPublic';
        }
        return <<<XML
        <table class='event $publicClass' eventId="{$event->getField('eventId')}"><tbody>
            <tr>
                <td class='time'>{$event->get_date_time()->get_pretty_time()}</td>
                <td class='title'>{$event->getField('title')}</td>
            </tr>
            <tr>
                <td>$editControls</td>
                <td class='organizer'>{$event->getField('organizer')}</td>
            </tr>
        </tbody></table>
XML;
    }

}


class comcal_MarkdownEventRenderer extends comcal_EventRenderer {
    function render( comcal_Event $event ) : string {
        $date_time = $event->get_date_time();
        $md = '**' . $date_time->get_humanized_time() . '** ';
        $md .= $event->getField( 'organizer' );
        $md .= ' | ';
        $md .= $event->getField( 'title' );
        $md .= ' ';
        $md .= $event->getField( 'url' );

        return $md;
    }
}
