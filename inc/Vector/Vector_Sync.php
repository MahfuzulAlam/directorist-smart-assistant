<?php
/**
 * Vector Sync Handler
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Vector;

use DirectoristSmartAssistant\Settings\Settings_Manager;

/**
 * Vector Sync class
 */
class Vector_Sync {

	/**
	 * Instance
	 *
	 * @var Vector_Sync
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Vector_Sync
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
		add_action( 'save_post', array( $this, 'handle_post_save' ), 10, 3 );
	}

	/**
	 * Handle post save
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @param bool    $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public function handle_post_save( int $post_id, $post, bool $update ): void {
		// Only process at_biz_dir post type
		$post_type = defined( 'ATBDP_POST_TYPE' ) ? ATBDP_POST_TYPE : 'at_biz_dir';
		if ( $post->post_type !== $post_type ) {
			return;
		}

		// Skip autosaves, revisions, and trash
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( 'trash' === $post->post_status ) {
			return;
		}

		// Check if auto-sync is enabled
		$settings = Settings_Manager::get_instance()->get_settings();
		if ( empty( $settings['vector_auto_sync'] ) ) {
			return;
		}

		// Check if API credentials are configured
		$api_base_url = $settings['vector_api_base_url'] ?? '';
		$api_secret_key = $this->get_decrypted_secret_key();

		if ( empty( $api_base_url ) || empty( $api_secret_key ) ) {
			return;
		}

		// Upsert to vector database
		$this->upsert_listing( $post_id, $post );
	}

	/**
	 * Upsert listing to vector database
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 * @return bool|WP_Error
	 */
	public function upsert_listing( int $post_id, $post ) {
		$settings = Settings_Manager::get_instance()->get_settings();
		$api_base_url = rtrim( $settings['vector_api_base_url'] ?? '', '/' );
		$api_secret_key = $this->get_decrypted_secret_key();

		if ( empty( $api_base_url ) || empty( $api_secret_key ) ) {
			return new \WP_Error( 'missing_credentials', __( 'Vector storage API credentials are not configured.', 'directorist-smart-assistant' ) );
		}

		// Prepare data
		$text = $this->prepare_listing_text( $post );
		$metadata = $this->prepare_listing_metadata( $post_id );

		$data = array(
			'post_id'   => $post_id,
			'text'      => $text,
			'metadata'  => $metadata,
		);

        //file_put_contents( __DIR__ . '/vector-sync.json', json_encode( $data ) );

		// Make API request
		$url = $api_base_url . '/api/v1/vectors/upsert';

		$response = wp_remote_post(
			$url,
			array(
				'headers' => array(
					'X-API-Key'   => $api_secret_key,
					'Content-Type' => 'application/json',
				),
				'body'    => wp_json_encode( $data ),
				'timeout' => 30,
			)
		);

		if ( is_wp_error( $response ) ) {
			error_log( 'Vector Sync Error: ' . $response->get_error_message() );
			return $response;
		}

		$response_code = wp_remote_retrieve_response_code( $response );
		$response_body = wp_remote_retrieve_body( $response );

		if ( 200 !== $response_code && 201 !== $response_code ) {
			$error_message = sprintf(
				/* translators: %d: HTTP status code */
				__( 'Vector storage API returned error code %d.', 'directorist-smart-assistant' ),
				$response_code
			);
			error_log( 'Vector Sync Error: ' . $error_message . ' - ' . $response_body );
			return new \WP_Error( 'api_error', $error_message );
		}

        update_post_meta( $post_id, '_vector_sync', 1 );
        update_post_meta( $post_id, '_vector_sync_date', current_time( 'Y-m-d H:i:s' ) );

		return true;
	}

	/**
	 * Prepare listing text (title + content)
	 *
	 * @param WP_Post $post Post object.
	 * @return string
	 */
	private function prepare_listing_text( $post ): string {
		$title = $post->post_title ?? '';
		$content = $post->post_content ?? '';

		// Strip HTML tags and clean up
		$title = wp_strip_all_tags( $title );
		$content = wp_strip_all_tags( $content );

		// Combine title and content
		$text = trim( $title . "\n\n" . $content );

		return $text;
	}

	/**
	 * Prepare listing metadata
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function prepare_listing_metadata( int $post_id ): array {
		$metadata = array();

		// Get listing category - use Directorist constant if available
		$category_taxonomy = 'at_biz_dir-category';
		if ( defined( 'ATBDP_CATEGORY' ) ) {
			$category_taxonomy = ATBDP_CATEGORY;
		}

		$categories = wp_get_post_terms( $post_id, $category_taxonomy, array( 'fields' => 'names' ) );
		if ( is_wp_error( $categories ) || empty( $categories ) ) {
			$metadata['category'] = '';
		} else {
			$metadata['category'] = implode( ', ', $categories );
		}

		// Get listing type - use Directorist constant if available
		$type_taxonomy = 'at_biz_dir_types';
		if ( defined( 'ATBDP_TYPE' ) ) {
			$type_taxonomy = ATBDP_TYPE;
		}

		$types = wp_get_post_terms( $post_id, $type_taxonomy, array( 'fields' => 'names' ) );
		if ( is_wp_error( $types ) || empty( $types ) ) {
			$metadata['type'] = '';
		} else {
			$metadata['type'] = implode( ', ', $types );
		}

		return $metadata;
	}

	/**
	 * Get decrypted secret key
	 *
	 * @return string
	 */
	private function get_decrypted_secret_key(): string {
		$settings = Settings_Manager::get_instance()->get_settings();
		$secret_key = $settings['vector_api_secret_key'] ?? '';

		if ( empty( $secret_key ) ) {
			return '';
		}

		// Decrypt the secret key (using same method as API key)
		$settings_manager = Settings_Manager::get_instance();
		return $settings_manager->decrypt_api_key( $secret_key );
	}
}

