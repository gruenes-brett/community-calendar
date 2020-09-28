<?php

// [floatingButtons target="targetDivId"]btn-class-1 btn-class-2[/floatingButtons]
function comcal_floatingButtons_func($atts, $content=null) {
	$a = shortcode_atts( array(
	), $atts );

	$out = "<div class='comcal-floating-button-container'>";
	$buttonClasses = ['addEvent', 'scrollToToday'];
	if (comcal_currentUserCanSetPublic()) {
		$buttonClasses[] = 'editCategories';
	}
	$index = 0;
	foreach ($buttonClasses as $class) {
		if ($index != 0) {
			$orderClass = 'other';
			$bottomPos = 16 + 64 + 8 + ($index-1) * 56;
		} else {
			$orderClass = 'first';
			$bottomPos = 16;
		}
		$addStyle = "bottom: {$bottomPos}px;";
		$out .= "<button class='comcal-floating-button btn $class $orderClass' style='$addStyle'>";
		if ($index == 0) {
			$imgSrc = EVTCAL__PLUGIN_URL . 'public/images/plus.png';
			$out .= "<img class='$class' src='$imgSrc'></img>";
		} else {
			$out .= "<span class='$class'></span>";
		}
		$out .= "</button>";
		$index++;
	}
	return $out . '</div>';

}
add_shortcode( 'community-calendar-buttons', 'comcal_floatingButtons_func' );