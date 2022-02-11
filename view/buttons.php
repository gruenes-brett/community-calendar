<?php
/**
 * Definition of floating buttons short code.
 *
 * @package Community_Calendar
 */

/**
 * Create a single floating button that scrolls the page.
 *
 * See comcal-basic.js for the implementation of the scrolling behavior.
 *
 * @param String $scroll_option Defines the scroll target:
 *                              'scrollToTop': Scroll to pixel position 0
 *                              'scrollToToday': Scroll to the position of an element with class 'today'.
 * @param String $img_src Image URL that is to be shown on the button.
 * @return String HTML of the button (incl. its container)
 */
function comcal_create_single_floating_button( $scroll_option, $img_src ) {
    return <<<XML
    <div class='comcal-floating-button-container'>
        <button class='comcal-floating-button btn $scroll_option first'>
            <img class='$scroll_option' src='$img_src'></img>
        </button>
    </div>
XML;
}
