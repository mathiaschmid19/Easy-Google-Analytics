<?php
/**
 * Fired when the plugin is uninstalled. Removes all options this plugin
 * has ever stored, including options from earlier plugin versions.
 */

// If uninstall not called from WordPress, then exit.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'for_you_google_analytics_ga4_code' );
delete_option( 'for_you_google_analytics_gtm_id' );
delete_option( 'for_you_google_analytics_consent_banner_enabled' );
delete_option( 'for_you_google_analytics_track_outbound' );
delete_option( 'for_you_google_analytics_track_downloads' );
delete_option( 'for_you_google_analytics_track_scroll' );
delete_option( 'for_you_google_analytics_track_forms' );
delete_option( 'for_you_google_analytics_banner_palette' );
delete_option( 'for_you_google_analytics_banner_bg_color' );
delete_option( 'for_you_google_analytics_banner_text_color' );
delete_option( 'for_you_google_analytics_banner_accept_color' );
delete_option( 'for_you_google_analytics_banner_reject_color' );
delete_option( 'for_you_google_analytics_banner_layout' );
delete_option( 'for_you_google_analytics_banner_message' );
delete_option( 'for_you_google_analytics_banner_accept_label' );
delete_option( 'for_you_google_analytics_banner_reject_label' );
delete_option( 'for_you_google_analytics_banner_privacy_url' );
