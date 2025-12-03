<?php
/**
 * Admin Menu
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Admin;

use DirectoristSmartAssistant\Admin\Enqueuer as Admin_Enqueuer;

/**
 * Admin Menu class
 */
class Admin_Menu {

	/**
	 * Instance
	 *
	 * @var Admin_Menu
	 */
	private static $instance = null;

	/**
	 * Page hook
	 *
	 * @var string
	 */
	private $page_hook = '';

	/**
	 * Get instance
	 *
	 * @return Admin_Menu
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
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
	}

	/**
	 * Add admin menu
	 *
	 * @return void
	 */
	public function add_admin_menu(): void {
		// Add submenu under Directorist
		$this->page_hook = add_submenu_page(
			'edit.php?post_type=at_biz_dir',
			__( 'Smart Assistant', 'directorist-smart-assistant' ),
			__( 'Smart Assistant', 'directorist-smart-assistant' ),
			'manage_options',
			'directorist-smart-assistant',
			array( $this, 'render_admin_page' )
		);

		// Enqueue scripts only on this page
		if ( $this->page_hook ) {
			add_action( "load-{$this->page_hook}", array( $this, 'enqueue_admin_assets' ) );
		}
	}

	/**
	 * Render admin page
	 *
	 * @return void
	 */
	public function render_admin_page(): void {
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_admin_page_title() ); ?></h1>
			<div id="directorist-smart-assistant-admin-root"></div>
		</div>
		<?php
	}

	/**
	 * Enqueue admin assets
	 *
	 * @return void
	 */
	public function enqueue_admin_assets(): void {
		Admin_Enqueuer::get_instance()->enqueue();
	}
}

