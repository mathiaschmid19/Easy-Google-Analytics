<?php
if (!defined('WPINC')) {
    die;
}

class EGA_Admin_Notice {

    const DISMISSED_META_KEY = 'ega_config_notice_dismissed';
    const AJAX_ACTION        = 'ega_dismiss_config_notice';
    const NONCE_ACTION       = 'ega_dismiss_config_notice';

    public static function init() {
        add_action('admin_notices', array(__CLASS__, 'render'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue'));
        add_action('wp_ajax_' . self::AJAX_ACTION, array(__CLASS__, 'handle_dismiss'));
    }

    private static function should_show($hook) {
        if ($hook === 'settings_page_for_you_google_analytics') {
            return false;
        }

        if (!current_user_can('manage_options')) {
            return false;
        }

        if (EGA_Tracking_Output::is_configured()) {
            return false;
        }

        return !get_user_meta(get_current_user_id(), self::DISMISSED_META_KEY, true);
    }

    public static function enqueue($hook) {
        if (!self::should_show($hook)) {
            return;
        }

        wp_enqueue_script(
            'ega-admin-notice',
            EGA_PLUGIN_URL . 'assets/admin-notice.js',
            array(),
            '2.0',
            true
        );

        wp_localize_script('ega-admin-notice', 'egaAdminNotice', array(
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce(self::NONCE_ACTION),
            'action'  => self::AJAX_ACTION,
        ));
    }

    public static function render() {
        $hook = get_current_screen();
        $hook = $hook ? $hook->id : '';

        if (!self::should_show($hook)) {
            return;
        }

        $settings_url = admin_url('options-general.php?page=for_you_google_analytics');
        ?>
        <div class="notice notice-warning is-dismissible ega-config-notice">
            <p>
                <strong><?php esc_html_e('Easy Google Analytics is almost ready.', 'for-you-google-analytics'); ?></strong>
                <?php esc_html_e('Add your GA4 Measurement ID or GTM Container ID to start tracking visitors.', 'for-you-google-analytics'); ?>
            </p>
            <p>
                <a href="<?php echo esc_url($settings_url); ?>" class="button button-primary">
                    <?php esc_html_e('Configure Now', 'for-you-google-analytics'); ?>
                </a>
            </p>
        </div>
        <?php
    }

    public static function handle_dismiss() {
        check_ajax_referer(self::NONCE_ACTION, 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('forbidden', 403);
        }

        update_user_meta(get_current_user_id(), self::DISMISSED_META_KEY, '1');
        wp_send_json_success();
    }
}
