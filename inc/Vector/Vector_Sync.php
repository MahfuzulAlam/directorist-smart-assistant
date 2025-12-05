<?php
/**
 * Vector Sync Handler
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Vector;

use DirectoristSmartAssistant\Settings\Settings_Manager;
use DirectoristSmartAssistant\Vector\Vector_Service_Manager;
use DirectoristSmartAssistant\Embedding\Services\Embedding_Service_Manager;

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

		// Check if vector service is configured
		$service_manager = Vector_Service_Manager::get_instance();
		$service = $service_manager->get_service();

        file_put_contents( __DIR__ . '/vector-service-manager.json', json_encode( $service ) );

		if ( is_wp_error( $service ) ) {
			// Service not configured, skip sync
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
		// Validate post object
		if ( ! $post || ! isset( $post->ID ) || empty( $post->ID ) ) {
			return new \WP_Error( 'invalid_post', __( 'Invalid post object provided.', 'directorist-smart-assistant' ) );
		}

		// Get vector service
		$service_manager = Vector_Service_Manager::get_instance();
		$service = $service_manager->get_service();

		if ( is_wp_error( $service ) ) {
			error_log( 'Vector Sync Error: ' . $service->get_error_message() );
			return $service;
		}

		// Prepare data
		$text = $this->prepare_listing_text( $post );
		$metadata = $this->prepare_listing_metadata( $post_id );

		// For WpXplore service, use the existing format
		// For other services, we might need to generate embeddings first
		$service_name = $service->get_service_name();
		
		if ( 'WpXplore' === $service_name ) {
			// WpXplore accepts text directly - metadata should include text
			$result = $service->upsert(
				(string) $post_id,
				array(), // Empty vector, service will handle embedding
				array_merge(
					$metadata,
					array(
						'text'      => $text,
						'post_title' => $post->post_title,
						'post_type' => $post->post_type,
					)
				)
			);
		} else {
			// For other services, we need vector embeddings
			// For now, we'll use a placeholder - in production, you'd generate embeddings here
			$result = $service->upsert(
				(string) $post_id,
				// Generate embeddings using the embedding service
				Embedding_Service_Manager::get_instance()->get_service()->embed( $text ) ?? array(),
				array_merge(
					$metadata,
					array(
						'text'      => $text,
						'post_title' => $post->post_title,
						'post_type' => $post->post_type,
					)
				)
			);
		}

        file_put_contents( __DIR__ . '/vector-sync-result.json', json_encode( $service_name ) );

		if ( is_wp_error( $result ) ) {
			error_log( 'Vector Sync Error: ' . $result->get_error_message() );
			return $result;
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
		// Validate post object
		if ( ! $post || ! isset( $post->ID ) || empty( $post->ID ) ) {
			return '';
		}

		$post_id = intval( $post->ID );
		$title = $post->post_title ?? '';
		$content = $post->post_content ?? '';
        $submission_form_fields = $this->get_submission_form_fields_with_values( $post_id );

		// Strip HTML tags and clean up
		$title = wp_strip_all_tags( $title );
		$content = wp_strip_all_tags( $content );

		// Combine title and content
		$text = trim( $title . "\n\n" . $content . "\n\n" . $submission_form_fields );

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

    /**
     * Get Submission Form Fields Of a Listing as label-value string.
     *
     * @param int $post_id The ID of the post/listing.
     * @return string All submission fields in "Label: Value" format, separated by line breaks.
     */
    private function get_submission_form_fields_with_values( int $post_id ): string {
        $output = '';

        // Get listing types
        $type_taxonomy = defined( 'ATBDP_TYPE' ) ? ATBDP_TYPE : 'at_biz_dir_types';
        $listing_types = wp_get_post_terms( $post_id, $type_taxonomy, array( 'fields' => 'ids' ) );

        if ( is_wp_error( $listing_types ) || empty( $listing_types ) ) {
            return $output;
        }

        // Use the first listing type
        $listing_type_id = $listing_types[0];
        if ( empty( $listing_type_id ) ) {
            return $output;
        }

        $submission_form_fields = get_term_meta( $listing_type_id, 'submission_form_fields', true );
        if ( empty( $submission_form_fields ) ) {
            return $output;
        }

        $fields = $submission_form_fields['fields'] ?? array();
        if ( ! empty( $fields ) && is_array( $fields ) ) {
            foreach ( $fields as $field ) {
                if ( ! is_array( $field ) ) {
                    continue;
                }
                if ( ! empty( $field['field_key'] ) ) {
                    $field_key = $field['field_key'];
                    $field_label = isset( $field['label'] ) ? $field['label'] : $field_key;
                    $value = get_post_meta( $post_id, '_' .$field_key, true );
                    if ( $value ) {
                        $output .= $field_label . ': ' . $value . "\n";
                    }
                }
            }
        }
        //file_put_contents( __DIR__ . '/submission-form-fields-2.json', json_encode( $output ) );
        return trim( $output );
    }
}
