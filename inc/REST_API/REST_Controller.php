<?php
/**
 * REST API Controller
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\REST_API;

use DirectoristSmartAssistant\Settings\Settings_Manager;

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

		// Don't expose API key in response, only return masked version
		if ( ! empty( $settings['api_key'] ) ) {
			$settings['api_key'] = 'sk-***';
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

		// Get listings context
		$listings_context = $this->get_listings_context();

		// Build messages array
		$messages = array();

		// System message with listings context
		$system_prompt = ! empty( $settings['system_prompt'] ) ? $settings['system_prompt'] : 'You are a helpful assistant for a business directory website.';
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
	 * Get listings context string
	 *
	 * @return string
	 */
	private function get_listings_context(): string {
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
	 * Get listings data
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

		$args = array(
			'post_type'      => 'at_biz_dir',
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
			'max_tokens'  => $max_tokens,
		);

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

