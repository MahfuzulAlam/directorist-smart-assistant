<?php
/**
 * Settings Manager
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Settings;

/**
 * Settings Manager class
 */
class Settings_Manager {

	/**
	 * Instance
	 *
	 * @var Settings_Manager
	 */
	private static $instance = null;

	/**
	 * Option name
	 *
	 * @var string
	 */
	private $option_name = 'directorist_smart_assistant_settings';

	/**
	 * Get instance
	 *
	 * @return Settings_Manager
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
	 * Get settings
	 *
	 * @return array
	 */
	public function get_settings(): array {
		$defaults = array(
			'api_key'       => '',
			'model'         => 'gpt-3.5-turbo',
			'system_prompt' => 'You are a helpful assistant for a business directory website. Answer questions about the listings available on this site.',
			'temperature'   => 0.7,
			'max_tokens'    => 1000,
			// Vector storage defaults
			'vector_api_base_url'   => '',
			'vector_api_secret_key'  => '',
			'vector_auto_sync'       => false,
			'vector_chunk_size'      => 500,
			'vector_chunk_overlap'   => 50,
			'vector_embedding_model' => 'text-embedding-ada-002',
			'vector_index_name'     => 'directorist-listings',
			'vector_namespace'       => '',
			// Chat module settings
			'chat_agent_name'        => '',
			'chat_widget_position'   => 'bottom-right',
			'chat_widget_color'      => '#667eea',
		);

		$settings = get_option( $this->option_name, array() );

		return wp_parse_args( $settings, $defaults );
	}

	/**
	 * Save settings
	 *
	 * @param array $settings Settings array.
	 * @return bool
	 */
	public function save_settings( array $settings ): bool {
		$current_settings = $this->get_settings();

		// Handle API key encryption
		if ( isset( $settings['api_key'] ) ) {
			// If API key is empty or masked, preserve existing encrypted key
			if ( empty( $settings['api_key'] ) || strpos( $settings['api_key'], '***' ) !== false ) {
				$settings['api_key'] = $current_settings['api_key'] ?? '';
			}
			// Only encrypt if it's a new unencrypted key (starts with "sk-")
			elseif ( ! empty( $settings['api_key'] ) && strpos( $settings['api_key'], 'sk-' ) === 0 ) {
				$settings['api_key'] = $this->encrypt_api_key( $settings['api_key'] );
			}
			// Otherwise, it's already encrypted, keep it as-is
		} else {
			// If API key is not provided, preserve existing
			$settings['api_key'] = $current_settings['api_key'] ?? '';
		}

		// Handle vector API secret key encryption
		if ( isset( $settings['vector_api_secret_key'] ) ) {
			// If secret key is empty or masked, preserve existing encrypted key
			if ( empty( $settings['vector_api_secret_key'] ) || strpos( $settings['vector_api_secret_key'], '***' ) !== false ) {
				$settings['vector_api_secret_key'] = $current_settings['vector_api_secret_key'] ?? '';
			}
			// Encrypt the secret key if it's new (not already encrypted)
			elseif ( ! empty( $settings['vector_api_secret_key'] ) ) {
				$settings['vector_api_secret_key'] = $this->encrypt_api_key( $settings['vector_api_secret_key'] );
			}
		} else {
			// If secret key is not provided, preserve existing
			$settings['vector_api_secret_key'] = $current_settings['vector_api_secret_key'] ?? '';
		}

		// Merge with existing settings to preserve all fields
		$settings = wp_parse_args( $settings, $current_settings );

		return update_option( $this->option_name, $settings );
	}

	/**
	 * Get setting value
	 *
	 * @param string $key Setting key.
	 * @param mixed  $default Default value.
	 * @return mixed
	 */
	public function get_setting( string $key, $default = '' ) {
		$settings = $this->get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : $default;
	}

	/**
	 * Encrypt API key
	 *
	 * @param string $api_key API key.
	 * @return string
	 */
	private function encrypt_api_key( string $api_key ): string {
		// Simple encryption using WordPress salts
		if ( ! function_exists( 'openssl_encrypt' ) ) {
			// Fallback to base64 if OpenSSL is not available
			return base64_encode( $api_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
		}

		$key = $this->get_encryption_key();
		$iv  = openssl_random_pseudo_bytes( openssl_cipher_iv_length( 'AES-256-CBC' ) );
		$encrypted = openssl_encrypt( $api_key, 'AES-256-CBC', $key, 0, $iv );

		return base64_encode( $iv . $encrypted ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
	}

	/**
	 * Decrypt API key
	 *
	 * @param string $encrypted_api_key Encrypted API key.
	 * @return string
	 */
	public function decrypt_api_key( string $encrypted_api_key ): string {
		if ( empty( $encrypted_api_key ) ) {
			return '';
		}

		// Check if it's already decrypted (legacy or fallback)
		if ( strpos( $encrypted_api_key, 'sk-' ) === 0 ) {
			return $encrypted_api_key;
		}

		if ( ! function_exists( 'openssl_decrypt' ) ) {
			// Fallback to base64 decode
			return base64_decode( $encrypted_api_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode
		}

		$key = $this->get_encryption_key();
		$data = base64_decode( $encrypted_api_key ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode

		$iv_length = openssl_cipher_iv_length( 'AES-256-CBC' );
		$iv = substr( $data, 0, $iv_length );
		$encrypted = substr( $data, $iv_length );

		return openssl_decrypt( $encrypted, 'AES-256-CBC', $key, 0, $iv );
	}

	/**
	 * Get encryption key
	 *
	 * @return string
	 */
	private function get_encryption_key(): string {
		$salt = defined( 'AUTH_SALT' ) ? AUTH_SALT : 'directorist-smart-assistant-salt';
		return hash( 'sha256', $salt, true );
	}

	/**
	 * Get decrypted API key for use
	 *
	 * @return string
	 */
	public function get_api_key(): string {
		$settings = $this->get_settings();
		$api_key  = $settings['api_key'] ?? '';

		if ( empty( $api_key ) ) {
			return '';
		}

		return $this->decrypt_api_key( $api_key );
	}
}

