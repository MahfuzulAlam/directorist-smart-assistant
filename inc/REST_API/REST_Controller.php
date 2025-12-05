<?php
/**
 * REST API Controller
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\REST_API;

use DirectoristSmartAssistant\Settings\Settings_Manager;
use DirectoristSmartAssistant\Vector\Vector_Query;

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
						'vector_auto_sync' => array(
							'type'              => 'boolean',
							'required'          => false,
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
		if ( ! empty( $settings['embedding_openai_api_key'] ) ) {
			$settings['embedding_openai_api_key'] = 'sk-***';
		}
		if ( ! empty( $settings['pinecone_api_key'] ) ) {
			$settings['pinecone_api_key'] = '***';
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
		if ( isset( $params['vector_service'] ) ) {
			$settings['vector_service'] = sanitize_text_field( $params['vector_service'] );
		}
		if ( isset( $params['vector_api_base_url'] ) ) {
			$settings['vector_api_base_url'] = esc_url_raw( $params['vector_api_base_url'] );
		}
		if ( isset( $params['vector_api_secret_key'] ) ) {
			$settings['vector_api_secret_key'] = sanitize_text_field( $params['vector_api_secret_key'] );
		}
		if ( isset( $params['vector_auto_sync'] ) ) {
			$settings['vector_auto_sync'] = (bool) $params['vector_auto_sync'];
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
		// Pinecone specific settings
		if ( isset( $params['pinecone_api_key'] ) ) {
			$settings['pinecone_api_key'] = sanitize_text_field( $params['pinecone_api_key'] );
		}
		if ( isset( $params['pinecone_environment'] ) ) {
			$settings['pinecone_environment'] = sanitize_text_field( $params['pinecone_environment'] );
		}
		if ( isset( $params['pinecone_index_name'] ) ) {
			$settings['pinecone_index_name'] = sanitize_text_field( $params['pinecone_index_name'] );
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
		// Embedding settings
		if ( isset( $params['embedding_service'] ) ) {
			$settings['embedding_service'] = sanitize_text_field( $params['embedding_service'] );
		}
		if ( isset( $params['embedding_openai_api_key'] ) ) {
			$settings['embedding_openai_api_key'] = sanitize_text_field( $params['embedding_openai_api_key'] );
		}
		if ( isset( $params['embedding_openai_model'] ) ) {
			$settings['embedding_openai_model'] = sanitize_text_field( $params['embedding_openai_model'] );
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
		$api_key = $settings_manager->get_api_key();

		if ( empty( $api_key ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'message' => __( 'OpenAI API key is not configured.', 'directorist-smart-assistant' ),
				),
				400
			);
		}

		$settings = $settings_manager->get_settings();

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

		$system_prompt .= "\n\nCRITICAL INSTRUCTIONS - READ CAREFULLY:\n"
			. "1. The content below is the SOURCE OF TRUTH - always prioritize it over any previous conversation history\n"
			. "2. Search through ALL content below case-insensitively (ignore capitalization differences like \"PaikarClud\" vs \"paikarclub\")\n"
			. "3. If the user asks about something that appears in ANY form (different capitalization, partial match, similar spelling, or variations) in the content below, you MUST provide an answer based on that content\n"
			. "4. Look for keywords, phrases, and related terms - be flexible and intelligent with matching\n"
			. "5. If you previously said information was not available but it actually exists in the content below, CORRECT YOURSELF and provide the correct answer\n"
			. "6. Only say information is not available if you have thoroughly searched ALL posts and the information is genuinely not present\n"
			. "7. Always use HTML/Markdown format for the response.\n"
			. "8. When you find the information, cite the post title it came from\n";

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

		//file_put_contents( __DIR__ . '/chat.json', json_encode( $messages ) );

		// Call OpenAI API
		$response = $this->call_openai_api(
			$api_key,
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
				'response' => $response,
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

		if ( is_wp_error( $query_results ) ) {
			// Fallback to direct query if vector query fails
			return $this->get_listings_context_fallback();
		}

		// Get listings from query results
		$listings = $vector_query->get_listings_from_query_results( $query_results );

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
			$context .= sprintf(
				"Title: %s\nContent: %s\n\n",
				$listing['title'],
				wp_strip_all_tags( $listing['content'] )
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
	 * Call OpenAI API
	 *
	 * @param string $api_key API key.
	 * @param string $model Model name.
	 * @param array  $messages Messages array.
	 * @param float  $temperature Temperature.
	 * @param int    $max_tokens Max tokens.
	 * @return string|\WP_Error
	 */
	private function call_openai_api( string $api_key, string $model, array $messages, float $temperature, int $max_tokens ) {
		$url = 'https://api.openai.com/v1/chat/completions';

		$body = array(
			'model'       => $model,
			'messages'    => $messages,
			'temperature' => $temperature,
		);

		// Use max_completion_tokens for newer models, max_tokens for older models
		if ( $this->model_requires_max_completion_tokens( $model ) ) {
			$body['max_completion_tokens'] = $max_tokens;
		} else {
			$body['max_tokens'] = $max_tokens;
		}

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $api_key,
					'Content-Type'  => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $response_code ) {
			$error_message = isset( $response_body['error']['message'] ) 
				? $response_body['error']['message'] 
				: __( 'OpenAI API request failed.', 'directorist-smart-assistant' );
			return new \WP_Error( 'openai_error', $error_message );
		}

		if ( ! isset( $response_body['choices'][0]['message']['content'] ) ) {
			return new \WP_Error( 'openai_error', __( 'Invalid response from OpenAI API.', 'directorist-smart-assistant' ) );
		}

		return $response_body['choices'][0]['message']['content'];
	}

}

