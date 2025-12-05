<?php
/**
 * Abstract Embedding Service
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Embedding\Services;

/**
 * Abstract Embedding Service
 */
abstract class Abstract_Embedding_Service implements Embedding_Service_Interface {

	/**
	 * Service settings
	 *
	 * @var array
	 */
	protected $settings = array();

	/**
	 * Initialize the service with settings
	 *
	 * @param array $settings Service settings.
	 * @return bool|WP_Error
	 */
	public function initialize( array $settings ) {
		$this->settings = $settings;
		return $this->validate_settings();
	}

	/**
	 * Validate required settings
	 *
	 * @return bool|WP_Error
	 */
	protected function validate_settings() {
		$required = $this->get_required_settings();
		
		foreach ( $required as $field ) {
			if ( empty( $this->settings[ $field ] ) ) {
				return new \WP_Error(
					'missing_setting',
					sprintf(
						/* translators: %s: Setting field name */
						__( 'Required setting "%s" is missing.', 'directorist-smart-assistant' ),
						$field
					)
				);
			}
		}
		
		return true;
	}

	/**
	 * Get setting value
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	protected function get_setting( string $key, $default = '' ) {
		return $this->settings[ $key ] ?? $default;
	}

	/**
	 * Make HTTP request
	 *
	 * @param string $url Request URL.
	 * @param string $method HTTP method.
	 * @param array  $headers Request headers.
	 * @param array  $body Request body.
	 * @return array|WP_Error
	 */
	protected function make_request( string $url, string $method = 'POST', array $headers = array(), array $body = array() ) {
		$args = array(
			'method'  => $method,
			'headers' => $headers,
			'timeout' => 30,
		);

		if ( ! empty( $body ) ) {
			$args['body'] = wp_json_encode( $body );
		}

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code && 201 !== $response_code ) {
			$error_data = json_decode( $response_body, true );
			$error_message = isset( $error_data['error']['message'] ) 
				? $error_data['error']['message'] 
				: sprintf(
					/* translators: %d: HTTP status code */
					__( 'API request failed with status code %d.', 'directorist-smart-assistant' ),
					$response_code
				);
			
			return new \WP_Error( 'api_error', $error_message );
		}

		return json_decode( $response_body, true );
	}
}

