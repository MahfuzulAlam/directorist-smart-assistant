<?php
/**
 * Frontend Enqueuer
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Frontend;

/**
 * Frontend Enqueuer class
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
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_footer', array( $this, 'render_chat_widget' ) );
	}

	/**
	 * Enqueue frontend scripts and styles
	 *
	 * @return void
	 */
	public function enqueue(): void {
		$asset_path = DIRECTORIST_SMART_ASSISTANT_PLUGIN_DIR . 'assets/build/chat-widget.asset.php';
		
		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset_file = include $asset_path;

		wp_enqueue_script(
			'directorist-smart-assistant-chat-widget',
			DIRECTORIST_SMART_ASSISTANT_PLUGIN_URL . 'assets/build/chat-widget.js',
			$asset_file['dependencies'] ?? array(),
			$asset_file['version'] ?? DIRECTORIST_SMART_ASSISTANT_VERSION,
			true
		);

		wp_enqueue_style(
			'directorist-smart-assistant-chat-widget',
			DIRECTORIST_SMART_ASSISTANT_PLUGIN_URL . 'assets/build/chat-widget.css',
			array(),
			$asset_file['version'] ?? DIRECTORIST_SMART_ASSISTANT_VERSION
		);

		// Get chat widget settings
		$settings = \DirectoristSmartAssistant\Settings\Settings_Manager::get_instance()->get_settings();

		// Localize script
		wp_localize_script(
			'directorist-smart-assistant-chat-widget',
			'directoristSmartAssistantChat',
			array(
				'apiUrl' => rest_url( 'directorist-smart-assistant/v1/' ),
				'nonce'  => wp_create_nonce( 'wp_rest' ),
				'settings' => array(
					'position' => $settings['chat_widget_position'] ?? 'bottom-right',
					'color'    => $settings['chat_widget_color'] ?? '#667eea',
				),
			)
		);
	}

	/**
	 * Render chat widget container
	 *
	 * @return void
	 */
	public function render_chat_widget(): void {
		?>
		<div id="directorist-smart-assistant-chat-root"></div>
		<?php
	}
}

