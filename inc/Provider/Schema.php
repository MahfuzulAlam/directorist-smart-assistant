<?php
/**
 * Database Schema
 *
 * Creates and manages custom tables for conversation storage.
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Provider;

/**
 * Schema class
 */
class Schema {

	const DB_VERSION     = '1.0';
	const VERSION_OPTION = 'dsa_db_version';

	/**
	 * Get the conversations table name (with WP prefix).
	 *
	 * @return string
	 */
	public static function conversations_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'dsa_conversations';
	}

	/**
	 * Get the messages table name (with WP prefix).
	 *
	 * @return string
	 */
	public static function messages_table(): string {
		global $wpdb;
		return $wpdb->prefix . 'dsa_messages';
	}

	/**
	 * Create or update tables. Safe to call repeatedly (uses dbDelta).
	 *
	 * @return void
	 */
	public static function create_tables(): void {
		global $wpdb;

		$charset_collate    = $wpdb->get_charset_collate();
		$conversations_table = self::conversations_table();
		$messages_table      = self::messages_table();

		$sql = "CREATE TABLE {$conversations_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			session_id varchar(64) NOT NULL DEFAULT '',
			user_id bigint(20) unsigned NOT NULL DEFAULT 0,
			title varchar(255) NOT NULL DEFAULT '',
			source varchar(20) NOT NULL DEFAULT 'widget',
			status varchar(20) NOT NULL DEFAULT 'active',
			ip_address varchar(45) NOT NULL DEFAULT '',
			user_agent text NOT NULL,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			updated_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY session_id (session_id),
			KEY user_id (user_id),
			KEY status_created (status, created_at),
			KEY source (source)
		) {$charset_collate};

		CREATE TABLE {$messages_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			conversation_id bigint(20) unsigned NOT NULL,
			role varchar(20) NOT NULL DEFAULT 'user',
			content longtext NOT NULL,
			tokens_used int(10) unsigned NOT NULL DEFAULT 0,
			created_at datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY conversation_id (conversation_id),
			KEY conv_created (conversation_id, created_at)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );

		update_option( self::VERSION_OPTION, self::DB_VERSION );
	}

	/**
	 * Drop all custom tables. Used on uninstall.
	 *
	 * @return void
	 */
	public static function drop_tables(): void {
		global $wpdb;

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dsa_messages" );
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.SchemaChange
		$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dsa_conversations" );

		delete_option( self::VERSION_OPTION );
	}

	/**
	 * Check if the schema needs an upgrade and run it.
	 *
	 * @return void
	 */
	public static function maybe_upgrade(): void {
		$installed_version = get_option( self::VERSION_OPTION, '0' );

		if ( version_compare( $installed_version, self::DB_VERSION, '<' ) ) {
			self::create_tables();
		}
	}
}
