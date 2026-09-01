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

        $palette_key = get_option('for_you_google_analytics_banner_palette', 'dark');
        $palettes    = self::get_palettes();
        $reject_style = isset($palettes[$palette_key]) ? $palettes[$palette_key]['reject_style'] : 'outline';

        $bg_color     = get_option('for_you_google_analytics_banner_bg_color', '#1e1e1e');
        $text_color   = get_option('for_you_google_analytics_banner_text_color', '#ffffff');
        $accept_color = get_option('for_you_google_analytics_banner_accept_color', '#2271b1');
        $reject_color = get_option('for_you_google_analytics_banner_reject_color', '#ffffff');
        $layout       = get_option('for_you_google_analytics_banner_layout', 'bar');

        $message      = get_option('for_you_google_analytics_banner_message', '');
        $message      = $message !== '' ? $message : __('This site uses cookies to analyze traffic via Google Analytics. Do you accept analytics cookies?', 'for-you-google-analytics');
        $accept_label = get_option('for_you_google_analytics_banner_accept_label', '');
        $accept_label = $accept_label !== '' ? $accept_label : __('Accept', 'for-you-google-analytics');
        $reject_label = get_option('for_you_google_analytics_banner_reject_label', '');
        $reject_label = $reject_label !== '' ? $reject_label : __('Reject', 'for-you-google-analytics');

        $privacy_url = get_option('for_you_google_analytics_banner_privacy_url', '');
        if ($privacy_url === '') {
            $privacy_url = get_privacy_policy_url();
        }

        $style = sprintf(
            '--ega-banner-bg:%s;--ega-banner-text:%s;--ega-banner-accept:%s;--ega-banner-reject:%s;',
            esc_attr($bg_color),
            esc_attr($text_color),
            esc_attr($accept_color),
            esc_attr($reject_color)
        );
        ?>
        <div id="ega-consent-banner" class="ega-layout-<?php echo esc_attr($layout); ?>" data-reject-style="<?php echo esc_attr($reject_style); ?>" style="<?php echo esc_attr($style); ?>" hidden>
            <p>
                <?php echo esc_html($message); ?>
                <?php if (!empty($privacy_url)) : ?>
                    <a href="<?php echo esc_url($privacy_url); ?>"><?php esc_html_e('Learn more', 'for-you-google-analytics'); ?></a>
                <?php endif; ?>
            </p>
            <div class="ega-consent-actions">
                <button type="button" id="ega-consent-reject"><?php echo esc_html($reject_label); ?></button>
                <button type="button" id="ega-consent-accept"><?php echo esc_html($accept_label); ?></button>
            </div>
        </div>
        <button type="button" id="ega-consent-manage" style="<?php echo esc_attr($style); ?>" hidden><?php esc_html_e('Manage cookie preferences', 'for-you-google-analytics'); ?></button>
        <?php
    }
}
