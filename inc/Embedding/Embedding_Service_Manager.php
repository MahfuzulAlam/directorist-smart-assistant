<?php
/**
 * Embedding Service Manager
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Embedding;

use DirectoristSmartAssistant\Embedding\Services\Embedding_Service_Interface;
use DirectoristSmartAssistant\Embedding\Services\OpenAI_Embedding_Service;
use DirectoristSmartAssistant\Settings\Settings_Manager;

/**
 * Embedding Service Manager class
 */
class Embedding_Service_Manager {

	/**
	 * Instance
	 *
	 * @var Embedding_Service_Manager
	 */
	private static $instance = null;

	/**
	 * Available services
	 *
	 * @var array
	 */
	private $available_services = array();

	/**
	 * Current service instance
	 *
	 * @var Embedding_Service_Interface|null
	 */
	private $current_service = null;

	/**
	 * Get instance
	 *
	 * @return Embedding_Service_Manager
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
		$this->register_services();
	}

	/**
	 * Register available services
	 *
	 * @return void
	 */
	private function register_services() {
		$this->available_services = array(
			'openai' => array(
				'class' => OpenAI_Embedding_Service::class,
				'label' => __( 'OpenAI Embedding', 'directorist-smart-assistant' ),
			),
		);

		/**
		 * Filter available embedding services
		 *
		 * @param array $services Available services.
		 */
		$this->available_services = apply_filters( 'directorist_smart_assistant_embedding_services', $this->available_services );
	}

	/**
	 * Get available services
	 *
	 * @return array
	 */
	public function get_available_services(): array {
		return $this->available_services;
	}

	/**
	 * Get service instance
	 *
	 * @param string|null $service_name Service name. If null, uses configured service.
	 * @return Embedding_Service_Interface|WP_Error
	 */
	public function get_service( string $service_name = null ) {
		$settings = Settings_Manager::get_instance()->get_settings();
		
		if ( null === $service_name ) {
			$service_name = $settings['embedding_service'] ?? 'openai';
		}

		// Return cached instance if same service
		if ( $this->current_service && $this->current_service->get_service_name() === $service_name ) {
			return $this->current_service;
		}

		if ( ! isset( $this->available_services[ $service_name ] ) ) {
			return new \WP_Error(
				'invalid_service',
				sprintf(
					/* translators: %s: Service name */
					__( 'Embedding service "%s" is not available.', 'directorist-smart-assistant' ),
					$service_name
				)
			);
		}

		$service_class = $this->available_services[ $service_name ]['class'];
		
		if ( ! class_exists( $service_class ) ) {
			return new \WP_Error(
				'service_class_not_found',
				sprintf(
					/* translators: %s: Service class name */
					__( 'Service class "%s" not found.', 'directorist-smart-assistant' ),
					$service_class
				)
			);
		}

		$service = new $service_class();
		
		// Initialize service with settings
		$service_settings = $this->get_service_settings( $service_name, $settings );
		$init_result = $service->initialize( $service_settings );
		
		if ( is_wp_error( $init_result ) ) {
			return $init_result;
		}

		$this->current_service = $service;
		return $service;
	}

	/**
	 * Get service-specific settings
	 *
	 * @param string $service_name Service name.
	 * @param array  $all_settings All settings.
	 * @return array
	 */
	private function get_service_settings( string $service_name, array $all_settings ): array {
		$service_settings = array();

		switch ( $service_name ) {
			case 'openai':
				$service_settings = array(
					'api_key' => $all_settings['embedding_openai_api_key'] ?? '',
					'model'   => $all_settings['embedding_openai_model'] ?? 'text-embedding-ada-002',
				);
				// Decrypt API key if needed
				if ( ! empty( $service_settings['api_key'] ) ) {
					$settings_manager = Settings_Manager::get_instance();
					$service_settings['api_key'] = $settings_manager->decrypt_api_key( $service_settings['api_key'] );
				}
				break;
		}

		return $service_settings;
	}
}

