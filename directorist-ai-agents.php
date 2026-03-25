<?php
/**
 * Plugin Name: Directorist - AI Agents
 * Plugin URI: https://wpxplore.com
 * Description: AI-powered chat assistant for Directorist listings using OpenAI
 * Version: 1.0.0
 * Author: wpXplore
 * Author URI: https://wpxplore.com
 * Text Domain: directorist-ai-agents
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package DirectoristAIAgents
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIRECTORIST_AI_AGENTS_VERSION', '1.0.0' );
define( 'DIRECTORIST_AI_AGENTS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DIRECTORIST_AI_AGENTS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DIRECTORIST_AI_AGENTS_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once DIRECTORIST_AI_AGENTS_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Main plugin class
 */
final class Directorist_AI_Agents {

	/**
	 * Plugin instance
	 *
	 * @var Directorist_AI_Agents
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return Directorist_AI_Agents
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
		$this->init();
	}

	/**
	 * Initialize plugin
	 *
	 * @return void
	 */
	private function init(): void {
		add_action( 'admin_notices', array( $this, 'check_directorist_dependency' ) );
		add_action( 'plugins_loaded', array( $this, 'load_components' ), 10 );

		// Ensure DB tables exist and are up-to-date (runs on both frontend and admin).
		// The check is lightweight — only a get_option() call unless an upgrade is needed.
		add_action( 'init', array( 'DirectoristAIAgents\Provider\Schema', 'maybe_upgrade' ) );
	}

	/**
	 * Check if Directorist plugin is active
	 *
	 * @return void
	 */
	public function check_directorist_dependency(): void {
		if ( ! class_exists( 'Directorist_Base' ) ) {
			?>
			<div class="notice notice-error">
				<p>
					<?php
					echo esc_html__(
						'Directorist - AI Agents requires Directorist plugin to be installed and activated.',
						'directorist-ai-agents'
					);
					?>
				</p>
			</div>
			<?php
		}
	}

	/**
	 * Load plugin components
	 *
	 * @return void
	 */
	public function load_components(): void {
		if ( ! class_exists( 'Directorist_Base' ) ) {
			return;
		}

		// REST APIs.
		DirectoristAIAgents\REST_API\REST_Controller::get_instance();
		DirectoristAIAgents\REST_API\Chat_API_Controller::get_instance();

		// Admin.
		DirectoristAIAgents\Admin\Admin_Menu::get_instance();

		// Frontend.
		DirectoristAIAgents\Frontend\Enqueuer::get_instance();

		// Shortcode.
		DirectoristAIAgents\Shortcode\Chat_Page::get_instance();

		// Vector Sync.
		DirectoristAIAgents\Vector\Vector_Sync::get_instance();
	}

	/**
	 * Plugin activation callback.
	 *
	 * @return void
	 */
	public static function activate(): void {
		DirectoristAIAgents\Provider\Schema::create_tables();

		/** Fires when the plugin is activated. */
		do_action( 'daia_plugin_activated' );
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		// Note: transient key intentionally kept for backward compatibility.
		delete_transient( 'directorist_smart_assistant_listings' );

		/** Fires when the plugin is deactivated. */
		do_action( 'daia_plugin_deactivated' );
	}
}

register_activation_hook( __FILE__, array( 'Directorist_AI_Agents', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Directorist_AI_Agents', 'deactivate' ) );

/**
 * Initialize plugin
 *
 * @return Directorist_AI_Agents
 */
function directorist_ai_agents() {
	return Directorist_AI_Agents::get_instance();
}

directorist_ai_agents();

