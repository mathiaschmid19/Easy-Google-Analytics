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
delete_option( 'for_you_google_analytics_excluded_roles' );
