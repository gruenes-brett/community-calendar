<?php
/**
 * Output events as Markdown.
 *
 * @package Community_Calendar
 */

/**
 * Creates a Markdown overview of all events in the next week (starting monday)
 */
class Comcal_Markdown_Builder extends Comcal_Default_Display_Builder {
    /**
     * Instantiates the Past_Calendar_Table_Builder singleton and loads the
     * events from the database.
     */
    public static function get_instance( $start_date, $end_date ) {
        $events_iterator = Comcal_Event_Iterator::load_from_database(
            null,
            '',
            $start_date,
            $end_date
        );

        $instance = self::create_display(
            static::class,
            $events_iterator,
            $start_date,
            $end_date
        );
        return $instance;
    }

    public static function esc_markdown_all( $text ): string {
        return str_replace(
            array( '_', '*', '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!' ),
            array( '\_', '\*', '\[', '\]', '\(', '\)', '\~', '\`', '\>', '\#', '\+', '\-', '\=', '\|', '\{', '\}', '\.', '\!' ),
            $text
        );
    }

    public static function esc_markdown_basic( $text ): string {
        return str_replace(
            array( '[', ']', '(', ')', '~', '`', '>', '#', '+', '-', '=', '|', '{', '}', '.', '!' ),
            array( '\[', '\]', '\(', '\)', '\~', '\`', '\>', '\#', '\+', '\-', '\=', '\|', '\{', '\}', '\.', '\!' ),
            $text
        );
    }

    protected function __construct( $earliest_date = null, $latest_date = null ) {
        parent::__construct( $earliest_date, $latest_date );
        $this->event_renderer = new Comcal_Markdown_Event_Renderer();
    }

    public function get_html() {
        if ( null !== $this->latest_date && null !== $this->current_date ) {
            $this->fill_days_between( $this->current_date->get_next_day(), $this->latest_date->get_next_day() );
        }
        return $this->html;
    }
    protected function fill_days_between( $begin_at_date, $end_before_date ) {
        foreach ( $begin_at_date->get_all_dates_until( $end_before_date ) as $this_date ) {
            $this->html .= $this->create_new_day( $this_date ) . '    _\(bis jetzt leider nichts\)_

';
        }
    }

    public function add_event( $event, int $day ) {
        if ( null === $this->current_date && null !== $this->earliest_date ) {
            $this->fill_days_between( $this->earliest_date, $event->get_start_date_time( $day ) );
        } elseif ( null !== $this->current_date ) {
            $this->fill_days_between( $this->current_date->get_next_day(), $event->get_start_date_time( $day ) );
        }
        if ( null === $this->current_date || ! $this->current_date->is_same_day( $event->get_start_date_time( $day ) ) ) {
            $this->current_date = $event->get_start_date_time( $day );
            $this->html        .= $this->create_new_day( $this->current_date );
        }
        $this->html .= $this->event_renderer->render( $event, $day ) . '

';
        $this->current_date = $event->get_start_date_time( $day );
    }

    private function create_new_day( $date_time ) {
        return '🕑 *' . self::esc_markdown_all( $date_time->get_humanized_date() ) . '*

';
    }
}