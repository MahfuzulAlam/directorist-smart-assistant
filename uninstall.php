<?php
/**
 * Uninstall handler — runs when the plugin is deleted via the WordPress admin.
 *
 * Cleans up all plugin data: options, transients, custom tables, and post meta.
 *
 * @package DirectoristSmartAssistant
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

global $wpdb;

// Drop custom tables.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}daia_messages" );
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}daia_conversations" );

// Remove plugin options.
delete_option( 'directorist_smart_assistant_settings' );
delete_option( 'daia_db_version' );

// Remove transients.
delete_transient( 'directorist_smart_assistant_listings' );

// Remove rate-limit transients.
$wpdb->query(
	"DELETE FROM {$wpdb->options}
	 WHERE option_name LIKE '_transient_daia_rate_%'
	    OR option_name LIKE '_transient_timeout_daia_rate_%'"
);

// Remove post meta added by the plugin.
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_vector_sync' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_vector_sync_date' ) );
$wpdb->delete( $wpdb->postmeta, array( 'meta_key' => '_upsert_id' ) );
