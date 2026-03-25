<?php
/**
 * Frontend Enqueuer
 *
 * @package DirectoristAIAgents
 */

namespace DirectoristAIAgents\Frontend;

use DirectoristAIAgents\Settings\Settings_Manager;

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
	 * Whether the chat widget should be loaded on the current page.
	 *
	 * @return bool
	 */
	private function should_load_widget(): bool {
		$settings       = Settings_Manager::get_instance()->get_settings();
		$api_base_url   = $settings['vector_api_base_url'] ?? '';
		$api_secret_key = $settings['vector_api_secret_key'] ?? '';

		// Don't load if vector API is not configured.
		if ( empty( $api_base_url ) || empty( $api_secret_key ) ) {
			return false;
		}

		/** Filter whether the chat widget should be loaded on the current page. */
		return (bool) apply_filters( 'daia_chat_widget_enabled', true );
	}

	/**
	 * Enqueue frontend scripts and styles
	 *
	 * @return void
	 */
	public function enqueue(): void {
		if ( ! $this->should_load_widget() ) {
			return;
		}

		$asset_path = DIRECTORIST_AI_AGENTS_PLUGIN_DIR . 'assets/build/chat-widget.asset.php';

		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset_file = include $asset_path;

		wp_enqueue_script(
			'directorist-ai-agents-chat-widget',
			DIRECTORIST_AI_AGENTS_PLUGIN_URL . 'assets/build/chat-widget.js',
			$asset_file['dependencies'] ?? array(),
			$asset_file['version'] ?? DIRECTORIST_AI_AGENTS_VERSION,
			true
		);

		wp_enqueue_style(
			'directorist-ai-agents-chat-widget',
			DIRECTORIST_AI_AGENTS_PLUGIN_URL . 'assets/build/chat-widget.css',
			array(),
			$asset_file['version'] ?? DIRECTORIST_AI_AGENTS_VERSION
		);

		$settings = Settings_Manager::get_instance()->get_settings();

		wp_localize_script(
			'directorist-ai-agents-chat-widget',
			'directoristAIAgentsChat',
			array(
				'apiUrl'   => rest_url( 'directorist-ai-agents/v1/' ),
				// Localized settings consumed by the chat widget React app.
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'settings' => array(
					'position'  => $settings['chat_widget_position'] ?? 'bottom-right',
					'color'     => $settings['chat_widget_color'] ?? '#667eea',
					'agentName' => $settings['chat_agent_name'] ?? '',
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
		if ( ! $this->should_load_widget() ) {
			return;
		}
		?>
		<div id="directorist-ai-agents-chat-root"></div>
		<?php
	}
}
