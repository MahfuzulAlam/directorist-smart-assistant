<?php
/**
 * REST API Controller
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\REST_API;

use DirectoristSmartAssistant\Settings\Settings_Manager;
use DirectoristSmartAssistant\Vector\Vector_Query;
use DirectoristSmartAssistant\Vector\Vector_Sync;
use DirectoristSmartAssistant\Triage\Triage_Client;
use DirectoristSmartAssistant\Usage\Usage_Client;

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
	private $namespace = 'directorist-smart-assistant/v1';

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
		// Settings endpoint
		register_rest_route(
			$this->namespace,
			'/settings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'save_settings' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'api_key'      => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'model'        => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'system_prompt' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'wp_kses_post',
						),
						'temperature' => array(
							'type'              => 'number',
							'required'          => false,
							'validate_callback' => function( $param ) {
								return is_numeric( $param ) && $param >= 0 && $param <= 1;
							},
						),
						'max_tokens'  => array(
							'type'              => 'integer',
							'required'          => false,
							'validate_callback' => function( $param ) {
								return is_numeric( $param ) && $param > 0;
							},
						),
						'vector_api_base_url' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'esc_url_raw',
						),
						'vector_api_secret_key' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'vector_website_id' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'vector_auto_sync' => array(
							'type'              => 'boolean',
							'required'          => false,
						),
						'vector_listing_chunk_size' => array(
							'type'              => 'integer',
							'required'          => false,
							'validate_callback' => function( $param ) {
								return is_numeric( $param ) && $param >= 1 && $param <= 100;
							},
						),
						'vector_sync_directory_types' => array(
							'type'     => 'array',
							'required' => false,
							'default'  => array(),
						),
						'vector_sync_listing_statuses' => array(
							'type'     => 'array',
							'required' => false,
							'default'  => array(),
						),
						'vector_chunk_size' => array(
							'type'              => 'integer',
							'required'          => false,
							'validate_callback' => function( $param ) {
								return is_numeric( $param ) && $param >= 100 && $param <= 2000;
							},
						),
						'vector_chunk_overlap' => array(
							'type'              => 'integer',
							'required'          => false,
							'validate_callback' => function( $param ) {
								return is_numeric( $param ) && $param >= 0 && $param <= 200;
							},
						),
						'vector_embedding_model' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'vector_index_name' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'vector_namespace' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'chat_agent_name' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'chat_widget_position' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_text_field',
							'validate_callback' => function( $param ) {
								return in_array( $param, array( 'bottom-right', 'bottom-left' ), true );
							},
						),
						'chat_widget_color' => array(
							'type'              => 'string',
							'required'          => false,
							'sanitize_callback' => 'sanitize_hex_color',
						),
					),
				),
			)
		);

		// Chat endpoint
		register_rest_route(
			$this->namespace,
			'/chat',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_chat' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'message' => array(
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

		// Listings endpoint
		register_rest_route(
			$this->namespace,
			'/listings',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_listings' ),
					'permission_callback' => '__return_true',
				),
			)
		);

		// Directory types endpoint
		register_rest_route(
			$this->namespace,
			'/directory-types',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_directory_types' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Listing statuses endpoint
		register_rest_route(
			$this->namespace,
			'/listing-statuses',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_listing_statuses' ),
					'permission_callback' => array( $this, 'check_permission' ),
				),
			)
		);

		// Bulk sync endpoint
		register_rest_route(
			$this->namespace,
			'/bulk-sync',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_bulk_sync' ),
					'permission_callback' => array( $this, 'check_permission' ),
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

		// Usage and cost for this website.
		register_rest_route(
			$this->namespace,
			'/usage',
			array(
				array(
					'methods'             => 'GET',
					'callback'            => array( $this, 'get_usage' ),
					'permission_callback' => array( $this, 'check_permission' ),
					'args'                => array(
						'days'    => array(
							'type'     => 'integer',
							'required' => false,
							'default'  => 30,
						),
						'refresh' => array(
							'type'     => 'boolean',
							'required' => false,
							'default'  => false,
						),
					),
				),
			)
		);

		// Symptom triage endpoint, used by the [symptom-based-search] shortcode.
		register_rest_route(
			$this->namespace,
			'/triage',
			array(
				array(
					'methods'             => 'POST',
					'callback'            => array( $this, 'handle_triage' ),
					'permission_callback' => '__return_true',
					'args'                => array(
						'session_id'  => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_text_field',
						),
						'description' => array(
							'type'              => 'string',
							'required'          => true,
							'sanitize_callback' => 'sanitize_textarea_field',
						),
					),
				),
			)
		);
	}

	/**
	 * Usage and cost for this website, over the requested window.
	 *
	 * Cached briefly: usage totals do not move second to second, and the tab
	 * re-reads them on every range change.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_usage( \WP_REST_Request $request ): \WP_REST_Response {
		$days    = (int) $request->get_param( 'days' );
		$refresh = (bool) $request->get_param( 'refresh' );

		// 0 means all time; anything else is clamped to a sane window.
		if ( $days < 0 ) {
			$days = 30;
		}

		if ( $days > 365 ) {
			$days = 365;
		}

		$start = '';
		$end   = '';
		$until = '';

		if ( $days > 0 ) {
			// Site-local dates: an admin reading "last 7 days" means their own
			// days, not UTC's.
			$now = current_datetime();

			$start = $now->modify( '-' . ( $days - 1 ) . ' days' )->format( 'Y-m-d' );
			$until = $now->format( 'Y-m-d' );

			// The service treats end_date as midnight at the start of that day,
			// so asking for "today" drops everything that happened today. Ask
			// for tomorrow and report the window we actually meant.
			$end = $now->modify( '+1 day' )->format( 'Y-m-d' );
		}

		// Scoped to the service + website in use, so switching either does not
		// serve the previous one's cached totals.
		$cache_key = 'dsa_usage_' . Usage_Client::get_instance()->get_cache_scope() . '_' . $days;

		if ( ! $refresh ) {
			$cached = get_transient( $cache_key );

			if ( is_array( $cached ) ) {
				$cached['cached'] = true;

				return new \WP_REST_Response(
					array(
						'success' => true,
						'data'    => $cached,
					),
					200
				);
			}
		}

		$result = Usage_Client::get_instance()->get_summary( $start, $end );

		if ( is_wp_error( $result ) ) {
			$data   = $result->get_error_data();
			$status = is_array( $data ) && isset( $data['status'] ) ? (int) $data['status'] : 500;

			return new \WP_REST_Response(
				array(
					'success' => false,
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				),
				$status
			);
		}

		// Label the window we asked for; the service echoes its own boundaries
		// in its local timezone, which reads a day out.
		$result['range_start'] = $start;
		$result['range_end']   = $until;

		set_transient( $cache_key, $result, 5 * MINUTE_IN_SECONDS );

		$result['cached'] = false;

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
			),
			200
		);
	}

	/**
	 * Handle a symptom triage request.
	 *
	 * Public by design — visitors are not logged in — so it is throttled per IP
	 * and the payload is bounded before anything reaches the upstream service.
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_triage( \WP_REST_Request $request ): \WP_REST_Response {
		$session_id  = (string) $request->get_param( 'session_id' );
		$description = trim( (string) $request->get_param( 'description' ) );

		// A client generated UUID; anything else is a malformed client.
		if ( ! preg_match( '/^[a-f0-9-]{8,64}$/i', $session_id ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Invalid session.', 'directorist-smart-assistant' ),
				),
				400
			);
		}

		if ( mb_strlen( $description ) < 4 ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Please describe the problem in a little more detail.', 'directorist-smart-assistant' ),
				),
				400
			);
		}

		// The description carries the transcript plus the specialty vocabulary the
		// model must choose from (~1,800 characters on its own), so the cap has
		// to clear both — truncation trims the end, which is where that list sits.
		$max_length = (int) apply_filters( 'directorist_smart_assistant_triage_max_length', 6000 );

		if ( mb_strlen( $description ) > $max_length ) {
			$description = mb_substr( $description, 0, $max_length );
		}

		if ( ! $this->allow_triage_request() ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Too many requests. Please wait a moment and try again.', 'directorist-smart-assistant' ),
				),
				429
			);
		}

		$result = Triage_Client::get_instance()->triage( $session_id, $description );

		if ( is_wp_error( $result ) ) {
			$status = $result->get_error_data();
			$status = is_array( $status ) && isset( $status['status'] ) ? (int) $status['status'] : 500;

			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $result->get_error_message(),
				),
				$status
			);
		}

		return new \WP_REST_Response(
			array(
				'success' => true,
				'data'    => $result,
			),
			200
		);
	}

	/**
	 * Per-IP throttle for the public triage endpoint.
	 *
	 * @return bool True when the request may proceed.
	 */
	private function allow_triage_request(): bool {
		if ( current_user_can( 'manage_options' ) ) {
			return true;
		}

		$limit = (int) apply_filters( 'directorist_smart_assistant_triage_rate_limit', 20 );

		if ( $limit <= 0 ) {
			return true;
		}

		$ip  = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		$key = 'dsa_triage_rl_' . md5( $ip . '|' . wp_salt() );

		$hits = (int) get_transient( $key );

		if ( $hits >= $limit ) {
			return false;
		}

		set_transient( $key, $hits + 1, HOUR_IN_SECONDS );

		return true;
	}

	/**
	 * Check permission
	 *
	 * @return bool
	 */
	public function check_permission(): bool {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get settings
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_settings( \WP_REST_Request $request ): \WP_REST_Response {
		$settings = Settings_Manager::get_instance()->get_settings();

		// Don't expose API keys in response, only return masked versions
		if ( ! empty( $settings['api_key'] ) ) {
			$settings['api_key'] = 'sk-***';
		}
		if ( ! empty( $settings['vector_api_secret_key'] ) ) {
			$settings['vector_api_secret_key'] = '***';
		}

		return new \WP_REST_Response( $settings, 200 );
	}

	/**
	 * Save settings
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

		// Vector storage settings
		if ( isset( $params['vector_api_base_url'] ) ) {
			$settings['vector_api_base_url'] = esc_url_raw( $params['vector_api_base_url'] );
		}
		if ( isset( $params['vector_api_secret_key'] ) ) {
			$settings['vector_api_secret_key'] = sanitize_text_field( $params['vector_api_secret_key'] );
		}
		if ( isset( $params['vector_website_id'] ) ) {
			$settings['vector_website_id'] = sanitize_text_field( $params['vector_website_id'] );
		}
		if ( isset( $params['vector_auto_sync'] ) ) {
			$settings['vector_auto_sync'] = (bool) $params['vector_auto_sync'];
		}
		if ( isset( $params['vector_listing_chunk_size'] ) ) {
			$settings['vector_listing_chunk_size'] = intval( $params['vector_listing_chunk_size'] );
		}
		if ( isset( $params['vector_sync_directory_types'] ) ) {
			$settings['vector_sync_directory_types'] = array_map( 'intval', $params['vector_sync_directory_types'] );
		}
		if ( isset( $params['vector_sync_listing_statuses'] ) ) {
			$settings['vector_sync_listing_statuses'] = array_map( 'sanitize_text_field', $params['vector_sync_listing_statuses'] );
		}
		if ( isset( $params['vector_chunk_size'] ) ) {
			$settings['vector_chunk_size'] = intval( $params['vector_chunk_size'] );
		}
		if ( isset( $params['vector_chunk_overlap'] ) ) {
			$settings['vector_chunk_overlap'] = intval( $params['vector_chunk_overlap'] );
		}
		if ( isset( $params['vector_embedding_model'] ) ) {
			$settings['vector_embedding_model'] = sanitize_text_field( $params['vector_embedding_model'] );
		}
		if ( isset( $params['vector_index_name'] ) ) {
			$settings['vector_index_name'] = sanitize_text_field( $params['vector_index_name'] );
		}
		if ( isset( $params['vector_namespace'] ) ) {
			$settings['vector_namespace'] = sanitize_text_field( $params['vector_namespace'] );
		}

		// Chat module settings
		if ( isset( $params['chat_agent_name'] ) ) {
			$settings['chat_agent_name'] = sanitize_text_field( $params['chat_agent_name'] );
		}
		if ( isset( $params['chat_widget_position'] ) ) {
			$settings['chat_widget_position'] = sanitize_text_field( $params['chat_widget_position'] );
		}
		if ( isset( $params['chat_widget_color'] ) ) {
			$settings['chat_widget_color'] = sanitize_text_field( $params['chat_widget_color'] );
		}
		if ( isset( $params['chat_widget_icon'] ) ) {
			// Settings_Manager checks this against the known icon keys.
			$settings['chat_widget_icon'] = sanitize_key( $params['chat_widget_icon'] );
		}
		if ( isset( $params['chat_widget_icon_url'] ) ) {
			$settings['chat_widget_icon_url'] = esc_url_raw( $params['chat_widget_icon_url'] );
		}

		// Settings Manager handles API key encryption and preservation logic
		Settings_Manager::get_instance()->save_settings( $settings );

		return new \WP_REST_Response(
			array(
				'success' => true,
				'message' => __( 'Settings saved successfully.', 'directorist-smart-assistant' ),
			),
			200
		);
	}

	/**
	 * Handle chat request
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_chat( \WP_REST_Request $request ): \WP_REST_Response {
		$params      = $request->get_json_params();
		$message     = isset( $params['message'] ) ? sanitize_textarea_field( $params['message'] ) : '';
		$conversation = isset( $params['conversation'] ) ? $params['conversation'] : array();

		if ( empty( $message ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Message is required.', 'directorist-smart-assistant' ),
				),
				400
			);
		}

		$settings_manager = Settings_Manager::get_instance();
		$settings = $settings_manager->get_settings();

		// Check if vector storage API is configured
		$api_base_url = rtrim( $settings['vector_api_base_url'] ?? '', '/' );
		$api_secret_key = $this->get_decrypted_secret_key();
		$website_id = $settings['vector_website_id'] ?? '';

		if ( empty( $api_base_url ) || empty( $api_secret_key ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'Vector storage API credentials are not configured.', 'directorist-smart-assistant' ),
				),
				400
			);
		}

		// Get listings context using vector query
		$listings_context = $this->get_listings_context_from_vector( $message );

		// Build messages array
		$messages = array();

		// Initialize system prompt with default or from settings
		$system_prompt = ! empty( $settings['system_prompt'] ) ? $settings['system_prompt'] : 'You are a helpful assistant for a business directory website. Answer questions about the listings available on this site.';

		// Website name
		$website_name = get_bloginfo( 'name' );
		if ( ! empty( $website_name ) ) {
			$system_prompt = sprintf(
				/* translators: %s: Website name */
				__( 'You are a helpful assistant for the website - %s. ', 'directorist-smart-assistant' ),
				$website_name
			) . $system_prompt . "\n";
		}
		
		// Add agent name to system prompt if set
		$agent_name = ! empty( $settings['chat_agent_name'] ) ? trim( $settings['chat_agent_name'] ) : '';
		if ( ! empty( $agent_name ) ) {
			$system_prompt = sprintf(
				/* translators: %s: Agent name */
				__( 'Your name is %s. ', 'directorist-smart-assistant' ),
				$agent_name
			) . $system_prompt;
		}
		
		$system_prompt .= "\n\nAvailable listings:\n" . $listings_context;

		$messages[] = array(
			'role'    => 'system',
			'content' => $system_prompt,
		);

		// Add conversation history
		foreach ( $conversation as $conv ) {
			if ( isset( $conv['role'] ) && isset( $conv['content'] ) ) {
				$messages[] = array(
					'role'    => sanitize_text_field( $conv['role'] ),
					'content' => sanitize_textarea_field( $conv['content'] ),
				);
			}
		}

		// Add current message
		$messages[] = array(
			'role'    => 'user',
			'content' => $message,
		);

		// Call Vector API chat endpoint
		$response = $this->call_vector_chat_api(
			$api_base_url,
			$api_secret_key,
			$website_id,
			$message, // prompt
			$system_prompt,
			$settings['model'] ?? 'gpt-3.5-turbo',
			$messages,
			$settings['temperature'] ?? 0.7,
			$settings['max_tokens'] ?? 1000
		);

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
				'success' => true,
				'response' => $response['message'] ?? '',
			),
			200
		);
	}

	/**
	 * Get listings
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_listings( \WP_REST_Request $request ): \WP_REST_Response {
		$listings = $this->get_listings_data();

		return new \WP_REST_Response( $listings, 200 );
	}

	/**
	 * Get listings context string from vector query
	 *
	 * @param string $query_text User's message to query vector database.
	 * @return string
	 */
	private function get_listings_context_from_vector( string $query_text ): string {
		$settings = Settings_Manager::get_instance()->get_settings();
		
		// Check if vector storage is configured
		$api_base_url = $settings['vector_api_base_url'] ?? '';
		$api_secret_key = $settings['vector_api_secret_key'] ?? '';

		// If vector storage is not configured, fallback to direct query
		if ( empty( $api_base_url ) || empty( $api_secret_key ) ) {
			return $this->get_listings_context_fallback();
		}

		// Query vector database
		$vector_query = Vector_Query::get_instance();
		$top_k = 3; // Get top 10 relevant listings
		$query_results = $vector_query->query( $query_text, $top_k );

		//file_put_contents( __DIR__ . '/vector-query-results.json', json_encode( $query_results ) );

		if ( is_wp_error( $query_results ) ) {
			// Fallback to direct query if vector query fails
			return $this->get_listings_context_fallback();
		}

		// Get listings from query results
		$listings = $vector_query->get_listings_from_query_results( $query_results );

		//file_put_contents( __DIR__ . '/listings-from-query-results.json', json_encode( $listings ) );

		// Format as context string
		$context = '';
		foreach ( $listings as $listing ) {
			$context .= sprintf(
				"Title: %s\nContent: %s\nURL: %s\n Related Information: %s\n\n",
				$listing['title'],
				wp_strip_all_tags( $listing['content'] ),
				isset( $listing['url'] ) ? $listing['url'] : '',
				isset( $listing['submission_form_fields'] ) ? $listing['submission_form_fields'] : ''
			);
		}

		//file_put_contents( __DIR__ . '/listings-context.json', json_encode( $listings ) );

		return $context;
	}

	/**
	 * Fallback method to get listings context (when vector storage is not available)
	 *
	 * @return string
	 */
	private function get_listings_context_fallback(): string {
		$listings = $this->get_listings_data();
		$context  = '';

		foreach ( $listings as $listing ) {
			$url = '';
			if ( isset( $listing['id'] ) ) {
				$url = get_permalink( $listing['id'] );
			}
			$context .= sprintf(
				"Title: %s\nContent: %s\nURL: %s\n\n",
				$listing['title'],
				wp_strip_all_tags( $listing['content'] ),
				$url
			);
		}

		return $context;
	}

	/**
	 * Get listings data (fallback method)
	 *
	 * @return array
	 */
	private function get_listings_data(): array {
		// Check cache first
		$cache_key = 'directorist_smart_assistant_listings';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$post_type = defined( 'ATBDP_POST_TYPE' ) ? ATBDP_POST_TYPE : 'at_biz_dir';
		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => 'publish',
		);

		$query  = new \WP_Query( $args );
		$posts  = $query->get_posts();
		$result = array();

		foreach ( $posts as $post ) {
			$result[] = array(
				'id'      => $post->ID,
				'title'   => $post->post_title,
				'content' => $post->post_content,
			);
		}

		// Cache for 1 hour
		set_transient( $cache_key, $result, HOUR_IN_SECONDS );

		return $result;
	}

	/**
	 * Check if model requires max_completion_tokens instead of max_tokens
	 *
	 * @param string $model Model name.
	 * @return bool
	 */
	private function model_requires_max_completion_tokens( string $model ): bool {
		// Models that require max_completion_tokens (newer models)
		$newer_models = array( 'gpt-4o', 'o1', 'o1-preview', 'o1-mini', 'gpt-5-mini' );
		
		foreach ( $newer_models as $newer_model ) {
			if ( strpos( strtolower( $model ), strtolower( $newer_model ) ) !== false ) {
				return true;
			}
		}
		
		return false;
	}

	/**
	 * Call Vector API chat endpoint
	 *
	 * @param string $api_base_url Vector API base URL.
	 * @param string $api_secret_key Vector API secret key.
	 * @param string $website_id Website ID.
	 * @param string $prompt User prompt/message.
	 * @param string $system_prompt System prompt.
	 * @param string $model Model name.
	 * @param array  $messages Messages array.
	 * @param float  $temperature Temperature.
	 * @param int    $max_tokens Max tokens.
	 * @return array|\WP_Error
	 */
	private function call_vector_chat_api( string $api_base_url, string $api_secret_key, string $website_id, string $prompt, string $system_prompt, string $model, array $messages, float $temperature, int $max_tokens ) {
		$url = $api_base_url . '/api/v1/vectors/chat';

		$body = array(
			'prompt'       => $prompt,
			'system_prompt' => $system_prompt,
			'model'        => $model,
			'temperature'  => $temperature,
			'max_tokens'   => $max_tokens,
			'messages'     => $messages,
		);

		// Prepare headers
		$headers = array(
			'X-API-Key'    => $api_secret_key,
			'Content-Type' => 'application/json',
		);

		// Add Website ID header if configured
		if ( ! empty( $website_id ) ) {
			$headers['X-Website-ID'] = $website_id;
		}

		$response = wp_remote_post(
			$url,
			array(
				'headers' => $headers,
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);


		if ( is_wp_error( $response ) ) {
			error_log( 'Vector Chat API Error: ' . $response->get_error_message() );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		// Handle 422 validation error
		if ( 422 === $response_code ) {
			$error_message = __( 'Invalid request parameters.', 'directorist-smart-assistant' );
			if ( isset( $response_body['detail'] ) && is_array( $response_body['detail'] ) ) {
				$errors = array();
				foreach ( $response_body['detail'] as $detail ) {
					if ( isset( $detail['msg'] ) ) {
						$errors[] = $detail['msg'];
					}
				}
				if ( ! empty( $errors ) ) {
					$error_message = implode( ', ', $errors );
				}
			}
			error_log( 'Vector Chat API Validation Error: ' . $error_message );
			return new \WP_Error( 'vector_api_validation_error', $error_message );
		}

		// Handle non-200 responses
		if ( 200 !== $response_code ) {
			$error_message = sprintf(
				/* translators: %d: HTTP status code */
				__( 'Vector storage API returned error code %d.', 'directorist-smart-assistant' ),
				$response_code
			);
			error_log( 'Vector Chat API Error: ' . $error_message . ' - ' . wp_remote_retrieve_body( $response ) );
			return new \WP_Error( 'vector_api_error', $error_message );
		}

		// Validate response structure
		if ( ! isset( $response_body['success'] ) || ! $response_body['success'] ) {
			$error_message = isset( $response_body['message'] ) 
				? $response_body['message'] 
				: __( 'Vector API request failed.', 'directorist-smart-assistant' );
			return new \WP_Error( 'vector_api_error', $error_message );
		}

		if ( ! isset( $response_body['message'] ) ) {
			return new \WP_Error( 'vector_api_error', __( 'Invalid response from Vector API.', 'directorist-smart-assistant' ) );
		}

		return $response_body;
	}

	/**
	 * Get decrypted secret key
	 *
	 * @return string
	 */
	private function get_decrypted_secret_key(): string {
		$settings = Settings_Manager::get_instance()->get_settings();
		$secret_key = $settings['vector_api_secret_key'] ?? '';

		if ( empty( $secret_key ) ) {
			return '';
		}

		// Decrypt the secret key (using same method as API key)
		$settings_manager = Settings_Manager::get_instance();
		return $settings_manager->decrypt_api_key( $secret_key );
	}

	/**
	 * Get directory types
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_directory_types( \WP_REST_Request $request ): \WP_REST_Response {
		$type_taxonomy = defined( 'ATBDP_TYPE' ) ? ATBDP_TYPE : 'at_biz_dir_types';
		
		$types = get_terms(
			array(
				'taxonomy'   => $type_taxonomy,
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
	 * Get listing statuses
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function get_listing_statuses( \WP_REST_Request $request ): \WP_REST_Response {
		$statuses = get_post_statuses();
		
		// Add custom statuses that might be used in Directorist
		$statuses['expired'] = __( 'Expired', 'directorist-smart-assistant' );
		$statuses['pending'] = __( 'Pending', 'directorist-smart-assistant' );
		$statuses['draft']   = __( 'Draft', 'directorist-smart-assistant' );
		$statuses['publish'] = __( 'Published', 'directorist-smart-assistant' );
		$statuses['private'] = __( 'Private', 'directorist-smart-assistant' );
		$statuses['future']  = __( 'Scheduled', 'directorist-smart-assistant' );

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
	 * Handle bulk sync request
	 *
	 * @param \WP_REST_Request $request Request object.
	 * @return \WP_REST_Response
	 */
	public function handle_bulk_sync( \WP_REST_Request $request ): \WP_REST_Response {
		$params = $request->get_json_params();
		$post_ids = isset( $params['post_ids'] ) ? array_map( 'intval', $params['post_ids'] ) : array();

		$vector_sync = Vector_Sync::get_instance();
		$results = $vector_sync->batch_upsert_listings( $post_ids );

		if ( $results['total'] === 0 ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => $results['errors'][0] ?? __( 'No listings found to sync.', 'directorist-smart-assistant' ),
					'results' => $results,
				),
				400
			);
		}

		$message = sprintf(
			/* translators: %1$d: Success count, %2$d: Failed count, %3$d: Total count */
			__( 'Synced %1$d out of %3$d listings successfully. %2$d failed.', 'directorist-smart-assistant' ),
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

}

