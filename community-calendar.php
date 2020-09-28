<?php
/**
 * @package Community_Calendar
 * @version 0.0.1
 */
/*
Plugin Name: Community Calendar
Plugin URI: https://github.com/joergrs/community-calendar
Description: This plugin allows users to submit event to the website and display them in a calendar
Author: Joerg Schroeter
Version: 0.0.1
Requires at least: 5.5
Requires PHP: 7.2
Author URI: https://github.com/joergrs
License: GPL v3
*/

// Make sure we don't expose any info if called directly
if ( !function_exists( 'add_action' ) ) {
	echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
	exit;
}

define( 'EVTCAL_VERSION', '0.0.1' );
define( 'EVTCAL__MINIMUM_WP_VERSION', '5.2' );
define( 'EVTCAL__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EVTCAL__PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

require_once( EVTCAL__PLUGIN_DIR . 'buttons.php' );
require_once( EVTCAL__PLUGIN_DIR . 'editevent.php' );
require_once( EVTCAL__PLUGIN_DIR . 'datetime.php' );
require_once( EVTCAL__PLUGIN_DIR . 'persistence.php' );
require_once( EVTCAL__PLUGIN_DIR . 'event.php' );
require_once( EVTCAL__PLUGIN_DIR . 'category.php' );
require_once( EVTCAL__PLUGIN_DIR . 'editcategory.php' );
require_once( EVTCAL__PLUGIN_DIR . 'showevent.php' );
require_once( EVTCAL__PLUGIN_DIR . 'showmonth.php' );
require_once( EVTCAL__PLUGIN_DIR . 'showcategories.php' );

/**
 * Enqueue scripts and styles.
 */
function comcal_scripts() {
	// $JQUERY_VERSION = '3.4.1';
	$SCRIPT_VERSION = EVTCAL_VERSION . '-' . time();
	wp_enqueue_script( 'comcal_event_js', EVTCAL__PLUGIN_URL  . 'public/js/event.js', array('jquery', 'jquery-form'), $SCRIPT_VERSION, true);
	wp_enqueue_script( 'comcal_edit_js', EVTCAL__PLUGIN_URL  . 'public/js/edit.js', array('jquery', 'jquery-form'), $SCRIPT_VERSION, true);
	wp_enqueue_script( 'comcal_show_js', EVTCAL__PLUGIN_URL  . 'public/js/show.js', array('jquery', 'jquery-form'), $SCRIPT_VERSION, true);
	wp_enqueue_script( 'comcal_editcats_js', EVTCAL__PLUGIN_URL  . 'public/js/editcats.js', array('jquery', 'jquery-form'), $SCRIPT_VERSION, true);
}
add_action( 'wp_enqueue_scripts', 'comcal_scripts' );

function comcal_styles() {
	$STYLE_VERSION = EVTCAL_VERSION . '-' . time();
	wp_enqueue_style('comcal_css', EVTCAL__PLUGIN_URL . 'public/css/comcal.css', array(), $STYLE_VERSION);
}
add_action('wp_print_styles', 'comcal_styles');

function comcal_activation() {
	// initialize/update database table on activation
	comcal_initTables();
}
register_activation_hook( __FILE__, 'comcal_activation' );

function comcal_deactivation() {
	// tbd
}
register_deactivation_hook( __FILE__, 'comcal_deactivation' );