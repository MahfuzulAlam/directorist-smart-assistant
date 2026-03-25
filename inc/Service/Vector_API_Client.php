<?php
/**
 * Vector API Client
 *
 * Centralizes all HTTP communication with the vector storage service.
 *
 * @package DirectoristAIAgents
 */

namespace DirectoristAIAgents\Service;

use DirectoristAIAgents\Settings\Settings_Manager;
use DirectoristAIAgents\Helpers\Listing_Helper;

/**
 * Vector API Client class
 */
class Vector_API_Client {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private $api_base_url;

	/**
	 * API secret key.
	 *
	 * @var string
	 */
	private $api_secret_key;

	/**
	 * Website ID.
	 *
	 * @var string
	 */
	private $website_id;

	/**
	 * Constructor.
	 *
	 * @param string $api_base_url  API base URL.
	 * @param string $api_secret_key API secret key (decrypted).
	 * @param string $website_id    Website ID.
	 */
	public function __construct( string $api_base_url, string $api_secret_key, string $website_id = '' ) {
		$this->api_base_url   = rtrim( $api_base_url, '/' );
		$this->api_secret_key = $api_secret_key;
		$this->website_id     = $website_id;
	}

	/**
	 * Create instance from saved settings.
	 *
	 * @return self|null Null if credentials are not configured.
	 */
	public static function from_settings() {
		$settings       = Settings_Manager::get_instance()->get_settings();
		$api_base_url   = $settings['vector_api_base_url'] ?? '';
		$api_secret_key = Listing_Helper::get_decrypted_secret_key();
		$website_id     = $settings['vector_website_id'] ?? '';

		if ( empty( $api_base_url ) || empty( $api_secret_key ) ) {
			return null;
		}

		return new self( $api_base_url, $api_secret_key, $website_id );
	}

	/**
	 * Query the vector database for relevant listings.
	 *
	 * @param string $text   Search query text.
	 * @param int    $top_k  Number of results to return.
	 * @param array  $filter Optional filter conditions.
	 * @return array|\WP_Error
	 */
	public function query( string $text, int $top_k = 5, array $filter = array() ) {
		if ( ! empty( $this->website_id ) ) {
			$filter['website_id'] = $this->website_id;
		}

		$body = array(
			'text'   => $text,
			'top_k'  => $top_k,
			'filter' => $filter,
		);

		$response = $this->request( '/api/v1/vectors/query', $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['results'] ) || ! is_array( $response['results'] ) ) {
			return new \WP_Error(
				'invalid_response',
				__( 'Invalid response from vector storage API.', 'directorist-ai-agents' )
			);
		}

		return $response['results'];
	}

	/**
	 * Send a chat completion request.
	 *
	 * @param string $prompt            User prompt.
	 * @param string $system_prompt     System prompt.
	 * @param string $model             Model name.
	 * @param array  $messages          Full messages array.
	 * @param float  $temperature       Sampling temperature.
	 * @param int    $max_tokens        Maximum tokens.
	 * @param bool   $use_vector_search Whether to use vector search for context.
	 * @return array|\WP_Error
	 */
	public function chat( string $prompt, string $system_prompt, string $model, array $messages, float $temperature, int $max_tokens, bool $use_vector_search = true ) {
		$body = array(
			'prompt'            => $prompt,
			'system_prompt'     => $system_prompt,
			'model'             => $model,
			'temperature'       => $temperature,
			'max_tokens'        => $max_tokens,
			'messages'          => $messages,
			'use_vector_search' => $use_vector_search,
		);

		$response = $this->request( '/api/v1/vectors/chat', $body );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['success'] ) || ! $response['success'] ) {
			$error_message = $response['message'] ?? __( 'Vector API request failed.', 'directorist-ai-agents' );
			return new \WP_Error( 'vector_api_error', $error_message );
		}

		if ( ! isset( $response['message'] ) ) {
			return new \WP_Error(
				'vector_api_error',
				__( 'Invalid response from Vector API.', 'directorist-ai-agents' )
			);
		}

		return $response;
	}

	/**
	 * Analyze message context and determine action.
	 *
	 * @param string $message              User message text.
	 * @param array  $conversation_history Optional conversation history.
	 * @return array|\WP_Error Context decision data.
	 */
	public function decide_context( string $message, array $conversation_history = array() ) {
		$body = array( 'message' => $message );

		if ( ! empty( $conversation_history ) ) {
			$body['conversation_history'] = $conversation_history;
		}

		$response = $this->request( '/api/v1/context-decider/classify', $body, 15 );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		if ( ! isset( $response['action'] ) ) {
			return new \WP_Error(
				'invalid_response',
				__( 'Invalid context decision response.', 'directorist-ai-agents' )
			);
		}

		return $response;
	}

	/**
	 * Upsert a single listing to the vector database.
	 *
	 * @param array $data Listing data (text, metadata, optional post_id).
	 * @return array|\WP_Error
	 */
	public function upsert( array $data ) {
		return $this->request( '/api/v1/vectors/upsert', $data );
	}

	/**
	 * Batch upsert listings to the vector database.
	 *
	 * @param array $contents Array of listing data items.
	 * @return array|\WP_Error
	 */
	public function batch_upsert( array $contents ) {
		return $this->request(
			'/api/v1/vectors/upsert/batch',
			array( 'contents' => $contents ),
			120
		);
	}

	/**
	 * Build request headers.
	 *
	 * @return array
	 */
	private function get_headers(): array {
		$headers = array(
			'X-API-Key'    => $this->api_secret_key,
			'Content-Type' => 'application/json',
		);

		if ( ! empty( $this->website_id ) ) {
			$headers['X-Website-ID'] = $this->website_id;
		}

		return $headers;
	}

	/**
	 * Make an authenticated API request.
	 *
	 * @param string $endpoint API endpoint path.
	 * @param array  $body     Request body.
	 * @param int    $timeout  Request timeout in seconds.
	 * @return array|\WP_Error Decoded response body or WP_Error.
	 */
	private function request( string $endpoint, array $body, int $timeout = 30 ) {
		$url = $this->api_base_url . $endpoint;

		$response = wp_remote_post(
			$url,
			array(
				'headers' => $this->get_headers(),
				'body'    => wp_json_encode( $body ),
				'timeout' => $timeout,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Vector API Error [' . $endpoint . ']: ' . $response->get_error_message() );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 422 === $response_code ) {
			$error_message = __( 'Invalid request parameters.', 'directorist-ai-agents' );
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
			return new \WP_Error( 'vector_api_validation_error', $error_message );
		}

		if ( $response_code < 200 || $response_code >= 300 ) {
			$error_message = sprintf(
				/* translators: %d: HTTP status code */
				__( 'Vector storage API returned error code %d.', 'directorist-ai-agents' ),
				$response_code
			);
			error_log( 'Vector API Error [' . $endpoint . ']: ' . $error_message );
			return new \WP_Error( 'api_error', $error_message );
		}

		return $response_body;
	}
}
