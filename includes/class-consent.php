<?php
if (!defined('WPINC')) {
    die;
}

class EGA_Consent {

    public static function init() {
        add_action('wp_enqueue_scripts', array(__CLASS__, 'enqueue'));
        add_action('wp_footer', array(__CLASS__, 'render_banner_markup'));
    }

    public static function banner_enabled() {
        return EGA_Tracking_Output::is_configured()
            && get_option('for_you_google_analytics_consent_banner_enabled') === '1';
    }

    public static function enqueue() {
        if (!self::banner_enabled()) {
            return;
        }

        wp_enqueue_style(
            'ega-consent-banner',
            EGA_PLUGIN_URL . 'assets/consent-banner.css',
            array(),
            '2.0'
        );

        wp_enqueue_script(
            'ega-consent-banner',
            EGA_PLUGIN_URL . 'assets/consent-banner.js',
            array(),
            '2.0',
            true
        );
    }

    public static function render_banner_markup() {
        if (!self::banner_enabled()) {
            return;
        }
        ?>
        <div id="ega-consent-banner" hidden>
            <p><?php esc_html_e('This site uses cookies to analyze traffic via Google Analytics. Do you accept analytics cookies?', 'for-you-google-analytics'); ?></p>
            <div class="ega-consent-actions">
                <button type="button" id="ega-consent-reject"><?php esc_html_e('Reject', 'for-you-google-analytics'); ?></button>
                <button type="button" id="ega-consent-accept"><?php esc_html_e('Accept', 'for-you-google-analytics'); ?></button>
            </div>
        </div>
        <button type="button" id="ega-consent-manage" hidden><?php esc_html_e('Manage cookie preferences', 'for-you-google-analytics'); ?></button>
        <?php
    }
}
