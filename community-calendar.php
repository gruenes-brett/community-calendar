<?php
/**
 * Community Calender plugin
 *
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

// Make sure we don't expose any info if called directly.
if ( ! function_exists( 'add_action' ) ) {
    echo 'Hi there!  I\'m just a plugin, not much I can do when called directly.';
    exit;
}

define( 'EVTCAL_VERSION', '0.0.1' );
define( 'EVTCAL__MINIMUM_WP_VERSION', '5.2' );
define( 'EVTCAL__PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'EVTCAL__PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

// model.
require_once EVTCAL__PLUGIN_DIR . 'data/class-comcal-date-time.php';
require_once EVTCAL__PLUGIN_DIR . 'data/class-comcal-database.php';
require_once EVTCAL__PLUGIN_DIR . 'data/class-comcal-event.php';
require_once EVTCAL__PLUGIN_DIR . 'data/class-comcal-category.php';

// view.
require_once EVTCAL__PLUGIN_DIR . 'view/view-common.php';
require_once EVTCAL__PLUGIN_DIR . 'view/buttons.php';
require_once EVTCAL__PLUGIN_DIR . 'view/class-comcal-ajax-event-popup.php';
require_once EVTCAL__PLUGIN_DIR . 'view/class-comcal-events-display-builder.php';
require_once EVTCAL__PLUGIN_DIR . 'view/class-comcal-event-renderer.php';
require_once EVTCAL__PLUGIN_DIR . 'view/class-comcal-event-popup.php';
require_once EVTCAL__PLUGIN_DIR . 'view/show-categories.php';

// controller.
require_once EVTCAL__PLUGIN_DIR . 'edit/edit-event.php';
require_once EVTCAL__PLUGIN_DIR . 'edit/edit-category.php';

// api.
require_once EVTCAL__PLUGIN_DIR . 'api/scraper-api.php';
require_once EVTCAL__PLUGIN_DIR . 'api/event-api.php';

/**
 * Enqueue scripts and styles.
 */
function comcal_scripts() {
    $script_version = EVTCAL_VERSION . '-' . time();
    wp_enqueue_script( 'comcal_event_api_js', EVTCAL__PLUGIN_URL . 'public/js/event-api.js', array( 'jquery', 'jquery-form' ), $script_version, true );
    wp_enqueue_script( 'comcal_edit_event_js', EVTCAL__PLUGIN_URL . 'public/js/edit-event.js', array( 'jquery', 'jquery-form' ), $script_version, true );
    wp_enqueue_script( 'comcal_popup_event_js', EVTCAL__PLUGIN_URL . 'public/js/popup-event.js', array( 'jquery', 'jquery-form' ), $script_version, true );
    wp_enqueue_script( 'comcal_edit_categories_js', EVTCAL__PLUGIN_URL . 'public/js/edit-categories.js', array( 'jquery', 'jquery-form' ), $script_version, true );
    wp_enqueue_script( 'comcal_comcal_basic_js', EVTCAL__PLUGIN_URL . 'public/js/comcal-basic.js', array( 'jquery', 'jquery-form' ), $script_version, true );
}
add_action( 'wp_enqueue_scripts', 'comcal_scripts' );

function comcal_styles() {
    $style_version = EVTCAL_VERSION . '-' . time();
    wp_enqueue_style( 'comcal_css', EVTCAL__PLUGIN_URL . 'public/css/comcal.css', array(), $style_version );
}
add_action( 'wp_print_styles', 'comcal_styles' );

/**
 * Initialize/update database table on activation.
 */
function comcal_activation() {
    Comcal_Database::init_tables();
}
register_activation_hook( __FILE__, 'comcal_activation' );

/**
 * Deactivation hook.
 */
function comcal_deactivation() {
    // tbd.
}
register_deactivation_hook( __FILE__, 'comcal_deactivation' );

/**
 * Log a warning.
 *
 * @param string $text message.
 */
function comcal_warning( $text ) {
    error_log( 'ComCal-warning: ' . $text );
}

/**
 * Log an error.
 *
 * @param string $text message.
 */
function comcal_error( $text ) {
    error_log( 'ComCal-error: ' . $text );
}
