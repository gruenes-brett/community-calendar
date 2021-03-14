<?php
/**
 * Common helper functions.
 *
 * @package Community_Calendar
 */

/**
 * Creates highlighted text.
 *
 * @param string $text Text to show.
 * @return string HTML.
 */
function comcal_make_error_box( $text ) {
    return "<p style='color:red'>$text</p>";
}
