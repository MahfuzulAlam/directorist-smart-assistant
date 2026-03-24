<?php
/**
 * Chat Provider
 *
 * Database CRUD operations for conversations and messages.
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Provider;

/**
 * Chat Provider class
 */
class Chat_Provider {

	/**
	 * Instance.
	 *
	 * @var Chat_Provider|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return Chat_Provider
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {}

	// ------------------------------------------------------------------
	// Conversations
	// ------------------------------------------------------------------

	/**
	 * Create a new conversation.
	 *
	 * @param array $data {
	 *     @type string $session_id  Client session identifier.
	 *     @type int    $user_id     WordPress user ID (0 for guests).
	 *     @type string $title       Conversation title.
	 *     @type string $source      'widget' or 'page'.
	 *     @type string $ip_address  Client IP.
	 *     @type string $user_agent  Client user-agent.
	 * }
	 * @return int|false Conversation ID or false on failure.
	 */
	public function create_conversation( array $data ) {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$result = $wpdb->insert(
			Schema::conversations_table(),
			array(
				'session_id'  => sanitize_text_field( $data['session_id'] ?? '' ),
				'user_id'     => absint( $data['user_id'] ?? 0 ),
				'title'       => sanitize_text_field( $data['title'] ?? '' ),
				'source'      => in_array( ( $data['source'] ?? 'widget' ), array( 'widget', 'page' ), true )
					? $data['source']
					: 'widget',
				'status'      => 'active',
				'ip_address'  => sanitize_text_field( $data['ip_address'] ?? '' ),
				'user_agent'  => sanitize_text_field( substr( $data['user_agent'] ?? '', 0, 500 ) ),
				'created_at'  => $now,
				'updated_at'  => $now,
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return false !== $result ? (int) $wpdb->insert_id : false;
	}

	/**
	 * Get a single conversation by ID.
	 *
	 * @param int $id Conversation ID.
	 * @return array|null
	 */
	public function get_conversation( int $id ) {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE id = %d",
				Schema::conversations_table(),
				$id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * List conversations for a given session or user.
	 *
	 * @param string $session_id Session identifier.
	 * @param int    $user_id    WordPress user ID.
	 * @param string $source     Filter by source ('widget', 'page', or '' for all).
	 * @param int    $limit      Max results.
	 * @param int    $offset     Offset.
	 * @return array
	 */
	public function get_conversations( string $session_id, int $user_id = 0, string $source = '', int $limit = 50, int $offset = 0 ): array {
		global $wpdb;

		$table = Schema::conversations_table();
		$where = array( 'status = %s' );
		$args  = array( 'active' );

		if ( $user_id > 0 ) {
			$where[] = 'user_id = %d';
			$args[]  = $user_id;
		} else {
			$where[] = 'session_id = %s';
			$args[]  = $session_id;
		}

		if ( ! empty( $source ) && in_array( $source, array( 'widget', 'page' ), true ) ) {
			$where[] = 'source = %s';
			$args[]  = $source;
		}

		$where_clause = implode( ' AND ', $where );
		$args[]       = $limit;
		$args[]       = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY updated_at DESC LIMIT %d OFFSET %d",
				...$args
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Update a conversation.
	 *
	 * @param int   $id   Conversation ID.
	 * @param array $data Fields to update (title, status).
	 * @return bool
	 */
	public function update_conversation( int $id, array $data ): bool {
		global $wpdb;

		$allowed = array( 'title', 'status' );
		$update  = array( 'updated_at' => current_time( 'mysql', true ) );
		$format  = array( '%s' );

		foreach ( $allowed as $field ) {
			if ( isset( $data[ $field ] ) ) {
				$update[ $field ] = sanitize_text_field( $data[ $field ] );
				$format[]         = '%s';
			}
		}

		return false !== $wpdb->update(
			Schema::conversations_table(),
			$update,
			array( 'id' => $id ),
			$format,
			array( '%d' )
		);
	}

	/**
	 * Soft-delete a conversation (set status to 'deleted').
	 *
	 * @param int $id Conversation ID.
	 * @return bool
	 */
	public function delete_conversation( int $id ): bool {
		return $this->update_conversation( $id, array( 'status' => 'deleted' ) );
	}

	/**
	 * Verify that a session or user owns a conversation.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $session_id      Session identifier.
	 * @param int    $user_id         WordPress user ID.
	 * @return bool
	 */
	public function verify_access( int $conversation_id, string $session_id, int $user_id = 0 ): bool {
		$conversation = $this->get_conversation( $conversation_id );

		if ( ! $conversation || 'deleted' === $conversation['status'] ) {
			return false;
		}

		if ( $user_id > 0 && (int) $conversation['user_id'] === $user_id ) {
			return true;
		}

		return $conversation['session_id'] === $session_id;
	}

	/**
	 * Admin: list all conversations with optional filters.
	 *
	 * @param int    $limit  Max results.
	 * @param int    $offset Offset.
	 * @param string $source Filter by source.
	 * @param string $status Filter by status.
	 * @return array
	 */
	public function get_all_conversations( int $limit = 50, int $offset = 0, string $source = '', string $status = '' ): array {
		global $wpdb;

		$table = Schema::conversations_table();
		$where = array( '1=1' );
		$args  = array();

		if ( ! empty( $source ) ) {
			$where[] = 'source = %s';
			$args[]  = $source;
		}

		if ( ! empty( $status ) ) {
			$where[] = 'status = %s';
			$args[]  = $status;
		} else {
			$where[] = "status != 'deleted'";
		}

		$where_clause = implode( ' AND ', $where );
		$args[]       = $limit;
		$args[]       = $offset;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM {$table} WHERE {$where_clause} ORDER BY updated_at DESC LIMIT %d OFFSET %d",
				...$args
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Count conversations with optional filters.
	 *
	 * @param string $source Filter by source.
	 * @param string $status Filter by status.
	 * @return int
	 */
	public function count_conversations( string $source = '', string $status = '' ): int {
		global $wpdb;

		$table = Schema::conversations_table();
		$where = array( '1=1' );
		$args  = array();

		if ( ! empty( $source ) ) {
			$where[] = 'source = %s';
			$args[]  = $source;
		}

		if ( ! empty( $status ) ) {
			$where[] = 'status = %s';
			$args[]  = $status;
		} else {
			$where[] = "status != 'deleted'";
		}

		$where_clause = implode( ' AND ', $where );

		if ( ! empty( $args ) ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			return (int) $wpdb->get_var(
				$wpdb->prepare(
					"SELECT COUNT(*) FROM {$table} WHERE {$where_clause}",
					...$args
				)
			);
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		return (int) $wpdb->get_var( "SELECT COUNT(*) FROM {$table} WHERE {$where_clause}" );
	}

	// ------------------------------------------------------------------
	// Messages
	// ------------------------------------------------------------------

	/**
	 * Add a message to a conversation.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $role            'user', 'assistant', or 'system'.
	 * @param string $content         Message content.
	 * @param int    $tokens_used     Token count (optional).
	 * @return int|false Message ID or false on failure.
	 */
	public function add_message( int $conversation_id, string $role, string $content, int $tokens_used = 0 ) {
		global $wpdb;

		$allowed_roles = array( 'user', 'assistant', 'system' );
		if ( ! in_array( $role, $allowed_roles, true ) ) {
			return false;
		}

		$now = current_time( 'mysql', true );

		$result = $wpdb->insert(
			Schema::messages_table(),
			array(
				'conversation_id' => $conversation_id,
				'role'            => $role,
				'content'         => $content,
				'tokens_used'     => $tokens_used,
				'created_at'      => $now,
			),
			array( '%d', '%s', '%s', '%d', '%s' )
		);

		if ( false === $result ) {
			return false;
		}

		// Capture insert_id immediately — the UPDATE below resets it.
		$message_id = (int) $wpdb->insert_id;

		// Touch conversation updated_at.
		$wpdb->update(
			Schema::conversations_table(),
			array( 'updated_at' => $now ),
			array( 'id' => $conversation_id ),
			array( '%s' ),
			array( '%d' )
		);

		return $message_id;
	}

	/**
	 * Get messages for a conversation.
	 *
	 * @param int $conversation_id Conversation ID.
	 * @param int $limit           Max results (0 = all).
	 * @param int $offset          Offset.
	 * @return array
	 */
	public function get_messages( int $conversation_id, int $limit = 0, int $offset = 0 ): array {
		global $wpdb;

		$table = Schema::messages_table();

		if ( $limit > 0 ) {
			return $wpdb->get_results(
				$wpdb->prepare(
					"SELECT * FROM %i WHERE conversation_id = %d ORDER BY created_at ASC LIMIT %d OFFSET %d",
					$table,
					$conversation_id,
					$limit,
					$offset
				),
				ARRAY_A
			) ?: array();
		}

		return $wpdb->get_results(
			$wpdb->prepare(
				"SELECT * FROM %i WHERE conversation_id = %d ORDER BY created_at ASC",
				$table,
				$conversation_id
			),
			ARRAY_A
		) ?: array();
	}

	/**
	 * Auto-generate a conversation title from the first user message.
	 *
	 * @param int    $conversation_id Conversation ID.
	 * @param string $first_message   First user message text.
	 * @return void
	 */
	public function auto_title( int $conversation_id, string $first_message ): void {
		$conversation = $this->get_conversation( $conversation_id );

		if ( $conversation && empty( $conversation['title'] ) ) {
			$title = wp_trim_words( $first_message, 8, '...' );
			$this->update_conversation( $conversation_id, array( 'title' => $title ) );
		}
	}
}
