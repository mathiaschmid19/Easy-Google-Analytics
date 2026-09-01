<?php
if (!defined('WPINC')) {
    die;
}

class EGA_Settings {

    public static function init() {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_init', array(__CLASS__, 'register_settings'));
        add_action('admin_enqueue_scripts', array(__CLASS__, 'enqueue_admin_assets'));
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

    public static function enqueue_admin_assets($hook) {
        if ($hook !== 'settings_page_for_you_google_analytics') {
            return;
        }

        wp_enqueue_style(
            'ega-admin-styles',
            EGA_PLUGIN_URL . 'assets/admin.css',
            array(),
            '2.0'
        );

        wp_enqueue_script(
            'ega-admin-scripts',
            EGA_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            '2.0',
            true
        );

        wp_localize_script('ega-admin-scripts', 'egaBannerDesign', array(
            'palettes' => EGA_Consent::get_palettes(),
            'defaults' => array(
                'message'     => __('This site uses cookies to analyze traffic via Google Analytics. Do you accept analytics cookies?', 'for-you-google-analytics'),
                'acceptLabel' => __('Accept', 'for-you-google-analytics'),
                'rejectLabel' => __('Reject', 'for-you-google-analytics'),
            ),
        ));
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

        $checkbox_options = array(
            'for_you_google_analytics_track_outbound',
            'for_you_google_analytics_track_downloads',
            'for_you_google_analytics_track_scroll',
            'for_you_google_analytics_track_forms',
        );

        foreach ($checkbox_options as $option_name) {
            register_setting(
                'for_you_google_analytics_options',
                $option_name,
                array(
                    'type'              => 'string',
                    'sanitize_callback' => array(__CLASS__, 'sanitize_checkbox'),
                    'default'           => '',
                )
            );
        }

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_palette',
            array(
                'type'              => 'string',
                'sanitize_callback' => array(__CLASS__, 'sanitize_banner_palette'),
                'default'           => 'dark',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_bg_color',
            array(
                'type'              => 'string',
                'sanitize_callback' => function ($input) {
                    return EGA_Settings::sanitize_hex_color($input, 'for_you_google_analytics_banner_bg_color', get_option('for_you_google_analytics_banner_bg_color', '#1e1e1e'));
                },
                'default'           => '#1e1e1e',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_text_color',
            array(
                'type'              => 'string',
                'sanitize_callback' => function ($input) {
                    return EGA_Settings::sanitize_hex_color($input, 'for_you_google_analytics_banner_text_color', get_option('for_you_google_analytics_banner_text_color', '#ffffff'));
                },
                'default'           => '#ffffff',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_accept_color',
            array(
                'type'              => 'string',
                'sanitize_callback' => function ($input) {
                    return EGA_Settings::sanitize_hex_color($input, 'for_you_google_analytics_banner_accept_color', get_option('for_you_google_analytics_banner_accept_color', '#2271b1'));
                },
                'default'           => '#2271b1',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_reject_color',
            array(
                'type'              => 'string',
                'sanitize_callback' => function ($input) {
                    return EGA_Settings::sanitize_hex_color($input, 'for_you_google_analytics_banner_reject_color', get_option('for_you_google_analytics_banner_reject_color', '#ffffff'));
                },
                'default'           => '#ffffff',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_layout',
            array(
                'type'              => 'string',
                'sanitize_callback' => array(__CLASS__, 'sanitize_banner_layout'),
                'default'           => 'bar',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_message',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_textarea_field',
                'default'           => '',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_accept_label',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_reject_label',
            array(
                'type'              => 'string',
                'sanitize_callback' => 'sanitize_text_field',
                'default'           => '',
            )
        );

        register_setting(
            'for_you_google_analytics_options',
            'for_you_google_analytics_banner_privacy_url',
            array(
                'type'              => 'string',
                'sanitize_callback' => array(__CLASS__, 'sanitize_banner_privacy_url'),
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

    public static function sanitize_hex_color($input, $option_name, $fallback) {
        $input = is_string($input) ? trim($input) : '';

        if (preg_match('/^#[0-9a-fA-F]{6}$/', $input)) {
            return strtolower($input);
        }

        add_settings_error(
            $option_name,
            'invalid_hex_color',
            __('Invalid color value. Colors must be a 6-digit hex code (e.g. #1e1e1e).', 'for-you-google-analytics')
        );

        return $fallback;
    }

    public static function sanitize_banner_palette($input) {
        $input = is_string($input) ? trim($input) : '';
        $palettes = EGA_Consent::get_palettes();

        if (!array_key_exists($input, $palettes)) {
            return 'custom';
        }

        $preset = $palettes[$input];
        update_option('for_you_google_analytics_banner_bg_color', $preset['bg']);
        update_option('for_you_google_analytics_banner_text_color', $preset['text']);
        update_option('for_you_google_analytics_banner_accept_color', $preset['accept']);
        update_option('for_you_google_analytics_banner_reject_color', $preset['reject']);

        return $input;
    }

    public static function sanitize_banner_layout($input) {
        return ($input === 'corner') ? 'corner' : 'bar';
    }

    public static function sanitize_banner_privacy_url($input) {
        $input = is_string($input) ? trim($input) : '';

        if ($input === '') {
            return '';
        }

        $sanitized = esc_url_raw($input);

        return $sanitized !== '' ? $sanitized : '';
    }

    public static function render_page() {
        $ga4_code       = get_option('for_you_google_analytics_ga4_code', '');
        $gtm_id         = get_option('for_you_google_analytics_gtm_id', '');
        $consent_banner = get_option('for_you_google_analytics_consent_banner_enabled', '');
        $track_outbound = get_option('for_you_google_analytics_track_outbound', '');
        $track_download = get_option('for_you_google_analytics_track_downloads', '');
        $track_scroll   = get_option('for_you_google_analytics_track_scroll', '');
        $track_forms    = get_option('for_you_google_analytics_track_forms', '');

        $is_configured = !empty($ga4_code) || !empty($gtm_id);
        
        $active_events_count = 0;
        if ($track_outbound === '1') $active_events_count++;
        if ($track_download === '1') $active_events_count++;
        if ($track_scroll === '1')   $active_events_count++;
        if ($track_forms === '1')    $active_events_count++;
        ?>
        <div class="wrap ega-admin-wrap">
            <?php
            settings_errors('for_you_google_analytics_ga4_code');
            settings_errors('for_you_google_analytics_gtm_id');
            settings_errors('for_you_google_analytics_banner_bg_color');
            settings_errors('for_you_google_analytics_banner_text_color');
            settings_errors('for_you_google_analytics_banner_accept_color');
            settings_errors('for_you_google_analytics_banner_reject_color');
            ?>

            <!-- Hero Header Banner -->
            <div class="ega-header-banner">
                <div class="ega-header-content">
                    <div class="ega-header-badge-group">
                        <span class="ega-version-tag">Version 2.0</span>
                        <span class="ega-pill-tag">
                            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
                            Consent Mode v2
                        </span>
                    </div>
                    <h1 class="ega-header-title">
                        <div class="ega-logo-icon">
                            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#ffffff" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M18 20V10M12 20V4M6 20v-6"/>
                            </svg>
                        </div>
                        <?php esc_html_e('Easy Google Analytics', 'for-you-google-analytics'); ?>
                    </h1>
                    <p class="ega-header-desc">
                        <?php esc_html_e('Privacy-friendly Google Analytics (GA4) and Google Tag Manager integration with automatic Google Consent Mode v2 support and client-side event tracking.', 'for-you-google-analytics'); ?>
                    </p>
                </div>

                <div class="ega-header-status-card">
                    <span class="ega-status-label"><?php esc_html_e('Integration Status', 'for-you-google-analytics'); ?></span>
                    <?php if ($is_configured) : ?>
                        <div class="ega-status-pill is-active">
                            <span class="ega-pulse-dot"></span>
                            <?php esc_html_e('Active & Tracking', 'for-you-google-analytics'); ?>
                        </div>
                    <?php else : ?>
                        <div class="ega-status-pill is-pending">
                            <span class="ega-pulse-dot"></span>
                            <?php esc_html_e('Setup Required', 'for-you-google-analytics'); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Main Form -->
            <form method="post" action="options.php" class="ega-settings-form">
                <?php settings_fields('for_you_google_analytics_options'); ?>

                <!-- Tabs -->
                <div class="ega-tabs-nav" role="tablist">
                    <button type="button" id="ega-tab-trigger-settings" class="ega-tab-trigger is-active" role="tab" aria-selected="true" aria-controls="ega-tab-panel-settings">
                        <?php esc_html_e('Settings', 'for-you-google-analytics'); ?>
                    </button>
                    <button type="button" id="ega-tab-trigger-design" class="ega-tab-trigger" role="tab" aria-selected="false" aria-controls="ega-tab-panel-design">
                        <?php esc_html_e('Banner Design', 'for-you-google-analytics'); ?>
                    </button>
                </div>

                <div id="ega-tab-panel-settings" class="ega-tab-panel" role="tabpanel">
                <div class="ega-grid">
                    <!-- Main Settings Column -->
                    <div class="ega-main-column">

                        <!-- Card 1: Tracking IDs -->
                        <div class="ega-card">
                            <div class="ega-card-header">
                                <div class="ega-card-title-group">
                                    <div class="ega-card-icon accent">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <rect x="2" y="3" width="20" height="14" rx="2" ry="2"/>
                                            <line x1="8" y1="21" x2="16" y2="21"/>
                                            <line x1="12" y1="17" x2="12" y2="21"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="ega-card-title"><?php esc_html_e('Tracking Configuration', 'for-you-google-analytics'); ?></h2>
                                        <p class="ega-card-subtitle"><?php esc_html_e('Connect your Google Analytics 4 property or Google Tag Manager container.', 'for-you-google-analytics'); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="ega-card-body">
                                <!-- GA4 Measurement ID -->
                                <div class="ega-field-group">
                                    <label class="ega-field-label" for="for_you_google_analytics_ga4_code">
                                        <span><?php esc_html_e('GA4 Measurement ID', 'for-you-google-analytics'); ?></span>
                                        <span class="ega-label-tag recommended"><?php esc_html_e('Recommended', 'for-you-google-analytics'); ?></span>
                                    </label>
                                    <div class="ega-input-wrapper">
                                        <input
                                            type="text"
                                            id="for_you_google_analytics_ga4_code"
                                            name="for_you_google_analytics_ga4_code"
                                            value="<?php echo esc_attr($ga4_code); ?>"
                                            placeholder="G-XXXXXXXXXX"
                                            class="ega-text-input"
                                            autocomplete="off"
                                            spellcheck="false"
                                        />
                                        <div class="ega-input-prefix-icon">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
                                                <path d="M22 12A10 10 0 0 0 12 2v10z"/>
                                            </svg>
                                        </div>
                                    </div>
                                    <div id="ega-ga4-validation-msg" class="ega-input-validation-msg"></div>
                                    <p class="ega-helper-text">
                                        <?php esc_html_e('Enter your Google Analytics 4 Measurement ID (Format: G-XXXXXXXXXX). Found under Admin > Data Streams > Web.', 'for-you-google-analytics'); ?>
                                    </p>
                                </div>

                                <!-- GTM Container ID -->
                                <div class="ega-field-group">
                                    <label class="ega-field-label" for="for_you_google_analytics_gtm_id">
                                        <span><?php esc_html_e('GTM Container ID', 'for-you-google-analytics'); ?></span>
                                        <span class="ega-label-tag"><?php esc_html_e('Optional', 'for-you-google-analytics'); ?></span>
                                    </label>
                                    <div class="ega-input-wrapper">
                                        <input
                                            type="text"
                                            id="for_you_google_analytics_gtm_id"
                                            name="for_you_google_analytics_gtm_id"
                                            value="<?php echo esc_attr($gtm_id); ?>"
                                            placeholder="GTM-XXXXXXX"
                                            class="ega-text-input"
                                            autocomplete="off"
                                            spellcheck="false"
                                        />
                                        <div class="ega-input-prefix-icon">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                                                <line x1="7" y1="7" x2="7.01" y2="7"/>
                                            </svg>
                                        </div>
                                    </div>
                                    <div id="ega-gtm-validation-msg" class="ega-input-validation-msg"></div>
                                    <p class="ega-helper-text">
                                        <?php esc_html_e('If using Google Tag Manager, enter your Container ID (Format: GTM-XXXXXXX). GA4 is typically configured inside GTM.', 'for-you-google-analytics'); ?>
                                    </p>
                                </div>
                            </div>
                        </div>

                        <!-- Card 2: Privacy & Consent Mode v2 -->
                        <div class="ega-card">
                            <div class="ega-card-header">
                                <div class="ega-card-title-group">
                                    <div class="ega-card-icon emerald">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="ega-card-title"><?php esc_html_e('Privacy & Consent Management', 'for-you-google-analytics'); ?></h2>
                                        <p class="ega-card-subtitle"><?php esc_html_e('Ensure compliance with GDPR, ePrivacy, and Google Consent Mode v2.', 'for-you-google-analytics'); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="ega-card-body">
                                <div class="ega-toggle-wrapper <?php echo ($consent_banner === '1') ? 'active' : ''; ?>">
                                    <div class="ega-toggle-info">
                                        <span class="ega-toggle-title"><?php esc_html_e('Enable Built-in Consent Banner', 'for-you-google-analytics'); ?></span>
                                        <span class="ega-toggle-desc"><?php esc_html_e('Displays a clean cookie consent prompt to visitors and sends real-time consent updates to Google Analytics.', 'for-you-google-analytics'); ?></span>
                                    </div>
                                    <label class="ega-switch">
                                        <input
                                            type="checkbox"
                                            name="for_you_google_analytics_consent_banner_enabled"
                                            value="1"
                                            <?php checked('1', $consent_banner); ?>
                                        />
                                        <span class="ega-slider"></span>
                                    </label>
                                </div>

                                <div class="ega-callout">
                                    <div class="ega-callout-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                    </div>
                                    <div>
                                        <strong><?php esc_html_e('Smart CMP Detection: ', 'for-you-google-analytics'); ?></strong>
                                        <?php esc_html_e('The built-in banner automatically yields and hides if a dedicated Consent Management Platform (such as Complianz or Cookiebot) is detected on your site.', 'for-you-google-analytics'); ?>
                                    </div>
                                </div>

                                <!-- Live Banner Preview Drawer -->
                                <div id="ega-consent-preview" class="ega-preview-box" <?php echo ($consent_banner !== '1') ? 'style="display:none;"' : ''; ?>>
                                    <div class="ega-preview-header">
                                        <span><?php esc_html_e('Live Banner Preview (Frontend)', 'for-you-google-analytics'); ?></span>
                                        <span style="color:#34d399;font-size:10px;">&#9679; Active</span>
                                    </div>
                                    <div class="ega-preview-banner">
                                        <p><?php esc_html_e('This site uses cookies to analyze traffic via Google Analytics. Do you accept analytics cookies?', 'for-you-google-analytics'); ?></p>
                                        <div class="ega-preview-actions">
                                            <button type="button" class="ega-btn-preview-reject"><?php esc_html_e('Reject', 'for-you-google-analytics'); ?></button>
                                            <button type="button" class="ega-btn-preview-accept"><?php esc_html_e('Accept', 'for-you-google-analytics'); ?></button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Card 3: Enhanced Event Tracking -->
                        <div class="ega-card">
                            <div class="ega-card-header">
                                <div class="ega-card-title-group">
                                    <div class="ega-card-icon">
                                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                                            <polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>
                                        </svg>
                                    </div>
                                    <div>
                                        <h2 class="ega-card-title"><?php esc_html_e('Enhanced Event Tracking', 'for-you-google-analytics'); ?></h2>
                                        <p class="ega-card-subtitle"><?php esc_html_e('Automatically track visitor actions and micro-conversions without custom coding.', 'for-you-google-analytics'); ?></p>
                                    </div>
                                </div>
                            </div>
                            <div class="ega-card-body">
                                <div class="ega-events-grid">

                                    <!-- Outbound Links -->
                                    <label class="ega-event-item <?php echo ($track_outbound === '1') ? 'is-selected' : ''; ?>">
                                        <div class="ega-event-icon">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                        </div>
                                        <div class="ega-event-details">
                                            <span class="ega-event-title"><?php esc_html_e('Outbound Link Clicks', 'for-you-google-analytics'); ?></span>
                                            <p class="ega-event-desc"><?php esc_html_e('Logs when visitors click links leading to external domains.', 'for-you-google-analytics'); ?></p>
                                        </div>
                                        <input
                                            type="checkbox"
                                            name="for_you_google_analytics_track_outbound"
                                            value="1"
                                            class="ega-event-checkbox"
                                            <?php checked('1', $track_outbound); ?>
                                        />
                                    </label>

                                    <!-- File Downloads -->
                                    <label class="ega-event-item <?php echo ($track_download === '1') ? 'is-selected' : ''; ?>">
                                        <div class="ega-event-icon">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                                        </div>
                                        <div class="ega-event-details">
                                            <span class="ega-event-title"><?php esc_html_e('File Downloads', 'for-you-google-analytics'); ?></span>
                                            <p class="ega-event-desc"><?php esc_html_e('Auto-tracks PDF, ZIP, DOCX, XLSX, MP4, and CSV file downloads.', 'for-you-google-analytics'); ?></p>
                                        </div>
                                        <input
                                            type="checkbox"
                                            name="for_you_google_analytics_track_downloads"
                                            value="1"
                                            class="ega-event-checkbox"
                                            <?php checked('1', $track_download); ?>
                                        />
                                    </label>

                                    <!-- Scroll Depth -->
                                    <label class="ega-event-item <?php echo ($track_scroll === '1') ? 'is-selected' : ''; ?>">
                                        <div class="ega-event-icon">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 5v14M19 12l-7 7-7-7"/></svg>
                                        </div>
                                        <div class="ega-event-details">
                                            <span class="ega-event-title"><?php esc_html_e('Scroll Depth', 'for-you-google-analytics'); ?></span>
                                            <p class="ega-event-desc"><?php esc_html_e('Captures 25%, 50%, 75%, and 90% vertical scroll milestones.', 'for-you-google-analytics'); ?></p>
                                        </div>
                                        <input
                                            type="checkbox"
                                            name="for_you_google_analytics_track_scroll"
                                            value="1"
                                            class="ega-event-checkbox"
                                            <?php checked('1', $track_scroll); ?>
                                        />
                                    </label>

                                    <!-- Form Submissions -->
                                    <label class="ega-event-item <?php echo ($track_forms === '1') ? 'is-selected' : ''; ?>">
                                        <div class="ega-event-icon">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                        </div>
                                        <div class="ega-event-details">
                                            <span class="ega-event-title"><?php esc_html_e('Form Submissions', 'for-you-google-analytics'); ?></span>
                                            <p class="ega-event-desc"><?php esc_html_e('Tracks form submission attempts and lead captures on your pages.', 'for-you-google-analytics'); ?></p>
                                        </div>
                                        <input
                                            type="checkbox"
                                            name="for_you_google_analytics_track_forms"
                                            value="1"
                                            class="ega-event-checkbox"
                                            <?php checked('1', $track_forms); ?>
                                        />
                                    </label>
                                </div>

                                <div class="ega-callout accent">
                                    <div class="ega-callout-icon">
                                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#d97706" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                                    </div>
                                    <div>
                                        <strong><?php esc_html_e('Consent Required: ', 'for-you-google-analytics'); ?></strong>
                                        <?php esc_html_e('Event tracking strictly respects user consent. Events fire only after analytics storage permission is granted by the visitor.', 'for-you-google-analytics'); ?>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Action Bar -->
                        <div class="ega-action-bar">
                            <div id="ega-save-indicator" class="ega-save-indicator">
                                <span><?php esc_html_e('All changes will be applied instantly to your website.', 'for-you-google-analytics'); ?></span>
                            </div>
                            <button type="submit" id="ega-save-btn" class="ega-btn-save">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                                <?php esc_html_e('Save Changes', 'for-you-google-analytics'); ?>
                            </button>
                        </div>
                    </div>

                    <!-- Sidebar Diagnostics & Helpers -->
                    <div class="ega-sidebar-column">

                        <!-- Diagnostics Widget -->
                        <div class="ega-sidebar-card">
                            <div class="ega-sidebar-header">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                                <h3 class="ega-sidebar-title"><?php esc_html_e('Live Diagnostics', 'for-you-google-analytics'); ?></h3>
                            </div>
                            <div class="ega-sidebar-body">
                                <ul class="ega-diag-list">
                                    <li class="ega-diag-item">
                                        <span class="ega-diag-label"><?php esc_html_e('GA4 Data Stream', 'for-you-google-analytics'); ?></span>
                                        <?php if (!empty($ga4_code)) : ?>
                                            <span class="ega-diag-badge active"><?php echo esc_html($ga4_code); ?></span>
                                        <?php else : ?>
                                            <span class="ega-diag-badge inactive"><?php esc_html_e('Not Set', 'for-you-google-analytics'); ?></span>
                                        <?php endif; ?>
                                    </li>
                                    <li class="ega-diag-item">
                                        <span class="ega-diag-label"><?php esc_html_e('GTM Container', 'for-you-google-analytics'); ?></span>
                                        <?php if (!empty($gtm_id)) : ?>
                                            <span class="ega-diag-badge active"><?php echo esc_html($gtm_id); ?></span>
                                        <?php else : ?>
                                            <span class="ega-diag-badge inactive"><?php esc_html_e('Not Set', 'for-you-google-analytics'); ?></span>
                                        <?php endif; ?>
                                    </li>
                                    <li class="ega-diag-item">
                                        <span class="ega-diag-label"><?php esc_html_e('Consent Mode v2', 'for-you-google-analytics'); ?></span>
                                        <span class="ega-diag-badge active"><?php esc_html_e('Enforced', 'for-you-google-analytics'); ?></span>
                                    </li>
                                    <li class="ega-diag-item">
                                        <span class="ega-diag-label"><?php esc_html_e('Active Event Modules', 'for-you-google-analytics'); ?></span>
                                        <span class="ega-diag-badge <?php echo ($active_events_count > 0) ? 'active' : 'inactive'; ?>">
                                            <?php echo esc_html($active_events_count . ' / 4'); ?>
                                        </span>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <!-- Quick Setup Checklist -->
                        <div class="ega-sidebar-card">
                            <div class="ega-sidebar-header">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2"><path d="M9 11l3 3L22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/></svg>
                                <h3 class="ega-sidebar-title"><?php esc_html_e('Setup Checklist', 'for-you-google-analytics'); ?></h3>
                            </div>
                            <div class="ega-sidebar-body">
                                <ul class="ega-checklist">
                                    <li class="ega-checklist-item">
                                        <span class="ega-check-icon <?php echo !empty($ga4_code) ? 'done' : 'pending'; ?>">
                                            <?php echo !empty($ga4_code) ? '&#10003;' : '1'; ?>
                                        </span>
                                        <span><?php esc_html_e('Add your GA4 Measurement ID (starts with G-)', 'for-you-google-analytics'); ?></span>
                                    </li>
                                    <li class="ega-checklist-item">
                                        <span class="ega-check-icon <?php echo ($consent_banner === '1') ? 'done' : 'pending'; ?>">
                                            <?php echo ($consent_banner === '1') ? '&#10003;' : '2'; ?>
                                        </span>
                                        <span><?php esc_html_e('Enable built-in Consent Banner or install a CMP', 'for-you-google-analytics'); ?></span>
                                    </li>
                                    <li class="ega-checklist-item">
                                        <span class="ega-check-icon <?php echo ($active_events_count > 0) ? 'done' : 'pending'; ?>">
                                            <?php echo ($active_events_count > 0) ? '&#10003;' : '3'; ?>
                                        </span>
                                        <span><?php esc_html_e('Activate desired event tracking modules', 'for-you-google-analytics'); ?></span>
                                    </li>
                                    <li class="ega-checklist-item">
                                        <span class="ega-check-icon <?php echo $is_configured ? 'done' : 'pending'; ?>">
                                            <?php echo $is_configured ? '&#10003;' : '4'; ?>
                                        </span>
                                        <span><?php esc_html_e('Verify live hits in Google Analytics Realtime', 'for-you-google-analytics'); ?></span>
                                    </li>
                                </ul>

                                <div class="ega-quick-links">
                                    <a href="https://analytics.google.com/" target="_blank" rel="noopener noreferrer" class="ega-quick-link-btn">
                                        <span><?php esc_html_e('Open GA4 Realtime Report', 'for-you-google-analytics'); ?></span>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                    </a>
                                    <a href="https://tagassistant.google.com/" target="_blank" rel="noopener noreferrer" class="ega-quick-link-btn">
                                        <span><?php esc_html_e('Google Tag Assistant', 'for-you-google-analytics'); ?></span>
                                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                                    </a>
                                </div>
                            </div>
                        </div>

                        <!-- Plugin Info / Support -->
                        <div class="ega-sidebar-card">
                            <div class="ega-sidebar-header">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#64748b" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                                <h3 class="ega-sidebar-title"><?php esc_html_e('About Easy Google Analytics', 'for-you-google-analytics'); ?></h3>
                            </div>
                            <div class="ega-sidebar-body" style="font-size:12.5px;color:var(--ega-text-secondary);line-height:1.5;">
                                <p style="margin:0 0 8px 0;"><strong><?php esc_html_e('Developer: ', 'for-you-google-analytics'); ?></strong> Amine Ouhannou</p>
                                <p style="margin:0 0 8px 0;"><strong><?php esc_html_e('Compatibility: ', 'for-you-google-analytics'); ?></strong> WordPress 5.8+ &amp; PHP 7.4+</p>
                                <p style="margin:0;"><strong><?php esc_html_e('Privacy: ', 'for-you-google-analytics'); ?></strong> Built-in Google Consent Mode v2 with default denied signals.</p>
                            </div>
                        </div>

                    </div>
                </div>
                </div>

                <div id="ega-tab-panel-design" class="ega-tab-panel" hidden role="tabpanel">
                    <?php self::render_design_tab(); ?>
                </div>
            </form>
        </div>
        <?php
    }

    private static function render_design_tab() {
        $palette       = get_option('for_you_google_analytics_banner_palette', 'dark');
        $bg_color      = get_option('for_you_google_analytics_banner_bg_color', '#1e1e1e');
        $text_color    = get_option('for_you_google_analytics_banner_text_color', '#ffffff');
        $accept_color  = get_option('for_you_google_analytics_banner_accept_color', '#2271b1');
        $reject_color  = get_option('for_you_google_analytics_banner_reject_color', '#ffffff');
        $layout        = get_option('for_you_google_analytics_banner_layout', 'bar');
        $message       = get_option('for_you_google_analytics_banner_message', '');
        $accept_label  = get_option('for_you_google_analytics_banner_accept_label', '');
        $reject_label  = get_option('for_you_google_analytics_banner_reject_label', '');
        $privacy_url   = get_option('for_you_google_analytics_banner_privacy_url', '');

        $default_message      = __('This site uses cookies to analyze traffic via Google Analytics. Do you accept analytics cookies?', 'for-you-google-analytics');
        $default_accept_label = __('Accept', 'for-you-google-analytics');
        $default_reject_label = __('Reject', 'for-you-google-analytics');
        $palettes              = EGA_Consent::get_palettes();
        ?>
        <div class="ega-grid">
            <div class="ega-main-column">

                <!-- Card: Color Palette -->
                <div class="ega-card">
                    <div class="ega-card-header">
                        <div class="ega-card-title-group">
                            <div class="ega-card-icon accent">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><circle cx="13.5" cy="6.5" r=".5"/><circle cx="17.5" cy="10.5" r=".5"/><circle cx="8.5" cy="7.5" r=".5"/><circle cx="6.5" cy="12.5" r=".5"/><path d="M12 2C6.5 2 2 6.5 2 12s4.5 10 10 10c.926 0 1.648-.746 1.648-1.688 0-.437-.18-.835-.437-1.125-.29-.289-.438-.652-.438-1.125a1.64 1.64 0 0 1 1.668-1.668h1.996c3.051 0 5.555-2.503 5.555-5.554C22 6.012 17.461 2 12 2z"/></svg>
                            </div>
                            <div>
                                <h2 class="ega-card-title"><?php esc_html_e('Color Palette', 'for-you-google-analytics'); ?></h2>
                                <p class="ega-card-subtitle"><?php esc_html_e('Pick a preset, then fine-tune any color individually.', 'for-you-google-analytics'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="ega-card-body">
                        <input type="hidden" id="ega-banner-palette-input" name="for_you_google_analytics_banner_palette" value="<?php echo esc_attr($palette); ?>" />

                        <div class="ega-palette-swatches">
                            <?php foreach ($palettes as $key => $preset) : ?>
                                <button type="button" class="ega-palette-swatch <?php echo ($palette === $key) ? 'is-active' : ''; ?>" data-palette="<?php echo esc_attr($key); ?>">
                                    <span class="ega-palette-swatch-preview" style="background:<?php echo esc_attr($preset['bg']); ?>;">
                                        <span style="background:<?php echo esc_attr($preset['accept']); ?>;"></span>
                                        <span style="background:<?php echo esc_attr($preset['reject']); ?>;"></span>
                                    </span>
                                    <span class="ega-palette-swatch-label"><?php echo esc_html(ucwords(str_replace('-', ' ', $key))); ?></span>
                                </button>
                            <?php endforeach; ?>
                        </div>

                        <div class="ega-field-group">
                            <label class="ega-field-label" for="ega-banner-bg-color"><span><?php esc_html_e('Background Color', 'for-you-google-analytics'); ?></span></label>
                            <input type="text" id="ega-banner-bg-color" name="for_you_google_analytics_banner_bg_color" value="<?php echo esc_attr($bg_color); ?>" class="ega-color-input" />
                        </div>
                        <div class="ega-field-group">
                            <label class="ega-field-label" for="ega-banner-text-color"><span><?php esc_html_e('Text Color', 'for-you-google-analytics'); ?></span></label>
                            <input type="text" id="ega-banner-text-color" name="for_you_google_analytics_banner_text_color" value="<?php echo esc_attr($text_color); ?>" class="ega-color-input" />
                        </div>
                        <div class="ega-field-group">
                            <label class="ega-field-label" for="ega-banner-accept-color"><span><?php esc_html_e('Accept Button Color', 'for-you-google-analytics'); ?></span></label>
                            <input type="text" id="ega-banner-accept-color" name="for_you_google_analytics_banner_accept_color" value="<?php echo esc_attr($accept_color); ?>" class="ega-color-input" />
                        </div>
                        <div class="ega-field-group">
                            <label class="ega-field-label" for="ega-banner-reject-color"><span><?php esc_html_e('Reject Button Color', 'for-you-google-analytics'); ?></span></label>
                            <input type="text" id="ega-banner-reject-color" name="for_you_google_analytics_banner_reject_color" value="<?php echo esc_attr($reject_color); ?>" class="ega-color-input" />
                        </div>
                    </div>
                </div>

                <!-- Card: Layout -->
                <div class="ega-card">
                    <div class="ega-card-header">
                        <div class="ega-card-title-group">
                            <div class="ega-card-icon">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><rect x="3" y="3" width="18" height="18" rx="2"/><line x1="3" y1="15" x2="21" y2="15"/></svg>
                            </div>
                            <div>
                                <h2 class="ega-card-title"><?php esc_html_e('Layout', 'for-you-google-analytics'); ?></h2>
                                <p class="ega-card-subtitle"><?php esc_html_e('Choose how the banner is positioned on the page.', 'for-you-google-analytics'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="ega-card-body">
                        <label class="ega-radio-option">
                            <input type="radio" name="for_you_google_analytics_banner_layout" value="bar" <?php checked('bar', $layout); ?> />
                            <?php esc_html_e('Full-width bar (bottom of screen)', 'for-you-google-analytics'); ?>
                        </label>
                        <label class="ega-radio-option">
                            <input type="radio" name="for_you_google_analytics_banner_layout" value="corner" <?php checked('corner', $layout); ?> />
                            <?php esc_html_e('Floating box (bottom-right corner)', 'for-you-google-analytics'); ?>
                        </label>
                    </div>
                </div>

                <!-- Card: Wording & Privacy Link -->
                <div class="ega-card">
                    <div class="ega-card-header">
                        <div class="ega-card-title-group">
                            <div class="ega-card-icon emerald">
                                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/></svg>
                            </div>
                            <div>
                                <h2 class="ega-card-title"><?php esc_html_e('Wording &amp; Privacy Link', 'for-you-google-analytics'); ?></h2>
                                <p class="ega-card-subtitle"><?php esc_html_e('Leave a field blank to use the default text.', 'for-you-google-analytics'); ?></p>
                            </div>
                        </div>
                    </div>
                    <div class="ega-card-body">
                        <div class="ega-field-group">
                            <label class="ega-field-label" for="ega-banner-message"><span><?php esc_html_e('Banner Message', 'for-you-google-analytics'); ?></span></label>
                            <textarea id="ega-banner-message" name="for_you_google_analytics_banner_message" class="ega-text-input ega-textarea" rows="3" placeholder="<?php echo esc_attr($default_message); ?>"><?php echo esc_textarea($message); ?></textarea>
                        </div>
                        <div class="ega-field-group">
                            <label class="ega-field-label" for="ega-banner-accept-label"><span><?php esc_html_e('Accept Button Label', 'for-you-google-analytics'); ?></span></label>
                            <input type="text" id="ega-banner-accept-label" name="for_you_google_analytics_banner_accept_label" value="<?php echo esc_attr($accept_label); ?>" class="ega-text-input" placeholder="<?php echo esc_attr($default_accept_label); ?>" />
                        </div>
                        <div class="ega-field-group">
                            <label class="ega-field-label" for="ega-banner-reject-label"><span><?php esc_html_e('Reject Button Label', 'for-you-google-analytics'); ?></span></label>
                            <input type="text" id="ega-banner-reject-label" name="for_you_google_analytics_banner_reject_label" value="<?php echo esc_attr($reject_label); ?>" class="ega-text-input" placeholder="<?php echo esc_attr($default_reject_label); ?>" />
                        </div>
                        <div class="ega-field-group">
                            <label class="ega-field-label" for="ega-banner-privacy-url"><span><?php esc_html_e('Privacy Policy URL', 'for-you-google-analytics'); ?></span></label>
                            <input type="url" id="ega-banner-privacy-url" name="for_you_google_analytics_banner_privacy_url" value="<?php echo esc_attr($privacy_url); ?>" class="ega-text-input" placeholder="<?php echo esc_attr(get_privacy_policy_url()); ?>" />
                            <p class="ega-helper-text">
                                <?php esc_html_e('Leave blank to automatically use your site\'s Privacy Policy page (Settings > Privacy), if one is set.', 'for-you-google-analytics'); ?>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <div class="ega-sidebar-column">
                <div class="ega-sidebar-card">
                    <div class="ega-sidebar-header">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#4f46e5" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                        <h3 class="ega-sidebar-title"><?php esc_html_e('Live Preview', 'for-you-google-analytics'); ?></h3>
                    </div>
                    <div class="ega-sidebar-body">
                        <div id="ega-design-preview" class="ega-preview-box ega-design-preview">
                            <div class="ega-preview-header">
                                <span><?php esc_html_e('Frontend Preview', 'for-you-google-analytics'); ?></span>
                            </div>
                            <div id="ega-design-preview-banner" class="ega-preview-banner ega-layout-<?php echo esc_attr($layout); ?>" data-reject-style="<?php echo esc_attr($palettes[$palette]['reject_style'] ?? 'outline'); ?>">
                                <p id="ega-design-preview-message"><?php echo esc_html($message !== '' ? $message : $default_message); ?></p>
                                <div class="ega-preview-actions">
                                    <button type="button" id="ega-design-preview-reject" class="ega-btn-preview-reject"><?php echo esc_html($reject_label !== '' ? $reject_label : $default_reject_label); ?></button>
                                    <button type="button" id="ega-design-preview-accept" class="ega-btn-preview-accept"><?php echo esc_html($accept_label !== '' ? $accept_label : $default_accept_label); ?></button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}
