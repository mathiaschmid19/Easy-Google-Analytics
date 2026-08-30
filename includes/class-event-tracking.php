<?php
if (!defined('WPINC')) {
    die;
}

class EGA_Event_Tracking {

    const DOWNLOAD_EXTENSIONS = array('pdf', 'zip', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'mp3', 'mp4', 'csv');

    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'));
    }

    public static function any_module_enabled() {
        return get_option('for_you_google_analytics_track_outbound') === '1'
            || get_option('for_you_google_analytics_track_downloads') === '1'
            || get_option('for_you_google_analytics_track_scroll') === '1'
            || get_option('for_you_google_analytics_track_forms') === '1';
    }

    public static function enqueue() {
        if (!EGA_Tracking_Output::is_configured() || !self::any_module_enabled()) {
            return;
        }

        wp_enqueue_script(
            'ega-tracking',
            EGA_PLUGIN_URL . 'assets/tracking.js',
            array(),
            '2.0',
            true
        );

        wp_localize_script('ega-tracking', 'easyGA4TrackingConfig', array(
            'outbound'           => get_option('for_you_google_analytics_track_outbound') === '1',
            'downloads'          => get_option('for_you_google_analytics_track_downloads') === '1',
            'scroll'             => get_option('for_you_google_analytics_track_scroll') === '1',
            'forms'              => get_option('for_you_google_analytics_track_forms') === '1',
            'downloadExtensions' => self::DOWNLOAD_EXTENSIONS,
        ));
    }
}
