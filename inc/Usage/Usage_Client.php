<?php
/**
 * Usage API client
 *
 * Reads this website's own API usage and cost from the Smart Assistant
 * service. The endpoint is scoped by the credentials themselves — the site's
 * X-API-Key plus X-Website-ID — so no platform-wide admin key is ever stored
 * in WordPress.
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Usage;

use DirectoristSmartAssistant\Settings\Settings_Manager;

/**
 * Usage Client class
 */
class Usage_Client {

	/**
	 * Instance
	 *
	 * @var Usage_Client
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Usage_Client
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
	 * Whether usage can be fetched with the credentials on file.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return '' !== $this->get_base_url() && '' !== $this->get_api_key();
	}

	/**
	 * Fetch the usage summary for this website.
	 *
	 * @param string $start_date Inclusive start date (Y-m-d), or '' for all time.
	 * @param string $end_date   Inclusive end date (Y-m-d), or '' for all time.
	 * @return array|\WP_Error
	 */
	public function get_summary( string $start_date = '', string $end_date = '' ) {
		$base    = $this->get_base_url();
		$api_key = $this->get_api_key();

		if ( '' === $base || '' === $api_key ) {
			return new \WP_Error(
				'usage_not_configured',
				__( 'Add your API base URL and secret key before usage can be shown.', 'directorist-smart-assistant' ),
				array( 'status' => 503 )
			);
		}

		/**
		 * Filter the usage summary endpoint.
		 *
		 * @param string $url Full endpoint URL.
		 */
		$url = apply_filters( 'directorist_smart_assistant_usage_endpoint', $base . '/api/v1/usage/summary' );

		$query = array_filter(
			array(
				'start_date' => $start_date,
				'end_date'   => $end_date,
			)
		);

		if ( $query ) {
			$url = add_query_arg( $query, $url );
		}

		$headers = array( 'X-API-Key' => $api_key );

		$website_id = $this->get_website_id();

		if ( '' !== $website_id ) {
			$headers['X-Website-ID'] = $website_id;
		}

		$response = wp_remote_get(
			$url,
			array(
				// A sleeping host (Render and friends spin down when idle) needs
				// time for its first request; warm ones answer in well under a second.
				'timeout' => (int) apply_filters( 'directorist_smart_assistant_usage_timeout', 25 ),
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			$message = $response->get_error_message();

			if ( false !== stripos( $message, 'timed out' ) || false !== stripos( $message, 'timeout' ) ) {
				return new \WP_Error(
					'usage_timeout',
					__( 'The usage service did not answer in time. It may be waking up — try again in a moment.', 'directorist-smart-assistant' ),
					array( 'status' => 504 )
				);
			}

			return new \WP_Error(
				'usage_request_failed',
				__( 'Could not reach the usage service.', 'directorist-smart-assistant' ),
				array( 'status' => 502 )
			);
		}

		$code = wp_remote_retrieve_response_code( $response );
		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		$detail = is_array( $body ) && isset( $body['detail'] ) ? $this->flatten_detail( $body['detail'] ) : '';

		if ( 404 === $code ) {
			// A missing route answers with the framework's bare "Not Found"; a
			// handler that 404s has something more specific to say, so say it.
			if ( '' === $detail || 0 === strcasecmp( $detail, 'Not Found' ) ) {
				return new \WP_Error(
					'usage_endpoint_missing',
					__( 'This service does not expose a usage endpoint yet.', 'directorist-smart-assistant' ),
					array( 'status' => 501 )
				);
			}

			return new \WP_Error( 'usage_api_error', $detail, array( 'status' => 502 ) );
		}

		if ( 401 === $code || 403 === $code ) {
			return new \WP_Error(
				'usage_unauthorized',
				__( 'The API key was rejected by the usage service.', 'directorist-smart-assistant' ),
				array( 'status' => 401 )
			);
		}

		if ( 200 !== $code || ! is_array( $body ) ) {
			return new \WP_Error(
				'usage_api_error',
				'' !== $detail
					? $detail
					: __( 'The usage service returned an unexpected response.', 'directorist-smart-assistant' ),
				array( 'status' => 502 )
			);
		}

		return $this->normalize( $body, $start_date, $end_date );
	}

	/**
	 * A short fingerprint of the endpoint and website currently configured.
	 *
	 * Cached usage belongs to one service + website pair; changing either must
	 * not surface the previous one's numbers.
	 *
	 * @return string
	 */
	public function get_cache_scope(): string {
		return substr( md5( $this->get_base_url() . '|' . $this->get_website_id() ), 0, 8 );
	}

	/**
	 * Turn an error detail node into one readable sentence.
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
				$field = isset( $item['loc'] ) && is_array( $item['loc'] ) ? end( $item['loc'] ) : '';

				$messages[] = $field ? $field . ': ' . $item['msg'] : (string) $item['msg'];
			}
		}

		return implode( ', ', $messages );
	}

	/**
	 * Reduce the service payload to one predictable shape.
	 *
	 * Accepts the ApiStatSummary fields (`total_cost_usd`, `cost_by_service`, …)
	 * and tolerates a `data` envelope or the shorter `cost_usd` spelling, so a
	 * small difference on the service side does not blank the screen.
	 *
	 * @param array  $body       Decoded response body.
	 * @param string $start_date Requested start date.
	 * @param string $end_date   Requested end date.
	 * @return array
	 */
	private function normalize( array $body, string $start_date, string $end_date ): array {
		$data = isset( $body['data'] ) && is_array( $body['data'] ) ? $body['data'] : $body;

		$calls  = (array) ( $data['calls_by_service'] ?? array() );
		$tokens = (array) ( $data['tokens_by_service'] ?? array() );
		$cost   = (array) ( $data['cost_by_service'] ?? array() );

		$names = array_unique( array_merge( array_keys( $calls ), array_keys( $tokens ), array_keys( $cost ) ) );

		$services = array();

		foreach ( $names as $name ) {
			$services[] = array(
				'service' => (string) $name,
				'calls'   => (int) ( $calls[ $name ] ?? 0 ),
				'tokens'  => (int) ( $tokens[ $name ] ?? 0 ),
				'cost'    => (float) ( $cost[ $name ] ?? 0 ),
			);
		}

		// Most expensive first — the order the reader cares about.
		usort(
			$services,
			static function ( $a, $b ) {
				return $b['cost'] <=> $a['cost'];
			}
		);

		return array(
			'total_calls'    => (int) ( $data['total_calls'] ?? 0 ),
			'total_tokens'   => (int) ( $data['total_tokens'] ?? 0 ),
			'total_cost_usd' => (float) ( $data['total_cost_usd'] ?? $data['cost_usd'] ?? 0 ),
			'services'       => $services,
			'period_start'   => (string) ( $data['period_start'] ?? $start_date ),
			'period_end'     => (string) ( $data['period_end'] ?? $end_date ),
			'website_id'     => $this->get_website_id(),
			'currency'       => (string) ( $data['currency'] ?? 'USD' ),
		);
	}

	/**
	 * Service base URL, without a trailing slash.
	 *
	 * @return string
	 */
	private function get_base_url(): string {
		$base = defined( 'SMART_ASSISTANT_API_URL' )
			? (string) SMART_ASSISTANT_API_URL
			: (string) ( Settings_Manager::get_instance()->get_settings()['vector_api_base_url'] ?? '' );

		return rtrim( trim( $base ), '/' );
	}

	/**
	 * API key for this website.
	 *
	 * @return string
	 */
	private function get_api_key(): string {
		if ( defined( 'SMART_ASSISTANT_API_KEY' ) && SMART_ASSISTANT_API_KEY ) {
			return (string) SMART_ASSISTANT_API_KEY;
		}

		$secret = Settings_Manager::get_instance()->get_settings()['vector_api_secret_key'] ?? '';

		if ( empty( $secret ) ) {
			return '';
		}

		return Settings_Manager::get_instance()->decrypt_api_key( $secret );
	}

	/**
	 * This website's identifier.
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
