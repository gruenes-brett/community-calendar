<?php
/**
 * Functions for rendering category buttons
 *
 * @package Community_Calendar
 */

 /**
  * Creates a pair of background and foreground (text) color strings
  * that will always be the same for the same input string
  *
  * @param string $name Some text.
  * @return array ($background, $foreground)
  */
function comcal_create_unique_colors( $name ) {
    $seed = 0;
    $i    = 0;
    foreach ( str_split( $name ) as $chr ) {
        $seed += ord( $chr ) * ( $i + 1 );
        $i++;
    }
    wp_rand();
    // We need reproducible randomness.
    srand( $seed );
    $background = array( rand( 0, 0xFF ), rand( 0, 0xFF ), rand( 0, 0xff ) );
    if ( array_sum( $background ) < 500 ) {
        $foreground = 'white';
    } else {
        $foreground = 'black';
    }
    return array(
        vsprintf( '#%02x%02x%02x', $background ),
        $foreground,
    );
}

function comcal_category_button( $category_id, $label, $active ) {
    if ( null === $category_id ) {
        $url = '?';
    } else {
        $url = "?comcal_category=$category_id";
    }
    list($background, $foreground) = comcal_create_unique_colors( $label );

    $class = $active ? 'comcal-category-label comcal-active' : 'comcal-category-label comcal-inactive';
    return "<a href='$url' class='$class'"
    . "style='background-color: $background; color: $foreground;' class='$class'>"
    . "$label</a> ";
}

function comcal_get_category_buttons( $active_category = null ) {
    $cats = Comcal_Category::get_all();
    $html = '<p class="comcal-categories">';

    $html .= comcal_category_button( null, 'Alles anzeigen', null === $active_category );

    $active_category_id = '';
    if ( null !== $active_category ) {
        $active_category_id = $active_category->get_field( 'categoryId' );
    }

    foreach ( $cats as $c ) {
        $category_id = $c->get_field( 'categoryId' );
        $name        = $c->get_field( 'name' );
        $html       .= comcal_category_button(
            $category_id,
            $name,
            $active_category_id === $c->get_field( 'categoryId' )
        );
    }

    return $html . '<p/>';
}
