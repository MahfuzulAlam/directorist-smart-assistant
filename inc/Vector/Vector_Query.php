<?php
/**
 * Vector Query Handler
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Vector;

use DirectoristSmartAssistant\Service\Vector_API_Client;
use DirectoristSmartAssistant\Helpers\Listing_Helper;

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
	private function __construct() {}

	/**
	 * Query vector database for relevant listings.
	 *
	 * @param string $query_text Search query text.
	 * @param int    $top_k      Number of results to return.
	 * @param array  $filter     Optional filter conditions.
	 * @return array|\WP_Error
	 */
	public function query( string $query_text, int $top_k = 5, array $filter = array() ) {
		$client = Vector_API_Client::from_settings();

		if ( ! $client ) {
			return new \WP_Error(
				'missing_credentials',
				__( 'Vector storage API credentials are not configured.', 'directorist-smart-assistant' )
			);
		}

		return $client->query( $query_text, $top_k, $filter );
	}

	/**
	 * Get listings data from vector query results.
	 *
	 * Resolves vector results back to WordPress posts and enriches
	 * them with submission form field values.
	 *
	 * @param array $query_results Vector query results.
	 * @return array
	 */
	public function get_listings_from_query_results( array $query_results ): array {
		$listings  = array();
		$post_type = Listing_Helper::get_post_type();

		foreach ( $query_results as $result ) {
			$post_id = 0;
			if ( isset( $result['metadata']['listing_id'] ) ) {
				$post_id = intval( $result['metadata']['listing_id'] );
			}

			if ( empty( $post_id ) ) {
				continue;
			}

			$post = get_post( $post_id );

			if ( ! $post || $post->post_type !== $post_type || 'publish' !== $post->post_status ) {
				continue;
			}

			$listings[] = array(
				'id'                     => $post->ID,
				'title'                  => $post->post_title,
				'content'                => $post->post_content,
				'url'                    => get_permalink( $post->ID ),
				'submission_form_fields' => Listing_Helper::get_submission_form_fields_with_values( $post->ID ),
			);
		}

		return $listings;
	}
}
