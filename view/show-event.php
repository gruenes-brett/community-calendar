<?php

abstract class comcal_EventRenderer {
    abstract function render( comcal_Event $event) : string;

    function getEditLink( $event ) {
        if ( comcal_currentUserCanSetPublic() ) {
            return "<a class='editEvent' eventId='{$event->getField('eventId')}'>edit</a> &mdash; ";
        }
        return '';
    }
}


class comcal_DefaultEventRenderer extends comcal_EventRenderer {
    public function render( comcal_Event $event ) : string {
        $title     = $event->getField( 'title' );
        $time      = $event->getDateTime()->getPrettyTime();
        $location  = $event->getField( 'location' );
        $url       = $event->getField( 'url' );
        $edit_link = $this->getEditLink( $event );
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

        $editControls = $this->getEditLink( $event );
        $publicClass = '';
        if ( $event->getField( 'public' ) == 0 ) {
            $publicClass = 'notPublic';
        }
        return <<<XML
        <table class='event $publicClass' eventId="{$event->getField('eventId')}"><tbody>
            <tr>
                <td class='time'>{$event->getDateTime()->getPrettyTime()}</td>
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
        $dateTime = $event->getDateTime();
        $md = '**' . $dateTime->getHumanizedTime() . '** ';
        $md .= $event->getField( 'organizer' );
        $md .= ' | ';
        $md .= $event->getField( 'title' );
        $md .= ' ';
        $md .= $event->getField( 'url' );

        return $md;
    }
}
