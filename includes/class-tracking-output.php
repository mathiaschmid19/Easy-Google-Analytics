<?php
if (!defined('WPINC')) {
    die;
}

class EGA_Tracking_Output {

    public static function init() {
        add_action('wp_head', array(__CLASS__, 'output_consent_defaults'), 1);
        add_action('wp_head', array(__CLASS__, 'output_tracking_scripts'), 10);
    }

    public static function is_configured() {
        $ga4 = get_option('for_you_google_analytics_ga4_code');
        $gtm = get_option('for_you_google_analytics_gtm_id');
        return !empty($ga4) || !empty($gtm);
    }

    public static function output_consent_defaults() {
        if (!self::is_configured()) {
            return;
        }
        ?>
        <!-- Google Consent Mode v2 defaults (Easy Google Analytics) -->
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag() { dataLayer.push(arguments); }
            gtag('consent', 'default', {
                'ad_storage': 'denied',
                'analytics_storage': 'denied',
                'ad_user_data': 'denied',
                'ad_personalization': 'denied',
                'wait_for_update': 500
            });
        </script>
        <?php
    }

    public static function output_tracking_scripts() {
        $ga4_code = get_option('for_you_google_analytics_ga4_code');
        $gtm_id   = get_option('for_you_google_analytics_gtm_id');

        if (!empty($gtm_id)) {
            ?>
            <!-- Google Tag Manager -->
            <script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer','<?php echo esc_js($gtm_id); ?>');</script>
            <!-- End Google Tag Manager -->
            <?php
        }

        if (!empty($ga4_code)) {
            ?>
            <!-- Global site tag (gtag.js) - Google Analytics (GA4) -->
            <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo esc_attr($ga4_code); ?>"></script>
            <script>
                window.dataLayer = window.dataLayer || [];
                function gtag() { dataLayer.push(arguments); }
                gtag('js', new Date());
                gtag('config', '<?php echo esc_js($ga4_code); ?>');
            </script>
            <?php
        }
    }
}
