<?php
/**
 * Uninstall handler — runs when the plugin is deleted via the WordPress admin.
 *
 * Cleans up all plugin data: options, transients, and post meta.
 *
 * @package DirectoristSmartAssistant
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove plugin settings.
delete_option( 'directorist_smart_assistant_settings' );

// Remove transients.
delete_transient( 'directorist_smart_assistant_listings' );

// Remove rate-limit transients (pattern: dsa_rate_*).
global $wpdb;
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_dsa_rate_%'
	    OR option_name LIKE '_transient_timeout_dsa_rate_%'"
);

// Remove post meta added by the plugin.
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_vector_sync' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_vector_sync_date' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_upsert_id' ) );
