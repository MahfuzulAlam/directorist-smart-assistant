<?php
/**
 * Vector Query Handler
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Vector;

use DirectoristSmartAssistant\Settings\Settings_Manager;

/**
 * Vector Query class
 */
class Vector_Query {

	/**
	 * Instance
	 *
	 * @var Vector_Query
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Vector_Query
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
		// Constructor
	}

	/**
	 * Query vector database
	 *
	 * @param string $query_text Search query text.
	 * @param int    $top_k      Number of results to return.
	 * @param array  $filter     Optional filter array.
	 * @return array|WP_Error
	 */
	public function query( string $query_text, int $top_k = 5, array $filter = array() ) {
		$settings = Settings_Manager::get_instance()->get_settings();
		$api_base_url = rtrim( $settings['vector_api_base_url'] ?? '', '/' );
		$api_secret_key = $this->get_decrypted_secret_key();

		if ( empty( $api_base_url ) || empty( $api_secret_key ) ) {
			return new \WP_Error( 'missing_credentials', __( 'Vector storage API credentials are not configured.', 'directorist-smart-assistant' ) );
		}

		// Prepare request body
		$body = array(
			'text'   => $query_text,
			'top_k'  => $top_k,
			//'filter' => $filter,
		);

		// Make API request
		$url = $api_base_url . '/api/v1/vectors/query';

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'X-API-Key'    => $api_secret_key,
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $body ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Vector Query Error: ' . $response->get_error_message() );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code ) {
			$error_message = sprintf(
				/* translators: %d: HTTP status code */
				__( 'Vector storage API returned error code %d.', 'directorist-smart-assistant' ),
				$response_code
			);
			error_log( 'Vector Query Error: ' . $error_message . ' - ' . $response_body );
			return new \WP_Error( 'api_error', $error_message );
		}

		$data = json_decode( $response_body, true );

		if ( ! isset( $data['results'] ) || ! is_array( $data['results'] ) ) {
			return new \WP_Error( 'invalid_response', __( 'Invalid response from vector storage API.', 'directorist-smart-assistant' ) );
		}

		return $data['results'];
	}

	/**
	 * Get listings data from vector query results
	 *
	 * @param array $query_results Vector query results.
	 * @return array
	 */
	public function get_listings_from_query_results( array $query_results ): array {
		$listings = array();
		$post_type = defined( 'ATBDP_POST_TYPE' ) ? ATBDP_POST_TYPE : 'at_biz_dir';

		foreach ( $query_results as $result ) {
			// Get post_id from result or metadata
			$post_id = 0;
			if ( isset( $result['post_id'] ) ) {
				$post_id = intval( $result['post_id'] );
			} elseif ( isset( $result['metadata']['post_id'] ) ) {
				$post_id = intval( $result['metadata']['post_id'] );
			}

			if ( empty( $post_id ) ) {
				continue;
			}

			$post = get_post( $post_id );

			if ( ! $post ) {
				continue;
			}

			// Only include Directorist listings
			if ( $post->post_type !== $post_type ) {
				continue;
			}

			// Only include published posts
			if ( 'publish' !== $post->post_status ) {
				continue;
			}

			$listings[] = array(
				'id'      => $post->ID,
				'title'   => $post->post_title,
				'content' => $post->post_content,
				'url'     => get_permalink( $post->ID ),
			);
		}

		return $listings;
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
}

