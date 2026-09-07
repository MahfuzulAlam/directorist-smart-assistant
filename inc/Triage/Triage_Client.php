<?php
/**
 * Triage API client
 *
 * Talks to the symptom triage service that backs the [symptom-based-search]
 * shortcode. Credentials come from the plugin settings, but the
 * SMART_ASSISTANT_* constants win when a site defines them in wp-config.php.
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Triage;

use DirectoristSmartAssistant\Settings\Settings_Manager;

/**
 * Triage Client class
 */
class Triage_Client {

	/**
	 * Instance
	 *
	 * @var Triage_Client
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Triage_Client
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
		// Constructor.
	}

	/**
	 * Whether the service is configured well enough to be called.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return '' !== $this->get_endpoint() && '' !== $this->get_api_key();
	}

	/**
	 * Send a complaint to the triage service.
	 *
	 * @param string $session_id  Client session UUID, groups follow-up requests.
	 * @param string $description What the patient described, in their own words.
	 * @return array|\WP_Error Triage payload, or an error.
	 */
	public function triage( string $session_id, string $description ) {
		$endpoint = $this->get_endpoint();
		$api_key  = $this->get_api_key();

		if ( '' === $endpoint || '' === $api_key ) {
			return new \WP_Error(
				'triage_not_configured',
				__( 'The symptom checker is not configured yet.', 'directorist-smart-assistant' ),
				array( 'status' => 503 )
			);
		}

		$headers = array(
			'Content-Type' => 'application/json',
			'X-API-Key'    => $api_key,
		);

		$website_id = $this->get_website_id();

		if ( '' !== $website_id ) {
			$headers['X-Website-ID'] = $website_id;
		}

		$response = wp_remote_post(
			$endpoint,
			array(
				// The service allows OpenAI up to 20s, so leave headroom.
				'timeout' => (int) apply_filters( 'directorist_smart_assistant_triage_timeout', 30 ),
				'headers' => $headers,
				'body'    => wp_json_encode(
					array(
						'session_id'  => $session_id,
						'description' => $description,
					)
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Triage API Error: ' . $response->get_error_message() );

			return new \WP_Error(
				'triage_request_failed',
				__( 'We could not reach the symptom checker. Please try again.', 'directorist-smart-assistant' ),
				array( 'status' => 502 )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$data = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 !== $code || ! is_array( $data ) ) {
			error_log( sprintf( 'Triage API Error: HTTP %d - %s', $code, wp_remote_retrieve_body( $response ) ) );

			$message = is_array( $data ) && ! empty( $data['detail'] )
				? $this->flatten_detail( $data['detail'] )
				: __( 'The symptom checker returned an unexpected response.', 'directorist-smart-assistant' );

			return new \WP_Error(
				'triage_api_error',
				$message,
				array( 'status' => 502 )
			);
		}

		return $this->normalize( $data );
	}

	/**
	 * Coerce the service payload into the exact shape the UI expects, so a
	 * missing or renamed field can never break rendering.
	 *
	 * @param array $data Raw decoded response.
	 * @return array
	 */
	private function normalize( array $data ): array {
		$reasons = array();

		foreach ( (array) ( $data['probable_reasons'] ?? array() ) as $reason ) {
			if ( empty( $reason['condition'] ) ) {
				continue;
			}

			$reasons[] = array(
				'condition'  => (string) $reason['condition'],
				'confidence' => isset( $reason['confidence'] ) ? (float) $reason['confidence'] : null,
				'why'        => isset( $reason['why'] ) ? (string) $reason['why'] : '',
			);
		}

		return array(
			'doctor_category'      => (string) ( $data['doctor_category'] ?? '' ),
			// The id the model picked from the taxonomy; the widget resolves it
			// to a label. Older service builds omit it.
			'recommended_specialty' => (string) ( $data['recommended_specialty'] ?? '' ),
			'specialist_required'  => ! empty( $data['specialist_required'] ),
			'specialist_type'      => isset( $data['specialist_type'] ) ? (string) $data['specialist_type'] : '',
			'min_experience_years' => isset( $data['min_experience_years'] ) ? (int) $data['min_experience_years'] : 0,
			'urgency'              => (string) ( $data['urgency'] ?? '' ),
			'urgency_hours'        => isset( $data['urgency_hours'] ) ? (int) $data['urgency_hours'] : 0,
			'probable_reasons'     => $reasons,
			'red_flags'            => array_values( array_filter( array_map( 'strval', (array) ( $data['red_flags'] ?? array() ) ) ) ),
			'recommended_action'   => (string) ( $data['recommended_action'] ?? '' ),
			'follow_up_questions'  => array_values( array_filter( array_map( 'strval', (array) ( $data['follow_up_questions'] ?? array() ) ) ) ),
			'confidence'           => isset( $data['confidence'] ) ? (float) $data['confidence'] : null,
			'disclaimer'           => (string) ( $data['disclaimer'] ?? '' ),
		);
	}

	/**
	 * Turn a FastAPI style validation detail into one readable sentence.
	 *
	 * @param mixed $detail Detail node from the response body.
	 * @return string
	 */
	private function flatten_detail( $detail ): string {
		if ( is_string( $detail ) ) {
			return $detail;
		}

		$messages = array();

		foreach ( (array) $detail as $item ) {
			if ( is_array( $item ) && ! empty( $item['msg'] ) ) {
				$messages[] = (string) $item['msg'];
			}
		}

		return $messages
			? implode( ', ', $messages )
			: __( 'The symptom checker returned an unexpected response.', 'directorist-smart-assistant' );
	}

	/**
	 * Full URL of the triage endpoint.
	 *
	 * @return string
	 */
	private function get_endpoint(): string {
		$base = defined( 'SMART_ASSISTANT_API_URL' )
			? (string) SMART_ASSISTANT_API_URL
			: (string) ( Settings_Manager::get_instance()->get_settings()['vector_api_base_url'] ?? '' );

		$base = rtrim( trim( $base ), '/' );

		$endpoint = '' === $base ? '' : $base . '/api/triage';

		/**
		 * Filter the triage endpoint URL.
		 *
		 * @param string $endpoint Full endpoint URL, or '' when unconfigured.
		 */
		return (string) apply_filters( 'directorist_smart_assistant_triage_endpoint', $endpoint );
	}

	/**
	 * API key for the triage service.
	 *
	 * @return string
	 */
	private function get_api_key(): string {
		if ( defined( 'SMART_ASSISTANT_API_KEY' ) && SMART_ASSISTANT_API_KEY ) {
			return (string) SMART_ASSISTANT_API_KEY;
		}

		$settings   = Settings_Manager::get_instance()->get_settings();
		$secret_key = $settings['vector_api_secret_key'] ?? '';

		if ( empty( $secret_key ) ) {
			return '';
		}

		return Settings_Manager::get_instance()->decrypt_api_key( $secret_key );
	}

	/**
	 * Website identifier sent alongside the API key.
	 *
	 * @return string
	 */
	private function get_website_id(): string {
		if ( defined( 'SMART_ASSISTANT_WEBSITE_ID' ) && SMART_ASSISTANT_WEBSITE_ID ) {
			return (string) SMART_ASSISTANT_WEBSITE_ID;
		}

		return (string) ( Settings_Manager::get_instance()->get_settings()['vector_website_id'] ?? '' );
	}
}
