<?php
/**
 * Chat Page Shortcode
 *
 * Registers [directorist_chat_agent] and enqueues the React GPT-style chat app.
 *
 * @package DirectoristAIAgents
 */

namespace DirectoristAIAgents\Shortcode;

use DirectoristAIAgents\Settings\Settings_Manager;

/**
 * Chat_Page class
 */
class Chat_Page {

	/**
	 * Instance.
	 *
	 * @var Chat_Page|null
	 */
	private static $instance = null;

	/**
	 * Whether assets have been enqueued already.
	 *
	 * @var bool
	 */
	private $enqueued = false;

	/**
	 * Get instance.
	 *
	 * @return Chat_Page
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// New shortcode.
		add_shortcode( 'directorist_chat_agent', array( $this, 'render' ) );

		// Backward-compatible alias (keeps old pages working).
		add_shortcode( 'directorist_smart_chat', array( $this, 'render' ) );
	}

	/**
	 * Shortcode callback.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts = array() ): string {
		$settings = Settings_Manager::get_instance()->get_settings();

		if ( empty( $settings['page_chat_enabled'] ) ) {
			return '';
		}

		$this->enqueue_assets();

		return '<div id="daia-chat-page-root"></div>';
	}

	/**
	 * Enqueue the chat page React app assets.
	 *
	 * @return void
	 */
	private function enqueue_assets(): void {
		if ( $this->enqueued ) {
			return;
		}

		$asset_path = DIRECTORIST_AI_AGENTS_PLUGIN_DIR . 'assets/build/chat-page.asset.php';

		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset_file = include $asset_path;

		wp_enqueue_script(
			'daia-chat-page',
			DIRECTORIST_AI_AGENTS_PLUGIN_URL . 'assets/build/chat-page.js',
			$asset_file['dependencies'] ?? array(),
			$asset_file['version'] ?? DIRECTORIST_AI_AGENTS_VERSION,
			true
		);

		wp_enqueue_style(
			'daia-chat-page',
			DIRECTORIST_AI_AGENTS_PLUGIN_URL . 'assets/build/chat-page.css',
			array(),
			$asset_file['version'] ?? DIRECTORIST_AI_AGENTS_VERSION
		);

		$settings = Settings_Manager::get_instance()->get_settings();

		wp_localize_script(
			'daia-chat-page',
			'dsaChatPage',
			array(
				'apiUrl'   => rest_url( 'directorist-ai-agents/v1/' ),
				'nonce'    => wp_create_nonce( 'wp_rest' ),
				'settings' => array(
					'title'          => $settings['page_chat_title'] ?? 'AI Agents',
					'welcomeMessage' => $settings['page_chat_welcome_message'] ?? '',
					'placeholder'    => $settings['page_chat_placeholder'] ?? 'Type a message...',
					'primaryColor'   => $settings['page_chat_primary_color'] ?? '#667eea',
					'guestEnabled'   => ! empty( $settings['page_chat_guest_enabled'] ),
					'showSidebar'    => ! empty( $settings['page_chat_show_sidebar'] ),
					'agentName'      => $settings['chat_agent_name'] ?? 'AI Agents',
				),
				'isLoggedIn' => is_user_logged_in(),
			)
		);

		$this->enqueued = true;
	}
}
