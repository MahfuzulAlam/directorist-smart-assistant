<?php
/**
 * Plugin Name: Directorist Smart Assistant
 * Plugin URI: https://wpxplore.com
 * Description: AI-powered chat assistant for Directorist listings using OpenAI
 * Version: 1.0.0
 * Author: wpXplore
 * Author URI: https://wpxplore.com
 * Text Domain: directorist-smart-assistant
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Define plugin constants
define( 'DIRECTORIST_SMART_ASSISTANT_VERSION', '1.0.0' );
define( 'DIRECTORIST_SMART_ASSISTANT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DIRECTORIST_SMART_ASSISTANT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DIRECTORIST_SMART_ASSISTANT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Autoloader
require_once DIRECTORIST_SMART_ASSISTANT_PLUGIN_DIR . 'vendor/autoload.php';

/**
 * Main plugin class
 */
final class Directorist_Smart_Assistant {

	/**
	 * Plugin instance
	 *
	 * @var Directorist_Smart_Assistant
	 */
	private static $instance = null;

	/**
	 * Get plugin instance
	 *
	 * @return Directorist_Smart_Assistant
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
		// Check if Directorist is active
		add_action( 'admin_notices', array( $this, 'check_directorist_dependency' ) );

		// Initialize components
		add_action( 'plugins_loaded', array( $this, 'load_components' ), 10 );
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
						'Directorist Smart Assistant requires Directorist plugin to be installed and activated.',
						'directorist-smart-assistant'
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
		// Only proceed if Directorist is active
		if ( ! class_exists( 'Directorist_Base' ) ) {
			return;
		}

		// Load REST API
		DirectoristSmartAssistant\REST_API\REST_Controller::get_instance();

		// Load Admin
		DirectoristSmartAssistant\Admin\Admin_Menu::get_instance();

		// Load Frontend
		DirectoristSmartAssistant\Frontend\Enqueuer::get_instance();

		// Load Vector Sync
		DirectoristSmartAssistant\Vector\Vector_Sync::get_instance();
	}
}

/**
 * Initialize plugin
 *
 * @return Directorist_Smart_Assistant
 */
function directorist_smart_assistant() {
	return Directorist_Smart_Assistant::get_instance();
}

// Start the plugin
directorist_smart_assistant();

