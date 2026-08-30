<?php
 /* For more information, see the following discussion:
 * https://github.com/tommcfarlin/WordPress-Plugin-Boilerplate/pull/123#issuecomment-28541913
 *
 * @link       #
 * @since      1.0.0
 *
 * @package    Plugin_Name
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