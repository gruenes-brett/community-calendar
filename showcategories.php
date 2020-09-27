<?php
/*
 * Functions for rendering category buttons
 */

 /**
  * Creates a pair of background and foreground (text) color strings
  * that will always be the same for the same input string
  *
  * return: array($background, $foreground)
  */
function evtcal_createUniqueColors($name) {
    $seed = 0;
    $i = 0;
    foreach (str_split($name) as $chr) {
        $seed += ord($chr) * ($i + 1);
        $i++;
    }
    srand($seed);
    $background = array(rand(0, 0xFF), rand(0, 0xFF), rand(0, 0xff));
    if (array_sum($background) < 500) {
        $foreground = 'white';
    } else {
        $foreground = 'black';
    }
    return array(
        vsprintf('#%02x%02x%02x', $background),
        $foreground
    );
}

function _evtcal_categoryButton($categoryId, $label, $active) {
    if ($categoryId === null) {
        $url = '?';
    } else {
        $url = "?evtcal_category=$categoryId";
    }
    list($background, $foreground) = evtcal_createUniqueColors($label);
    $class = $active ? 'evtcal-category-label evtcal-active' : 'evtcal-category-label evtcal-inactive';
    return "<a href='$url' class='$class'"
    . "style='background-color: $background; color: $foreground;' class='$class'>"
    . "$label</a> ";
}

function evtcal_getCategoryButtons($activeCategory=null) {
    $cats = evtcal_Category::getAll();
    $html = '<p class="evtcal-categories">';

    $html .= _evtcal_categoryButton(null, 'Alles anzeigen', $activeCategory===null);

    $activeCategoryId = '';
    if ($activeCategory !== null) {
        $activeCategoryId = $activeCategory->getField('categoryId');
    }

    foreach ($cats as $c) {
        $categoryId = $c->getField('categoryId');
        $name = $c->getField('name');
        $html .= _evtcal_categoryButton(
            $categoryId,
            $name,
            $activeCategoryId === $c->getField('categoryId')
        );
    }

    return $html . '</>';
}