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

        // Known limitation: tracking.js is enqueued whenever event tracking is
        // configured, even if nothing can ever grant consent (e.g. the built-in
        // consent banner is disabled and no CMP is installed). Server-side PHP
        // cannot reliably detect a client-side CMP (Complianz/Cookiebot), so this
        // is intentionally not gated here — see the "Enable event tracking"
        // description in EGA_Settings::event_tracking_field() for the
        // admin-facing guidance instead.
        $js_ver = file_exists(EGA_PLUGIN_DIR . 'assets/tracking.js') ? (string) filemtime(EGA_PLUGIN_DIR . 'assets/tracking.js') : (defined('EGA_VERSION') ? EGA_VERSION : '2.2');

        wp_enqueue_script(
            'ega-tracking',
            EGA_PLUGIN_URL . 'assets/tracking.js',
            array(),
            $js_ver,
            true
        );

        // wp_localize_script casts every scalar value to a string before JSON-encoding,
        // so JS receives "1"/"" here, not real booleans true/false. Consuming code in
        // assets/tracking.js must use truthy checks (e.g. `if (config.scroll)`), never
        // strict comparisons like `config.downloads === false`.
        wp_localize_script('ega-tracking', 'easyGA4TrackingConfig', array(
            'outbound'           => get_option('for_you_google_analytics_track_outbound') === '1',
            'downloads'          => get_option('for_you_google_analytics_track_downloads') === '1',
            'scroll'             => get_option('for_you_google_analytics_track_scroll') === '1',
            'forms'              => get_option('for_you_google_analytics_track_forms') === '1',
            'downloadExtensions' => self::DOWNLOAD_EXTENSIONS,
        ));
    }
}
