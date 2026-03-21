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

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'DIRECTORIST_SMART_ASSISTANT_VERSION', '1.0.0' );
define( 'DIRECTORIST_SMART_ASSISTANT_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DIRECTORIST_SMART_ASSISTANT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DIRECTORIST_SMART_ASSISTANT_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

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
		add_action( 'admin_notices', array( $this, 'check_directorist_dependency' ) );
		add_action( 'plugins_loaded', array( $this, 'load_components' ), 10 );

		// Ensure DB tables are up-to-date on admin requests.
		add_action( 'admin_init', array( 'DirectoristSmartAssistant\Provider\Schema', 'maybe_upgrade' ) );
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
		if ( ! class_exists( 'Directorist_Base' ) ) {
			return;
		}

		// REST APIs.
		DirectoristSmartAssistant\REST_API\REST_Controller::get_instance();
		DirectoristSmartAssistant\REST_API\Chat_API_Controller::get_instance();

		// Admin.
		DirectoristSmartAssistant\Admin\Admin_Menu::get_instance();

		// Frontend.
		DirectoristSmartAssistant\Frontend\Enqueuer::get_instance();

		// Shortcode.
		DirectoristSmartAssistant\Shortcode\Chat_Page::get_instance();

		// Vector Sync.
		DirectoristSmartAssistant\Vector\Vector_Sync::get_instance();
	}

	/**
	 * Plugin activation callback.
	 *
	 * @return void
	 */
	public static function activate(): void {
		DirectoristSmartAssistant\Provider\Schema::create_tables();

		/** Fires when the plugin is activated. */
		do_action( 'dsa_plugin_activated' );
	}

	/**
	 * Plugin deactivation callback.
	 *
	 * @return void
	 */
	public static function deactivate(): void {
		delete_transient( 'directorist_smart_assistant_listings' );

		/** Fires when the plugin is deactivated. */
		do_action( 'dsa_plugin_deactivated' );
	}
}

register_activation_hook( __FILE__, array( 'Directorist_Smart_Assistant', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Directorist_Smart_Assistant', 'deactivate' ) );

/**
 * Initialize plugin
 *
 * @return Directorist_Smart_Assistant
 */
function directorist_smart_assistant() {
	return Directorist_Smart_Assistant::get_instance();
}

directorist_smart_assistant();
