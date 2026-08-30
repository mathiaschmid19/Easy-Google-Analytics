<?php
if (!defined('WPINC')) {
    die;
}

class EGA_Settings {

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_init', array(__CLASS__, 'register_fields'));
    }

    public static function menu() {
        add_options_page(
            'Google Analytics Settings (GA4)',
            'Google Analytics (GA4)',
            'manage_options',
            'for_you_google_analytics',
            array(__CLASS__, 'render_page')
        );
    }

    public static function render_page() {
        ?>
        <div class="wrap">
            <h2>Google Analytics (GA4) Settings</h2>
            <?php
            settings_errors('for_you_google_analytics_ga4_code');
            settings_errors('for_you_google_analytics_gtm_id');
            ?>
            <form method="post" action="options.php">
                <?php
                settings_fields('for_you_google_analytics_options');
                do_settings_sections('for_you_google_analytics');
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    public static function register_settings() {
        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_ga4_code',
            array(
                'type'              => 'string',
                'sanitize_callback' => array(__CLASS__, 'sanitize_ga4_code'),
                'default'           => '',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_gtm_id',
            array(
                'type'              => 'string',
                'sanitize_callback' => array(__CLASS__, 'sanitize_gtm_id'),
                'default'           => '',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_consent_banner_enabled',
            array(
                'type'              => 'string',
                'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
                'default'           => '',
            )
        );
    }

    public static function sanitize_ga4_code($input) {
        $input = strtoupper(sanitize_text_field($input));

        if (empty($input)) {
            return '';
        }

        if (!preg_match('/^G-[A-Z0-9]+$/', $input)) {
            add_settings_error(
                'for_you_google_analytics_ga4_code',
                'invalid_ga4_code',
                __('Invalid GA4 tracking code. It must be in the format G-XXXXXXXXXX.', 'for-you-google-analytics')
            );
            return get_option('for_you_google_analytics_ga4_code', '');
        }

        return $input;
    }

    public static function sanitize_gtm_id($input) {
        $input = strtoupper(sanitize_text_field($input));

        if (empty($input)) {
            return '';
        }

        if (!preg_match('/^GTM-[A-Z0-9]+$/', $input)) {
            add_settings_error(
                'for_you_google_analytics_gtm_id',
                'invalid_gtm_id',
                __('Invalid GTM Container ID. It must be in the format GTM-XXXXXXX.', 'for-you-google-analytics')
            );
            return get_option('for_you_google_analytics_gtm_id', '');
        }

        return $input;
    }

    public static function sanitize_checkbox($input) {
        return ($input === '1') ? '1' : '';
    }

    public static function register_fields() {
        add_settings_section(
            'for_you_google_analytics_section',
            'Google Analytics (GA4) Tracking Code',
            array(__CLASS__, 'section_callback'),
            'for_you_google_analytics'
        );

        add_settings_field(
            'for_you_google_analytics_ga4_code',
            'GA4 Tracking Code',
            array(__CLASS__, 'ga4_code_field'),
            'for_you_google_analytics',
            'for_you_google_analytics_section'
        );

        add_settings_field(
            'for_you_google_analytics_gtm_id',
            'GTM Container ID',
            array(__CLASS__, 'gtm_id_field'),
            'for_you_google_analytics',
            'for_you_google_analytics_section'
        );

        add_settings_field(
            'for_you_google_analytics_consent_banner_enabled',
            'Consent Banner',
            array(__CLASS__, 'consent_banner_field'),
            'for_you_google_analytics',
            'for_you_google_analytics_section'
        );
    }

    public static function section_callback() {
        echo '<p>Enter your Google Analytics (GA4) tracking code below:</p>';
    }

    public static function ga4_code_field() {
        $ga4_code = get_option('for_you_google_analytics_ga4_code');
        echo '<input type="text" name="for_you_google_analytics_ga4_code" value="' . esc_attr($ga4_code) . '" placeholder="G-XXXXXXXXXX" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Format: G-XXXXXXXXXX', 'for-you-google-analytics') . '</p>';
    }

    public static function gtm_id_field() {
        $gtm_id = get_option('for_you_google_analytics_gtm_id');
        echo '<input type="text" name="for_you_google_analytics_gtm_id" value="' . esc_attr($gtm_id) . '" placeholder="GTM-XXXXXXX" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Format: GTM-XXXXXXX. If set, GA4 is typically configured inside GTM instead of loading separately.', 'for-you-google-analytics') . '</p>';
    }

    public static function consent_banner_field() {
        $enabled = get_option('for_you_google_analytics_consent_banner_enabled');
        echo '<label><input type="checkbox" name="for_you_google_analytics_consent_banner_enabled" value="1" ' . checked('1', $enabled, false) . ' /> ';
        echo esc_html__('Enable built-in consent banner', 'for-you-google-analytics') . '</label>';
        echo '<p class="description">' . esc_html__('Only shown when Complianz or Cookiebot isn\'t detected on the page.', 'for-you-google-analytics') . '</p>';
    }
}
