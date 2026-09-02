<?php
/*
    Plugin Name: Easy Google Analytics
    Plugin URI: #
    Description: Adds your Google Analytics tracking code to the <head> of your theme.
    Author: Amine Ouhannou
    Version: 2.2
    Text Domain: for-you-google-analytics
 */

if (!defined('WPINC')) {
    die;
}

define('EGA_VERSION', '2.2');
define('EGA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EGA_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once EGA_PLUGIN_DIR . 'includes/class-settings.php';
require_once EGA_PLUGIN_DIR . 'includes/class-tracking-output.php';
require_once EGA_PLUGIN_DIR . 'includes/class-consent.php';
require_once EGA_PLUGIN_DIR . 'includes/class-event-tracking.php';
require_once EGA_PLUGIN_DIR . 'includes/class-admin-notice.php';

EGA_Settings::init();
EGA_Tracking_Output::init();
EGA_Consent::init();
EGA_Event_Tracking::init();
EGA_Admin_Notice::init();
