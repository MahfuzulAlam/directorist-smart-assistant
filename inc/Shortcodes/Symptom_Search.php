<?php
/**
 * [symptom-based-search] shortcode
 *
 * Renders a two-mode symptom intake widget. Both modes — the guided
 * question flow and the free-text description — fill the same patient
 * profile, which is turned into one sentence and sent to the triage
 * service. The verdict is rendered in place.
 *
 * Assets load only on pages that actually contain the shortcode.
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Shortcodes;

use DirectoristSmartAssistant\Triage\Triage_Client;

/**
 * Symptom Search shortcode class
 */
class Symptom_Search {

	/**
	 * Shortcode tag.
	 */
	const TAG = 'symptom-based-search';

	/**
	 * Instance
	 *
	 * @var Symptom_Search
	 */
	private static $instance = null;

	/**
	 * Whether the shortcode has rendered on this request.
	 *
	 * @var bool
	 */
	private $rendered = false;

	/**
	 * Get instance
	 *
	 * @return Symptom_Search
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
		add_shortcode( self::TAG, array( $this, 'render' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	/**
	 * Register (but do not enqueue) the widget assets.
	 *
	 * @return void
	 */
	public function register_assets(): void {
		$asset_path = DIRECTORIST_SMART_ASSISTANT_PLUGIN_DIR . 'assets/build/symptom-search.asset.php';

		if ( ! file_exists( $asset_path ) ) {
			return;
		}

		$asset_file = include $asset_path;
		$version    = $asset_file['version'] ?? DIRECTORIST_SMART_ASSISTANT_VERSION;

		wp_register_script(
			'directorist-smart-assistant-symptom-search',
			DIRECTORIST_SMART_ASSISTANT_PLUGIN_URL . 'assets/build/symptom-search.js',
			$asset_file['dependencies'] ?? array(),
			$version,
			true
		);

		wp_register_style(
			'directorist-smart-assistant-symptom-search',
			DIRECTORIST_SMART_ASSISTANT_PLUGIN_URL . 'assets/build/symptom-search.css',
			array(),
			$version
		);
	}

	/**
	 * Render the shortcode.
	 *
	 * @param array|string $atts Shortcode attributes.
	 * @return string
	 */
	public function render( $atts ): string {
		$atts = shortcode_atts(
			array(
				'title'      => __( 'Symptom based search', 'directorist-smart-assistant' ),
				'subtitle'   => __( 'Tell us what is wrong and we will point you to the right dentist.', 'directorist-smart-assistant' ),
				'mode'       => 'guided',
				'disclaimer' => __( 'This tool offers guidance only and is not a medical diagnosis. In an emergency, contact a doctor immediately.', 'directorist-smart-assistant' ),
			),
			is_array( $atts ) ? $atts : array(),
			self::TAG
		);

		if ( ! wp_script_is( 'directorist-smart-assistant-symptom-search', 'registered' ) ) {
			return '';
		}

		wp_enqueue_script( 'directorist-smart-assistant-symptom-search' );
		wp_enqueue_style( 'directorist-smart-assistant-symptom-search' );

		// One config object per page, even if the shortcode appears twice.
		// Emitted as JSON rather than through wp_localize_script(), which casts
		// top-level values to strings and would turn `configured` into "1"/"".
		if ( ! $this->rendered ) {
			wp_add_inline_script(
				'directorist-smart-assistant-symptom-search',
				'var directoristSmartAssistantSymptom = ' . wp_json_encode( $this->get_config() ) . ';',
				'before'
			);

			$this->rendered = true;
		}

		$instance_atts = array(
			'title'      => $atts['title'],
			'subtitle'   => $atts['subtitle'],
			'disclaimer' => $atts['disclaimer'],
			'mode'       => in_array( $atts['mode'], array( 'guided', 'describe' ), true ) ? $atts['mode'] : 'guided',
		);

		return sprintf(
			'<div class="dsa-symptom-search" data-dsa-symptom-search data-config="%s"></div>',
			esc_attr( wp_json_encode( $instance_atts ) )
		);
	}

	/**
	 * Configuration shared by every instance on the page.
	 *
	 * @return array
	 */
	private function get_config(): array {
		$flow = $this->get_flow();

		return array(
			'restUrl'         => rest_url( 'directorist-smart-assistant/v1/triage' ),
			'nonce'           => wp_create_nonce( 'wp_rest' ),
			'searchUrl'       => $this->get_search_url(),
			'configured'      => Triage_Client::get_instance()->is_configured(),
			'flow'            => $flow,
			'specialties'     => $this->get_specialties(),
			'locale'          => $this->get_flow_locale( $flow ),
			'locationOptions' => $this->get_location_options(),
			'examples'        => $this->get_examples(),
		);
	}

	/**
	 * The conversation graph the guided mode walks.
	 *
	 * Read from disk rather than compiled into the bundle, so the flow can be
	 * edited without a rebuild.
	 *
	 * @return array Decoded flow document, or an empty array when unreadable.
	 */
	private function get_flow(): array {
		/**
		 * Filter the path of the conversation flow document.
		 *
		 * @param string $path Absolute path to a JSON flow file.
		 */
		$path = (string) apply_filters(
			'directorist_smart_assistant_symptom_flow_path',
			DIRECTORIST_SMART_ASSISTANT_PLUGIN_DIR . 'assets/src/symptom-search/conversation/dental.json'
		);

		$flow = array();

		if ( is_readable( $path ) ) {
			$decoded = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			if ( is_array( $decoded ) ) {
				$flow = $decoded;
			}
		}

		/**
		 * Filter the decoded conversation flow.
		 *
		 * @param array $flow Flow document.
		 */
		return (array) apply_filters( 'directorist_smart_assistant_symptom_flow', $flow );
	}

	/**
	 * The specialty taxonomy the model must choose from, and that turns its
	 * answer back into a label.
	 *
	 * @return array
	 */
	private function get_specialties(): array {
		/**
		 * Filter the path of the specialty taxonomy document.
		 *
		 * @param string $path Absolute path to a JSON file.
		 */
		$path = (string) apply_filters(
			'directorist_smart_assistant_specialties_path',
			DIRECTORIST_SMART_ASSISTANT_PLUGIN_DIR . 'assets/src/symptom-search/conversation/speciaties.json'
		);

		$list = array();

		if ( is_readable( $path ) ) {
			$decoded = json_decode( (string) file_get_contents( $path ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents

			if ( is_array( $decoded ) ) {
				$list = $decoded;
			}
		}

		/**
		 * Filter the specialty taxonomy.
		 *
		 * @param array $list Specialty groups.
		 */
		return (array) apply_filters( 'directorist_smart_assistant_specialties', $list );
	}

	/**
	 * Which of the flow's translations to show this visitor.
	 *
	 * @param array $flow Flow document.
	 * @return string Language code present in the document.
	 */
	private function get_flow_locale( array $flow ): string {
		$default   = $flow['config']['locale_default'] ?? 'en';
		$supported = (array) ( $flow['config']['locales_supported'] ?? array( 'en' ) );
		$current   = strtolower( substr( determine_locale(), 0, 2 ) );

		return in_array( $current, $supported, true ) ? $current : $default;
	}

	/**
	 * Example complaints offered under the free-text field.
	 *
	 * @return string[]
	 */
	private function get_examples(): array {
		$examples = array(
			__( 'Sharp pain in my lower right molar when I drink something cold', 'directorist-smart-assistant' ),
			__( 'My gums bleed every time I brush and they feel swollen', 'directorist-smart-assistant' ),
			__( 'I chipped a front tooth yesterday and it feels rough', 'directorist-smart-assistant' ),
		);

		/**
		 * Filter the example complaints.
		 *
		 * @param string[] $examples Example sentences.
		 */
		return (array) apply_filters( 'directorist_smart_assistant_symptom_examples', $examples );
	}

	/**
	 * Location chips, taken from the live Directorist location taxonomy.
	 *
	 * @return string[]
	 */
	private function get_location_options(): array {
		$taxonomy = defined( 'ATBDP_LOCATION' ) ? ATBDP_LOCATION : 'at_biz_dir-location';

		if ( ! taxonomy_exists( $taxonomy ) ) {
			return array();
		}

		$args = array(
			'taxonomy'   => $taxonomy,
			'hide_empty' => true,
			'number'     => 6,
			'orderby'    => 'count',
			'order'      => 'DESC',
		);

		$terms = get_terms( $args );

		// A fresh directory has locations but no published listings in them yet;
		// showing those chips still beats showing none.
		if ( ! is_wp_error( $terms ) && ! $terms ) {
			$args['hide_empty'] = false;
			$args['orderby']    = 'name';
			$args['order']      = 'ASC';

			$terms = get_terms( $args );
		}

		if ( is_wp_error( $terms ) || ! $terms ) {
			return array();
		}

		return array_map(
			static function ( $term ) {
				return $term->name;
			},
			$terms
		);
	}

	/**
	 * Where the "find a dentist" call to action points.
	 *
	 * @return string
	 */
	private function get_search_url(): string {
		if ( class_exists( 'ATBDP_Permalink' ) ) {
			return \ATBDP_Permalink::get_search_result_page_link();
		}

		return home_url( '/' );
	}
}
