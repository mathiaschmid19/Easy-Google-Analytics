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
            <?php settings_errors('for_you_google_analytics_ga4_code'); ?>
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
    }

    public static function section_callback() {
        echo '<p>Enter your Google Analytics (GA4) tracking code below:</p>';
    }

    public static function ga4_code_field() {
        $ga4_code = get_option('for_you_google_analytics_ga4_code');
        echo '<input type="text" name="for_you_google_analytics_ga4_code" value="' . esc_attr($ga4_code) . '" placeholder="G-XXXXXXXXXX" class="regular-text" />';
        echo '<p class="description">' . esc_html__('Format: G-XXXXXXXXXX', 'for-you-google-analytics') . '</p>';
    }
}
