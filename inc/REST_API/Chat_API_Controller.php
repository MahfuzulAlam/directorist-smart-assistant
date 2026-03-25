<?php
/**
 * Chat API Controller
 *
 * REST endpoints for conversation and message management.
 *
 * @package DirectoristAIAgents
 */

namespace DirectoristAIAgents\REST_API;

use DirectoristAIAgents\Provider\Chat_Provider;
use DirectoristAIAgents\Service\Chat_Service;

/**
 * Chat API Controller class
 */
class Chat_API_Controller {

	/**
	 * Instance.
	 *
	 * @var Chat_API_Controller|null
	 */
	private static $instance = null;

	/**
	 * REST namespace.
	 *
	 * @var string
	 */
	private $namespace = 'directorist-ai-agents/v1';

	/**
	 * Chat provider.
	 *
	 * @var Chat_Provider
	 */
	private $provider;

	/**
	 * Get instance.
	 *
	 * @return Chat_API_Controller
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
	private function __construct() {
		$this->provider = Chat_Provider::get_instance();
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		$namespaces = array_unique(
			array(
				$this->namespace,
			)
		);

		foreach ( $namespaces as $namespace ) {
			$this->namespace = $namespace;

		// List conversations (public, rate-limited).
		register_rest_route(
			$this->namespace,
			'/conversations',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'list_conversations' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'session_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'source'     => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => '',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'create_conversation' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'session_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'source'     => array(
							'type'              => 'string',
							'required'          => false,
							'default'           => 'widget',
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Single conversation.
		register_rest_route(
			$this->namespace,
			'/conversations/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_conversation' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'session_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'PATCH',
					'callback'            => array( $this, 'update_conversation' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'session_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'title'      => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'delete_conversation' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'session_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
					),
				),
			)
		);

		// Send message (creates AI response too).
		register_rest_route(
			$this->namespace,
			'/conversations/(?P<id>\d+)/messages',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'send_message' ),
					'permission_callback' => array( $this, 'check_message_permission' ),
					'args'                => array(
						'session_id' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'message'    => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);

		// Admin: list all conversations.
		register_rest_route(
			$this->namespace,
			'/admin/conversations',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'admin_list_conversations' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => array(
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
						),
						'source'   => array(
							'type'    => 'string',
							'default' => '',
						),
					),
				),
			)
		);

		// Admin: view single conversation.
		register_rest_route(
			$this->namespace,
			'/admin/conversations/(?P<id>\d+)',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'admin_get_conversation' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'DELETE',
					'callback'            => array( $this, 'admin_delete_conversation' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
			)
		);

		}
	}

	// ------------------------------------------------------------------
	// Permission callbacks
	// ------------------------------------------------------------------

	/**
	 * Rate-limited permission for message sending.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return bool|\WP_Error
	 */
	public function check_message_permission( \WP_REST_Request $request ) {
		if ( ! $this->check_rate_limit() ) {
			return new \WP_Error(
				'rate_limited',
				__( 'Too many requests. Please try again later.', 'directorist-ai-agents' ),
				array( 'status' => 429 )
			);
		}
		return true;
	}

	/**
	 * Admin permission.
	 *
	 * @return bool
	 */
	public function check_admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	// ------------------------------------------------------------------
	// Public handlers
	// ------------------------------------------------------------------

	/**
	 * List conversations for the current session/user.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function list_conversations( \WP_REST_Request $request ): \WP_REST_Response {
		$session_id = $request->get_param( 'session_id' );
		$source     = $request->get_param( 'source' );
		$user_id    = get_current_user_id();

		$conversations = $this->provider->get_conversations( $session_id, $user_id, $source, 50, 0 );

		return new \WP_REST_Response( $conversations, 200 );
	}

	/**
	 * Create a new conversation.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function create_conversation( \WP_REST_Request $request ): \WP_REST_Response {
		$session_id = $request->get_param( 'session_id' );
		$source     = $request->get_param( 'source' );

		$conversation_id = $this->provider->create_conversation(
			array(
				'session_id' => $session_id,
				'user_id'    => get_current_user_id(),
				'source'     => $source,
				'ip_address' => $this->get_client_ip(),
				'user_agent' => isset( $_SERVER['HTTP_USER_AGENT'] )
					? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
					: '',
			)
		);

		if ( false === $conversation_id ) {
			return new \WP_REST_Response(
				array( 'success' => false, 'message' => __( 'Failed to create conversation.', 'directorist-ai-agents' ) ),
				500
			);
		}

		$conversation = $this->provider->get_conversation( $conversation_id );

		return new \WP_REST_Response(
			array( 'success' => true, 'conversation' => $conversation ),
			201
		);
	}

	/**
	 * Get a conversation with its messages.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function get_conversation( \WP_REST_Request $request ): \WP_REST_Response {
		$id         = (int) $request->get_param( 'id' );
		$session_id = $request->get_param( 'session_id' );
		$user_id    = get_current_user_id();

		if ( ! $this->provider->verify_access( $id, $session_id, $user_id ) ) {
			return new \WP_REST_Response(
				array( 'success' => false, 'message' => __( 'Conversation not found.', 'directorist-ai-agents' ) ),
				404
			);
		}

		$conversation = $this->provider->get_conversation( $id );
		$messages     = $this->provider->get_messages( $id );

		$conversation['messages'] = $messages;

		return new \WP_REST_Response( $conversation, 200 );
	}

	/**
	 * Update a conversation (rename).
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function update_conversation( \WP_REST_Request $request ): \WP_REST_Response {
		$id         = (int) $request->get_param( 'id' );
		$session_id = $request->get_param( 'session_id' );
		$user_id    = get_current_user_id();

		if ( ! $this->provider->verify_access( $id, $session_id, $user_id ) ) {
			return new \WP_REST_Response(
				array( 'success' => false, 'message' => __( 'Conversation not found.', 'directorist-ai-agents' ) ),
				404
			);
		}

		$title = $request->get_param( 'title' );
		$this->provider->update_conversation( $id, array( 'title' => $title ) );

		return new \WP_REST_Response(
			array( 'success' => true, 'message' => __( 'Conversation updated.', 'directorist-ai-agents' ) ),
			200
		);
	}

	/**
	 * Delete a conversation.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function delete_conversation( \WP_REST_Request $request ): \WP_REST_Response {
		$id         = (int) $request->get_param( 'id' );
		$session_id = $request->get_param( 'session_id' );
		$user_id    = get_current_user_id();

		if ( ! $this->provider->verify_access( $id, $session_id, $user_id ) ) {
			return new \WP_REST_Response(
				array( 'success' => false, 'message' => __( 'Conversation not found.', 'directorist-ai-agents' ) ),
				404
			);
		}

		$this->provider->delete_conversation( $id );

		return new \WP_REST_Response(
			array( 'success' => true, 'message' => __( 'Conversation deleted.', 'directorist-ai-agents' ) ),
			200
		);
	}

	/**
	 * Send a message and get AI response.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function send_message( \WP_REST_Request $request ): \WP_REST_Response {
		$conversation_id = (int) $request->get_param( 'id' );
		$session_id      = $request->get_param( 'session_id' );
		$message         = $request->get_param( 'message' );
		$user_id         = get_current_user_id();

		if ( empty( $message ) ) {
			return new \WP_REST_Response(
				array( 'success' => false, 'message' => __( 'Message is required.', 'directorist-ai-agents' ) ),
				400
			);
		}

		if ( ! $this->provider->verify_access( $conversation_id, $session_id, $user_id ) ) {
			return new \WP_REST_Response(
				array( 'success' => false, 'message' => __( 'Conversation not found.', 'directorist-ai-agents' ) ),
				404
			);
		}

		// Collect existing conversation history BEFORE inserting the new message.
		// Chat_Service will append the current user message itself.
		$db_messages  = $this->provider->get_messages( $conversation_id );
		$conversation = array();
		foreach ( $db_messages as $msg ) {
			if ( 'system' === $msg['role'] ) {
				continue;
			}
			$conversation[] = array(
				'role'    => $msg['role'],
				'content' => $msg['content'],
			);
		}

		// Store user message in DB.
		$this->provider->add_message( $conversation_id, 'user', $message );

		// Auto-title from first message.
		$this->provider->auto_title( $conversation_id, $message );

		// Process through AI.
		$response = Chat_Service::get_instance()->process_message( $message, $conversation );

		if ( is_wp_error( $response ) ) {
			return new \WP_REST_Response(
				array( 'success' => false, 'message' => $response->get_error_message() ),
				500
			);
		}

		$assistant_content = $response['message'] ?? '';

		// Store assistant response in DB.
		$this->provider->add_message( $conversation_id, 'assistant', $assistant_content );

		return new \WP_REST_Response(
			array(
				'success'         => true,
				'response'        => $assistant_content,
				'conversation_id' => $conversation_id,
			),
			200
		);
	}

	// ------------------------------------------------------------------
	// Admin handlers
	// ------------------------------------------------------------------

	/**
	 * Admin: list all conversations.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function admin_list_conversations( \WP_REST_Request $request ): \WP_REST_Response {
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$source   = $request->get_param( 'source' );
		$offset   = ( $page - 1 ) * $per_page;

		$conversations = $this->provider->get_all_conversations( $per_page, $offset, $source );
		$total         = $this->provider->count_conversations( $source );

		return new \WP_REST_Response(
			array(
				'conversations' => $conversations,
				'total'         => $total,
				'page'          => $page,
				'per_page'      => $per_page,
				'total_pages'   => (int) ceil( $total / $per_page ),
			),
			200
		);
	}

	/**
	 * Admin: get a single conversation with messages.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function admin_get_conversation( \WP_REST_Request $request ): \WP_REST_Response {
		$id           = (int) $request->get_param( 'id' );
		$conversation = $this->provider->get_conversation( $id );

		if ( ! $conversation ) {
			return new \WP_REST_Response(
				array( 'success' => false, 'message' => __( 'Conversation not found.', 'directorist-ai-agents' ) ),
				404
			);
		}

		$conversation['messages'] = $this->provider->get_messages( $id );

		return new \WP_REST_Response( $conversation, 200 );
	}

	/**
	 * Admin: delete a conversation.
	 *
	 * @param \WP_REST_Request $request Request.
	 * @return \WP_REST_Response
	 */
	public function admin_delete_conversation( \WP_REST_Request $request ): \WP_REST_Response {
		$id = (int) $request->get_param( 'id' );
		$this->provider->delete_conversation( $id );

		return new \WP_REST_Response(
			array( 'success' => true, 'message' => __( 'Conversation deleted.', 'directorist-ai-agents' ) ),
			200
		);
	}

	// ------------------------------------------------------------------
	// Helpers
	// ------------------------------------------------------------------

	/**
	 * Transient-based rate limiter.
	 *
	 * @return bool
	 */
	private function check_rate_limit(): bool {
		$ip  = $this->get_client_ip();
		$key = 'daia_rate_' . md5( $ip );

		$max_requests = apply_filters( 'daia_rate_limit_requests', 20 );
		$window       = apply_filters( 'daia_rate_limit_window', MINUTE_IN_SECONDS );
		$data         = get_transient( $key );

		if ( false === $data ) {
			set_transient( $key, array( 'count' => 1 ), $window );
			return true;
		}

		if ( $data['count'] >= $max_requests ) {
			return false;
		}

		$data['count']++;
		set_transient( $key, $data, $window );
		return true;
	}

	/**
	 * Get client IP.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				if ( strpos( $ip, ',' ) !== false ) {
					$ip = trim( explode( ',', $ip )[0] );
				}
				if ( filter_var( $ip, FILTER_VALIDATE_IP ) ) {
					return $ip;
				}
			}
		}
		return '0.0.0.0';
	}
}
