<?php
/**
 * Admin Enqueuer
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Admin;

/**
 * Admin Enqueuer class
 */
class Enqueuer {

	/**
	 * Instance
	 *
	 * @var Enqueuer
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Enqueuer
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
	 * Enqueue admin scripts and styles
	 *
	 * @return void
	 */
	public function enqueue(): void {
		$asset_path = DIRECTORIST_SMART_ASSISTANT_PLUGIN_DIR . 'assets/build/admin.asset.php';
		
		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset_file = include $asset_path;

		wp_enqueue_script(
			'directorist-smart-assistant-admin',
			DIRECTORIST_SMART_ASSISTANT_PLUGIN_URL . 'assets/build/admin.js',
			$asset_file['dependencies'] ?? array(),
			$asset_file['version'] ?? DIRECTORIST_SMART_ASSISTANT_VERSION,
			true
		);

		wp_enqueue_style(
			'directorist-smart-assistant-admin',
			DIRECTORIST_SMART_ASSISTANT_PLUGIN_URL . 'assets/build/admin.css',
			array(),
			$asset_file['version'] ?? DIRECTORIST_SMART_ASSISTANT_VERSION
		);

		// Localize script
		wp_localize_script(
			'directorist-smart-assistant-admin',
			'directoristSmartAssistantAdmin',
			array(
				'apiUrl'   => rest_url( 'directorist-smart-assistant/v1/' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'settings' => $this->get_settings_for_js(),
			)
		);
	}

	/**
	 * Get settings for JavaScript
	 *
	 * @return array
	 */
	private function get_settings_for_js(): array {
		$settings = \DirectoristSmartAssistant\Settings\Settings_Manager::get_instance()->get_settings();

		if ( ! empty( $settings['api_key'] ) ) {
			$settings['api_key'] = 'sk-***';
		}
		if ( ! empty( $settings['vector_api_secret_key'] ) ) {
			$settings['vector_api_secret_key'] = '***';
		}

		return $settings;
	}
}

