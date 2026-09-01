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
            '2.1'
        );

        wp_enqueue_script(
            'ega-admin-scripts',
            EGA_PLUGIN_URL . 'assets/admin.js',
            array('jquery'),
            '2.1',
            true
        );
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

            <!-- Admin Notifications Container -->
            <div class="ega-notices-container">
                <?php
                settings_errors('for_you_google_analytics_ga4_code');
                settings_errors('for_you_google_analytics_gtm_id');

                if (!empty($ga4_code) && !empty($gtm_id)) :
                    ?>
                    <div class="notice notice-warning">
                        <p>
                            <strong><?php esc_html_e('Both GA4 and GTM are configured: ', 'for-you-google-analytics'); ?></strong>
                            <?php esc_html_e('This plugin will load both the standalone GA4 tag and the GTM container on every page, which commonly causes duplicate pageview hits if your GTM container already includes a GA4 configuration tag. If GTM manages GA4, clear the GA4 Measurement ID field above and configure GA4 inside GTM instead.', 'for-you-google-analytics'); ?>
                        </p>
                    </div>
                    <?php
                endif;
                ?>
            </div>

            <!-- Main Form -->
            <form method="post" action="options.php" class="ega-settings-form">
                <?php settings_fields('for_you_google_analytics_options'); ?>

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
                                        <span class="ega-input-prefix-icon">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M21.21 15.89A10 10 0 1 1 8 2.83"/>
                                                <path d="M22 12A10 10 0 0 0 12 2v10z"/>
                                            </svg>
                                        </span>
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
                                        <span class="ega-input-prefix-icon">
                                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                                <path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/>
                                                <line x1="7" y1="7" x2="7.01" y2="7"/>
                                            </svg>
                                        </span>
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
            </form>
        </div>
        <?php
    }
}
