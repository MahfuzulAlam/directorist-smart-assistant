<?php
/**
 * Vector Sync Handler
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Vector;

use DirectoristSmartAssistant\Settings\Settings_Manager;
use DirectoristSmartAssistant\Service\Vector_API_Client;
use DirectoristSmartAssistant\Helpers\Listing_Helper;

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
	 * Handle post save — sync to vector DB and invalidate caches.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @param bool     $update  Whether this is an existing post being updated.
	 * @return void
	 */
	public function handle_post_save( int $post_id, $post, bool $update ): void {
		$post_type = Listing_Helper::get_post_type();
		if ( $post->post_type !== $post_type ) {
			return;
		}

		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
			return;
		}

		if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
			return;
		}

		if ( 'trash' === $post->post_status ) {
			return;
		}

		// Always invalidate the fallback listings cache when a listing changes.
		Listing_Helper::invalidate_listings_cache();

		$settings = Settings_Manager::get_instance()->get_settings();
		if ( empty( $settings['vector_auto_sync'] ) ) {
			return;
		}

		$client = Vector_API_Client::from_settings();
		if ( ! $client ) {
			return;
		}

		$this->upsert_listing( $post_id, $post );
	}

	/**
	 * Upsert a single listing to the vector database.
	 *
	 * @param int      $post_id Post ID.
	 * @param \WP_Post $post    Post object.
	 * @return bool|\WP_Error
	 */
	public function upsert_listing( int $post_id, $post ) {
		if ( ! $post || ! isset( $post->ID ) || empty( $post->ID ) ) {
			return new \WP_Error( 'invalid_post', __( 'Invalid post object provided.', 'directorist-smart-assistant' ) );
		}

		$client = Vector_API_Client::from_settings();
		if ( ! $client ) {
			return new \WP_Error(
				'missing_credentials',
				__( 'Vector storage API credentials are not configured.', 'directorist-smart-assistant' )
			);
		}

		$text     = $this->prepare_listing_text( $post );
		$metadata = $this->prepare_listing_metadata( $post_id );

		/** Filter the metadata sent to the vector database during sync. */
		$metadata = apply_filters( 'dsa_listing_metadata', $metadata, $post_id );

		$existing_upsert_id = get_post_meta( $post_id, '_upsert_id', true );

		$data = array(
			'text'     => $text,
			'metadata' => $metadata,
		);

		if ( ! empty( $existing_upsert_id ) ) {
			$data['post_id'] = $existing_upsert_id;
		}

		$response = $client->upsert( $data );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$upsert_id = $response['vector_id'] ?? null;

		update_post_meta( $post_id, '_vector_sync', 1 );
		update_post_meta( $post_id, '_vector_sync_date', current_time( 'Y-m-d H:i:s' ) );

		if ( null !== $upsert_id ) {
			update_post_meta( $post_id, '_upsert_id', $upsert_id );
		}

		/** Fires after a single listing is synced to the vector database. */
		do_action( 'dsa_after_vector_sync', $post_id, $response );

		return true;
	}

	/**
	 * Prepare listing text for embedding (title + content + form fields).
	 *
	 * @param \WP_Post $post Post object.
	 * @return string
	 */
	private function prepare_listing_text( $post ): string {
		if ( ! $post || ! isset( $post->ID ) || empty( $post->ID ) ) {
			return '';
		}

		$post_id                = intval( $post->ID );
		$title                  = wp_strip_all_tags( $post->post_title ?? '' );
		$content                = wp_strip_all_tags( $post->post_content ?? '' );
		$submission_form_fields = Listing_Helper::get_submission_form_fields_with_values( $post_id );

		return trim( $title . "\n\n" . $content . "\n\n" . $submission_form_fields );
	}

	/**
	 * Prepare listing metadata for the vector database.
	 *
	 * @param int $post_id Post ID.
	 * @return array
	 */
	private function prepare_listing_metadata( int $post_id ): array {
		$metadata = array();
		$post     = get_post( $post_id );

		$categories = wp_get_post_terms( $post_id, Listing_Helper::get_category_taxonomy(), array( 'fields' => 'names' ) );
		$metadata['category'] = ( ! is_wp_error( $categories ) && ! empty( $categories ) )
			? implode( ', ', $categories )
			: '';

		$locations = wp_get_post_terms( $post_id, Listing_Helper::get_location_taxonomy(), array( 'fields' => 'names' ) );
		$metadata['location'] = ( ! is_wp_error( $locations ) && ! empty( $locations ) )
			? implode( ', ', $locations )
			: '';

		$types = wp_get_post_terms( $post_id, Listing_Helper::get_type_taxonomy(), array( 'fields' => 'names' ) );
		$metadata['type'] = ( ! is_wp_error( $types ) && ! empty( $types ) )
			? implode( ', ', $types )
			: '';

		$metadata['listing_id']     = $post_id;
		$metadata['listing_status'] = $post ? $post->post_status : '';

		$ai_blocked = get_post_meta( $post_id, '_ai_blocked', true );
		if ( $ai_blocked ) {
			$metadata['ai_blocked'] = $ai_blocked;
		}

		return $metadata;
	}

	/**
	 * Batch upsert listings to the vector database.
	 *
	 * @param array $post_ids Optional array of specific post IDs to sync.
	 * @return array Results with 'success', 'failed', 'total', and 'errors' keys.
	 */
	public function batch_upsert_listings( array $post_ids = array() ): array {
		$client = Vector_API_Client::from_settings();
		if ( ! $client ) {
			return array(
				'success' => 0,
				'failed'  => 0,
				'total'   => 0,
				'errors'  => array( __( 'Vector storage API credentials are not configured.', 'directorist-smart-assistant' ) ),
			);
		}

		if ( empty( $post_ids ) ) {
			$post_ids = $this->get_listings_for_sync();
		}

		if ( empty( $post_ids ) ) {
			return array(
				'success' => 0,
				'failed'  => 0,
				'total'   => 0,
				'errors'  => array( __( 'No listings found to sync.', 'directorist-smart-assistant' ) ),
			);
		}

		$settings   = Settings_Manager::get_instance()->get_settings();
		$chunk_size = intval( $settings['vector_listing_chunk_size'] ?? 20 );

		$results = array(
			'success' => 0,
			'failed'  => 0,
			'total'   => count( $post_ids ),
			'errors'  => array(),
		);

		$batches = array_chunk( $post_ids, $chunk_size );

		foreach ( $batches as $batch_index => $batch ) {
			$contents = array();

			foreach ( $batch as $post_id ) {
				$post = get_post( $post_id );

				if ( ! $post ) {
					$results['failed']++;
					$results['errors'][] = sprintf(
						/* translators: %d: Post ID */
						__( 'Post ID %d not found.', 'directorist-smart-assistant' ),
						$post_id
					);
					continue;
				}

				$text     = $this->prepare_listing_text( $post );
				$metadata = $this->prepare_listing_metadata( $post_id );

				/** Filter the metadata sent to the vector database during sync. */
				$metadata = apply_filters( 'dsa_listing_metadata', $metadata, $post_id );

				$existing_upsert_id = get_post_meta( $post_id, '_upsert_id', true );

				$content_item = array(
					'text'     => $text,
					'metadata' => $metadata,
				);

				if ( ! empty( $existing_upsert_id ) ) {
					$content_item['post_id'] = $existing_upsert_id;
				}

				$contents[] = $content_item;
			}

			if ( empty( $contents ) ) {
				continue;
			}

			$response = $client->batch_upsert( $contents );

			if ( is_wp_error( $response ) ) {
				$results['failed'] += count( $batch );
				$results['errors'][] = sprintf(
					/* translators: %1$d: Batch number, %2$s: Error message */
					__( 'Batch %1$d: %2$s', 'directorist-smart-assistant' ),
					$batch_index + 1,
					$response->get_error_message()
				);
				continue;
			}

			if ( isset( $response['results'] ) && is_array( $response['results'] ) ) {
				foreach ( $response['results'] as $index => $result ) {
					$post_id = $batch[ $index ] ?? null;
					if ( ! $post_id ) {
						continue;
					}

					$upsert_id = $result['vector_id'] ?? $result['post_id'] ?? null;

					if ( $upsert_id ) {
						update_post_meta( $post_id, '_vector_sync', 1 );
						update_post_meta( $post_id, '_vector_sync_date', current_time( 'Y-m-d H:i:s' ) );
						update_post_meta( $post_id, '_upsert_id', $upsert_id );
						$results['success']++;
					} else {
						$results['failed']++;
						if ( isset( $result['error'] ) ) {
							$results['errors'][] = sprintf(
								/* translators: %1$d: Post ID, %2$s: Error message */
								__( 'Post ID %1$d: %2$s', 'directorist-smart-assistant' ),
								$post_id,
								$result['error']
							);
						}
					}
				}
			} else {
				// Unknown response format — mark as failed rather than assuming success.
				$results['failed'] += count( $batch );
				$results['errors'][] = sprintf(
					/* translators: %d: Batch number */
					__( 'Batch %d: Unexpected response format from API.', 'directorist-smart-assistant' ),
					$batch_index + 1
				);
				error_log( 'Vector Batch Sync: Unexpected response format for batch ' . ( $batch_index + 1 ) );
			}
		}

		/** Fires after a bulk sync operation completes. */
		do_action( 'dsa_after_bulk_sync', $results );

		return $results;
	}

	/**
	 * Get listing post IDs eligible for sync based on settings filters.
	 *
	 * @return array Array of post IDs.
	 */
	private function get_listings_for_sync(): array {
		$settings  = Settings_Manager::get_instance()->get_settings();
		$post_type = Listing_Helper::get_post_type();

		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'fields'         => 'ids',
		);

		$directory_types = $settings['vector_sync_directory_types'] ?? array();
		if ( ! empty( $directory_types ) && is_array( $directory_types ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => Listing_Helper::get_type_taxonomy(),
					'field'    => 'term_id',
					'terms'    => array_map( 'intval', $directory_types ),
					'operator' => 'IN',
				),
			);
		}

		$listing_statuses = $settings['vector_sync_listing_statuses'] ?? array();
		if ( ! empty( $listing_statuses ) && is_array( $listing_statuses ) ) {
			$args['post_status'] = array_map( 'sanitize_text_field', $listing_statuses );
		} else {
			$args['post_status'] = 'publish';
		}

		$query = new \WP_Query( $args );
		return $query->get_posts();
	}
}
