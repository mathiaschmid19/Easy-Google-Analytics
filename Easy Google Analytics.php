<?php
/*
    Plugin Name: Easy Google Analytics
    Plugin URI: #
    Description: Adds your Google Analytics tracking code to the <head> of your theme.
    Author: Amine Ouhannou
    Version: 1.2
 */

if (!defined('WPINC')) {
    die;
}

// Add a menu item to the admin menu
function for_you_google_analytics_menu() {
    add_options_page(
        'Google Analytics Settings (GA4)',
        'Google Analytics (GA4)',
        'manage_options',
        'for_you_google_analytics',
        'for_you_google_analytics_page'
    );
}
add_action('admin_menu', 'for_you_google_analytics_menu');

// Function to display the settings page
function for_you_google_analytics_page() {
    ?>
    <div class="wrap">
        <h2>Google Analytics (GA4) Settings</h2>
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

// Register plugin settings
function for_you_google_analytics_settings() {
    register_setting('for_you_google_analytics_options', 'for_you_google_analytics_ga4_code');
}
add_action('admin_init', 'for_you_google_analytics_settings');

// Add settings sections and fields
function for_you_google_analytics_settings_fields() {
    add_settings_section(
        'for_you_google_analytics_section',
        'Google Analytics (GA4) Tracking Code',
        'for_you_google_analytics_section_callback',
        'for_you_google_analytics'
    );

    add_settings_field(
        'for_you_google_analytics_ga4_code',
        'GA4 Tracking Code',
        'for_you_google_analytics_ga4_code_callback',
        'for_you_google_analytics',
        'for_you_google_analytics_section'
    );
}
add_action('admin_init', 'for_you_google_analytics_settings_fields');

// Callback functions for settings sections and fields
function for_you_google_analytics_section_callback() {
    echo '<p>Enter your Google Analytics (GA4) tracking code below:</p>';
}

function for_you_google_analytics_ga4_code_callback() {
    $ga4_code = get_option('for_you_google_analytics_ga4_code');
    echo '<input type="text" name="for_you_google_analytics_ga4_code" value="' . esc_attr($ga4_code) . '" />';
}

// Output the selected GA4 tracking code in the <head> section
function for_you_google_analytics_output() {
    $ga4_code = get_option('for_you_google_analytics_ga4_code');

    if (!empty($ga4_code)) {
        ?>
        <!-- Global site tag (gtag.js) - Google Analytics (GA4) -->
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga4_code); ?>"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() { dataLayer.push(arguments); }
            gtag('js', new Date());

            gtag('config', '<?php echo esc_attr($ga4_code); ?>');
        </script>
        <?php
    }
}
add_action('wp_head', 'for_you_google_analytics_output', 10);
