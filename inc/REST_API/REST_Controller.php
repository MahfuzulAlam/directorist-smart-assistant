<?php
/**
 * REST API Controller
 *
 * @package DirectoristAIAgents
 */

namespace DirectoristAIAgents\REST_API;

use DirectoristAIAgents\Settings\Settings_Manager;
use DirectoristAIAgents\Service\Chat_Service;
use DirectoristAIAgents\Vector\Vector_Sync;
use DirectoristAIAgents\Helpers\Listing_Helper;

/**
 * REST API Controller class
 */
class REST_Controller {

	/**
	 * Instance
	 *
	 * @var REST_Controller
	 */
	private static $instance = null;

	/**
	 * Namespace
	 *
	 * @var string
	 */
	private $namespace = 'directorist-ai-agents/v1';

	/**
	 * Get instance
	 *
	 * @return REST_Controller
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor
	 */
	private function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	/**
	 * Register REST API routes
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
			// Settings endpoints.
			$this->namespace = $namespace;
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( $this, 'check_admin_permission' ),
					'args'                => $this->get_settings_args(),
				),
			)
		);

			// Chat endpoint (public, rate-limited).
			register_rest_route(
				$this->namespace,
				'/chat',
				array(
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'handle_chat' ),
						'permission_callback' => array( $this, 'check_chat_permission' ),
						'args'                => array(
							'message'      => array(
								'type'              => 'string',
								'required'          => true,
								'sanitize_callback' => 'sanitize_textarea_field',
							),
							'conversation' => array(
								'type'     => 'array',
								'required' => false,
								'default'  => array(),
							),
						),
					),
				)
			);

			// Listings endpoint (admin-only).
			register_rest_route(
				$this->namespace,
				'/listings',
				array(
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'get_listings' ),
						'permission_callback' => array( $this, 'check_admin_permission' ),
					),
				)
			);

			// Directory types endpoint.
			register_rest_route(
				$this->namespace,
				'/directory-types',
				array(
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'get_directory_types' ),
						'permission_callback' => array( $this, 'check_admin_permission' ),
					),
				)
			);

			// Listing statuses endpoint.
			register_rest_route(
				$this->namespace,
				'/listing-statuses',
				array(
					array(
						'methods'             => 'GET',
						'callback'            => array( $this, 'get_listing_statuses' ),
						'permission_callback' => array( $this, 'check_admin_permission' ),
					),
				)
			);

			// Bulk sync endpoint.
			register_rest_route(
				$this->namespace,
				'/bulk-sync',
				array(
					array(
						'methods'             => 'POST',
						'callback'            => array( $this, 'handle_bulk_sync' ),
						'permission_callback' => array( $this, 'check_admin_permission' ),
						'args'                => array(
							'post_ids' => array(
								'type'     => 'array',
								'required' => false,
								'default'  => array(),
							),
						),
					),
				)
			);
		}
	}

	/**
	 * Admin permission check.
	 *
	 * @return bool
	 */
	public function check_admin_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Chat endpoint permission check with rate limiting.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return bool|\WP_Error
	 */
	public function check_chat_permission( \WP_REST_Request $request ) {
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
	 * Transient-based rate limiter keyed by client IP.
	 *
	 * @return bool True if within limits, false if rate-limited.
	 */
	private function check_rate_limit(): bool {
		$ip  = $this->get_client_ip();
		$key = 'daia_rate_' . md5( $ip );

		/** Filter the maximum chat requests per window (default 20). */
		$max_requests = apply_filters( 'daia_rate_limit_requests', 20 );

		/** Filter the rate-limit window in seconds (default 60). */
		$window = apply_filters( 'daia_rate_limit_window', MINUTE_IN_SECONDS );

		$data = get_transient( $key );

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
	 * Get the client IP address.
	 *
	 * @return string
	 */
	private function get_client_ip(): string {
		$ip_keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' );

		foreach ( $ip_keys as $key ) {
			if ( ! empty( $_SERVER[ $key ] ) ) {
				$ip = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
				// X-Forwarded-For may contain comma-separated IPs; take the first.
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

	// ------------------------------------------------------------------
	// Route handlers
	// ------------------------------------------------------------------

	/**
	 * Get settings.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$settings = Settings_Manager::get_instance()->get_settings();

		if ( ! empty( $settings['api_key'] ) ) {
			$settings['api_key'] = 'sk-***';
		}
		if ( ! empty( $settings['vector_api_secret_key'] ) ) {
			$settings['vector_api_secret_key'] = '***';
		}

		return new \WP_REST_Response( $settings, 200 );
	}

	/**
	 * Save settings.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function save_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();

		$settings = array(
			'api_key'       => isset( $params['api_key'] ) ? sanitize_text_field( $params['api_key'] ) : '',
			'model'         => isset( $params['model'] ) ? sanitize_text_field( $params['model'] ) : 'gpt-3.5-turbo',
			'system_prompt' => isset( $params['system_prompt'] ) ? wp_kses_post( $params['system_prompt'] ) : '',
			'temperature'   => isset( $params['temperature'] ) ? floatval( $params['temperature'] ) : 0.7,
			'max_tokens'    => isset( $params['max_tokens'] ) ? intval( $params['max_tokens'] ) : 1000,
		);

		// Vector storage settings.
		$vector_fields = array(
			'vector_api_base_url'          => 'esc_url_raw',
			'vector_api_secret_key'        => 'sanitize_text_field',
			'vector_website_id'            => 'sanitize_text_field',
			'vector_embedding_model'       => 'sanitize_text_field',
			'vector_index_name'            => 'sanitize_text_field',
			'vector_namespace'             => 'sanitize_text_field',
		);

		foreach ( $vector_fields as $field => $sanitizer ) {
			if ( isset( $params[ $field ] ) ) {
				$settings[ $field ] = call_user_func( $sanitizer, $params[ $field ] );
			}
		}

		if ( isset( $params['vector_auto_sync'] ) ) {
			$settings['vector_auto_sync'] = (bool) $params['vector_auto_sync'];
		}

		$int_fields = array( 'vector_listing_chunk_size', 'vector_chunk_size', 'vector_chunk_overlap' );
		foreach ( $int_fields as $field ) {
			if ( isset( $params[ $field ] ) ) {
				$settings[ $field ] = intval( $params[ $field ] );
			}
		}

		if ( isset( $params['vector_sync_directory_types'] ) ) {
			$settings['vector_sync_directory_types'] = array_map( 'intval', $params['vector_sync_directory_types'] );
		}
		if ( isset( $params['vector_sync_listing_statuses'] ) ) {
			$settings['vector_sync_listing_statuses'] = array_map( 'sanitize_text_field', $params['vector_sync_listing_statuses'] );
		}

		// Chat module settings.
		if ( isset( $params['chat_agent_name'] ) ) {
			$settings['chat_agent_name'] = sanitize_text_field( $params['chat_agent_name'] );
		}
		if ( isset( $params['chat_widget_position'] ) ) {
			$settings['chat_widget_position'] = sanitize_text_field( $params['chat_widget_position'] );
		}
		if ( isset( $params['chat_widget_color'] ) ) {
			$settings['chat_widget_color'] = sanitize_hex_color( $params['chat_widget_color'] );
		}

		// Page chat settings.
		if ( isset( $params['page_chat_enabled'] ) ) {
			$settings['page_chat_enabled'] = (bool) $params['page_chat_enabled'];
		}
		$page_text_fields = array( 'page_chat_title', 'page_chat_welcome_message', 'page_chat_placeholder' );
		foreach ( $page_text_fields as $f ) {
			if ( isset( $params[ $f ] ) ) {
				$settings[ $f ] = sanitize_text_field( $params[ $f ] );
			}
		}
		if ( isset( $params['page_chat_primary_color'] ) ) {
			$settings['page_chat_primary_color'] = sanitize_hex_color( $params['page_chat_primary_color'] );
		}
		if ( isset( $params['page_chat_show_sidebar'] ) ) {
			$settings['page_chat_show_sidebar'] = (bool) $params['page_chat_show_sidebar'];
		}
		if ( isset( $params['page_chat_guest_enabled'] ) ) {
			$settings['page_chat_guest_enabled'] = (bool) $params['page_chat_guest_enabled'];
		}

		Settings_Manager::get_instance()->save_settings( $settings );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Settings saved successfully.', 'directorist-ai-agents' ),
			),
			200
		);
	}

	/**
	 * Handle chat request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_chat( \WP_REST_Request $request ): \WP_REST_Response {
		$params       = $request->get_json_params();
		$message      = isset( $params['message'] ) ? sanitize_textarea_field( $params['message'] ) : '';
		$conversation = isset( $params['conversation'] ) ? $params['conversation'] : array();

		if ( empty( $message ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Message is required.', 'directorist-ai-agents' ),
				),
				400
			);
		}

		$response = Chat_Service::get_instance()->process_message( $message, $conversation );

		if ( is_wp_error( $response ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $response->get_error_message(),
				),
				500
			);
		}

		return new \WP_REST_Response(
			array(
				'success'  => true,
				'response' => $response['message'] ?? '',
			),
			200
		);
	}

	/**
	 * Get listings (admin-only).
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_listings( \WP_REST_Request $request ): \WP_REST_Response {
		$post_type = Listing_Helper::get_post_type();

		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => 100,
			'post_status'    => 'publish',
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		$query    = new \WP_Query( $args );
		$posts    = $query->get_posts();
		$listings = array();

		foreach ( $posts as $post ) {
			$listings[] = array(
				'id'      => $post->ID,
				'title'   => $post->post_title,
				'content' => $post->post_content,
			);
		}

		return new \WP_REST_Response( $listings, 200 );
	}

	/**
	 * Get directory types.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_directory_types( \WP_REST_Request $request ): \WP_REST_Response {
		$types = get_terms(
			array(
				'taxonomy'   => Listing_Helper::get_type_taxonomy(),
				'hide_empty' => false,
			)
		);

		$directory_types = array();
		if ( ! is_wp_error( $types ) && ! empty( $types ) ) {
			foreach ( $types as $type ) {
				$directory_types[] = array(
					'id'   => $type->term_id,
					'slug' => $type->slug,
					'name' => $type->name,
				);
			}
		}

		return new \WP_REST_Response( $directory_types, 200 );
	}

	/**
	 * Get listing statuses.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_listing_statuses( \WP_REST_Request $request ): \WP_REST_Response {
		$statuses = get_post_statuses();

		$statuses['expired'] = __( 'Expired', 'directorist-ai-agents' );
		$statuses['pending'] = __( 'Pending', 'directorist-ai-agents' );
		$statuses['draft']   = __( 'Draft', 'directorist-ai-agents' );
		$statuses['publish'] = __( 'Published', 'directorist-ai-agents' );
		$statuses['private'] = __( 'Private', 'directorist-ai-agents' );
		$statuses['future']  = __( 'Scheduled', 'directorist-ai-agents' );

		$listing_statuses = array();
		foreach ( $statuses as $key => $label ) {
			$listing_statuses[] = array(
				'value' => $key,
				'label' => $label,
			);
		}

		return new \WP_REST_Response( $listing_statuses, 200 );
	}

	/**
	 * Handle bulk sync request.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_bulk_sync( \WP_REST_Request $request ): \WP_REST_Response {
		$params   = $request->get_json_params();
		$post_ids = isset( $params['post_ids'] ) ? array_map( 'intval', $params['post_ids'] ) : array();

		$results = Vector_Sync::get_instance()->batch_upsert_listings( $post_ids );

		if ( 0 === $results['total'] ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $results['errors'][0] ?? __( 'No listings found to sync.', 'directorist-ai-agents' ),
					'results' => $results,
				),
				400
			);
		}

		$message = sprintf(
			/* translators: %1$d: Success count, %2$d: Failed count, %3$d: Total count */
			__( 'Synced %1$d out of %3$d listings successfully. %2$d failed.', 'directorist-ai-agents' ),
			$results['success'],
			$results['failed'],
			$results['total']
		);

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => $message,
				'results' => $results,
			),
			200
		);
	}

	// ------------------------------------------------------------------
	// Settings argument definitions
	// ------------------------------------------------------------------

	/**
	 * Get the argument schema for the settings POST endpoint.
	 *
	 * @return array
	 */
	private function get_settings_args(): array {
		return array(
			'api_key'                      => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'model'                        => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'system_prompt'                => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'wp_kses_post',
			),
			'temperature'                  => array(
				'type'              => 'number',
				'required'          => false,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param >= 0 && $param <= 1;
				},
			),
			'max_tokens'                   => array(
				'type'              => 'integer',
				'required'          => false,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param > 0;
				},
			),
			'vector_api_base_url'          => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'esc_url_raw',
			),
			'vector_api_secret_key'        => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'vector_website_id'            => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'vector_auto_sync'             => array(
				'type'     => 'boolean',
				'required' => false,
			),
			'vector_listing_chunk_size'    => array(
				'type'              => 'integer',
				'required'          => false,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param >= 1 && $param <= 100;
				},
			),
			'vector_sync_directory_types'  => array(
				'type'     => 'array',
				'required' => false,
				'default'  => array(),
			),
			'vector_sync_listing_statuses' => array(
				'type'     => 'array',
				'required' => false,
				'default'  => array(),
			),
			'vector_chunk_size'            => array(
				'type'              => 'integer',
				'required'          => false,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param >= 100 && $param <= 2000;
				},
			),
			'vector_chunk_overlap'         => array(
				'type'              => 'integer',
				'required'          => false,
				'validate_callback' => function ( $param ) {
					return is_numeric( $param ) && $param >= 0 && $param <= 200;
				},
			),
			'vector_embedding_model'       => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'vector_index_name'            => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'vector_namespace'             => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'chat_agent_name'              => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
			),
			'chat_widget_position'         => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_text_field',
				'validate_callback' => function ( $param ) {
					return in_array( $param, array( 'bottom-right', 'bottom-left' ), true );
				},
			),
			'chat_widget_color'            => array(
				'type'              => 'string',
				'required'          => false,
				'sanitize_callback' => 'sanitize_hex_color',
			),
		);
	}
}
