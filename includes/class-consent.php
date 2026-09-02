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
                'reject'       => '#1e1e1e',
                'reject_style' => 'outline',
            ),
            'minimal' => array(
                'bg'           => '#f8f9fa',
                'text'         => '#1e293b',
                'accept'       => '#1e293b',
                'reject'       => '#64748b',
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

    public static function get_contrast_color($hex_color) {
        $hex_color = ltrim(trim((string) $hex_color), '#');
        if (strlen($hex_color) === 3) {
            $hex_color = $hex_color[0] . $hex_color[0] . $hex_color[1] . $hex_color[1] . $hex_color[2] . $hex_color[2];
        }
        if (strlen($hex_color) !== 6) {
            return '#ffffff';
        }
        $r = hexdec(substr($hex_color, 0, 2));
        $g = hexdec(substr($hex_color, 2, 2));
        $b = hexdec(substr($hex_color, 4, 2));
        $yiq = (($r * 299) + ($g * 587) + ($b * 114)) / 1000;
        return ($yiq >= 128) ? '#000000' : '#ffffff';
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

        $css_ver = file_exists(EGA_PLUGIN_DIR . 'assets/consent-banner.css') ? (string) filemtime(EGA_PLUGIN_DIR . 'assets/consent-banner.css') : '2.2';
        $js_ver  = file_exists(EGA_PLUGIN_DIR . 'assets/consent-banner.js') ? (string) filemtime(EGA_PLUGIN_DIR . 'assets/consent-banner.js') : '2.2';

        wp_enqueue_style(
            'ega-consent-banner',
            EGA_PLUGIN_URL . 'assets/consent-banner.css',
            array(),
            $css_ver
        );

        wp_enqueue_script(
            'ega-consent-banner',
            EGA_PLUGIN_URL . 'assets/consent-banner.js',
            array(),
            $js_ver,
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

        $accept_text_color = self::get_contrast_color($accept_color);
        $reject_text_color = ($reject_style === 'filled') ? self::get_contrast_color($reject_color) : $reject_color;

        $style = sprintf(
            '--ega-banner-bg:%s;--ega-banner-text:%s;--ega-banner-accept:%s;--ega-banner-accept-text:%s;--ega-banner-reject:%s;--ega-banner-reject-text:%s;',
            esc_attr($bg_color),
            esc_attr($text_color),
            esc_attr($accept_color),
            esc_attr($accept_text_color),
            esc_attr($reject_color),
            esc_attr($reject_text_color)
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
        <button type="button" id="ega-consent-manage" class="ega-consent-manage-btn" style="<?php echo esc_attr($style); ?>" aria-label="<?php esc_attr_e('Manage cookie preferences', 'for-you-google-analytics'); ?>" title="<?php esc_attr_e('Manage cookie preferences', 'for-you-google-analytics'); ?>" hidden>
            <span class="ega-manage-icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5"/>
                    <circle cx="8.5" cy="8.5" r="1" fill="currentColor"/>
                    <circle cx="7.5" cy="15.5" r="1" fill="currentColor"/>
                    <circle cx="12" cy="18" r="1" fill="currentColor"/>
                    <circle cx="11" cy="13" r="1" fill="currentColor"/>
                    <circle cx="16" cy="13" r="1" fill="currentColor"/>
                </svg>
            </span>
            <span class="ega-manage-label"><?php esc_html_e('Cookie Preferences', 'for-you-google-analytics'); ?></span>
        </button>
        <?php
    }
}
