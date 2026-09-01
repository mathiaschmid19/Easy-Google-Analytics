<?php
if (!defined('WPINC')) {
    die;
}

class EGA_Consent {

    public static function get_palettes() {
        return array(
            'dark' => array(
                'bg'           => '#1e1e1e',
                'text'         => '#ffffff',
                'accept'       => '#2271b1',
                'reject'       => '#ffffff',
                'reject_style' => 'outline',
            ),
            'light' => array(
                'bg'           => '#ffffff',
                'text'         => '#1e1e1e',
                'accept'       => '#2271b1',
                'reject'       => '#f0f0f1',
                'reject_style' => 'filled',
            ),
            'minimal' => array(
                'bg'           => '#f8f9fa',
                'text'         => '#3c434a',
                'accept'       => '#3c434a',
                'reject'       => '#3c434a',
                'reject_style' => 'outline',
            ),
            'brand-blue' => array(
                'bg'           => '#0f172a',
                'text'         => '#e2e8f0',
                'accept'       => '#3b82f6',
                'reject'       => '#93c5fd',
                'reject_style' => 'outline',
            ),
            'high-contrast' => array(
                'bg'           => '#000000',
                'text'         => '#ffffff',
                'accept'       => '#ffcc00',
                'reject'       => '#ffffff',
                'reject_style' => 'filled',
            ),
        );
    }

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
        <?php
    }
}
