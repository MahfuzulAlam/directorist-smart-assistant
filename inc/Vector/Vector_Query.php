<?php
/**
 * Vector Query Handler
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Vector;

use DirectoristSmartAssistant\Settings\Settings_Manager;
use DirectoristSmartAssistant\Vector\Vector_Service_Manager;

/**
 * Vector Query class
 */
class Vector_Query {

	/**
	 * Instance
	 *
	 * @var Vector_Query
	 */
	private static $instance = null;

	/**
	 * Get instance
	 *
	 * @return Vector_Query
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
		// Constructor
	}

	/**
	 * Query vector database
	 *
	 * @param string $query_text Search query text.
	 * @param int    $top_k      Number of results to return.
	 * @param array  $filter     Optional filter array.
	 * @return array|WP_Error
	 */
	public function query( string $query_text, int $top_k = 5, array $filter = array() ) {
		// Get vector service
		$service_manager = Vector_Service_Manager::get_instance();
		$service = $service_manager->get_service();

		if ( is_wp_error( $service ) ) {
			error_log( 'Vector Query Error: ' . $service->get_error_message() );
			return $service;
		}

		// Use query_by_text if available, otherwise use query with empty vector
		$result = $service->query_by_text( $query_text, $top_k, $filter );

		if ( is_wp_error( $result ) ) {
			error_log( 'Vector Query Error: ' . $result->get_error_message() );
			return $result;
		}

		return $result;
	}

	/**
	 * Get listings data from vector query results
	 *
	 * @param array $query_results Vector query results.
	 * @return array
	 */
	public function get_listings_from_query_results( array $query_results ): array {
		$listings = array();
		$post_type = defined( 'ATBDP_POST_TYPE' ) ? ATBDP_POST_TYPE : 'at_biz_dir';

		foreach ( $query_results as $result ) {
			// Get post_id from result or metadata
			$post_id = 0;
			if ( isset( $result['post_id'] ) ) {
				$post_id = intval( $result['post_id'] );
			} elseif ( isset( $result['metadata']['post_id'] ) ) {
				$post_id = intval( $result['metadata']['post_id'] );
			}

			if ( empty( $post_id ) ) {
				continue;
			}

			$post = get_post( $post_id );

			if ( ! $post ) {
				continue;
			}

			// Only include Directorist listings
			if ( $post->post_type !== $post_type ) {
				continue;
			}

			// Only include published posts
			if ( 'publish' !== $post->post_status ) {
				continue;
			}

			$listings[] = array(
				'id'      => $post->ID,
				'title'   => $post->post_title,
				'content' => $post->post_content,
				'url'     => get_permalink( $post->ID ),
				'submission_form_fields' => $this->get_submission_form_fields_with_values( $post->ID ),
			);
		}

		return $listings;
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

