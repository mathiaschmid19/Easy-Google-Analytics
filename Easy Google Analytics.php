<?php
/*
    Plugin Name: Easy Google Analytics
    Plugin URI: #
    Description: Adds your Google Analytics tracking code to the <head> of your theme.
    Author: Amine Ouhannou
    Version: 2.0
    Text Domain: for-you-google-analytics
 */

if (!defined('WPINC')) {
    die;
}

define('EGA_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('EGA_PLUGIN_URL', plugin_dir_url(__FILE__));

require_once EGA_PLUGIN_DIR . 'includes/class-settings.php';

EGA_Settings::init();
