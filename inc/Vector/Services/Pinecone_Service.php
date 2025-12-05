<?php
/**
 * Pinecone Vector DB Service
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Vector\Services;

/**
 * Pinecone Vector DB Service
 */
class Pinecone_Service extends Abstract_Vector_DB_Service {

	/**
	 * Get service name
	 *
	 * @return string
	 */
	public function get_service_name(): string {
		return 'Pinecone';
	}

	/**
	 * Get required settings fields
	 *
	 * @return array
	 */
	public function get_required_settings(): array {
		return array( 'api_key', 'environment', 'index_name' );
	}

	/**
	 * Get Pinecone API base URL
	 *
	 * @return string
	 */
	private function get_api_base_url(): string {
		$environment = $this->get_setting( 'environment' );
		$index_name = $this->get_setting( 'index_name' );
		return "https://{$index_name}-{$environment}.svc.pinecone.io";
	}

	/**
	 * Upsert a single vector
	 *
	 * @param string $id Vector ID.
	 * @param array  $vector Vector data.
	 * @param array  $metadata Optional metadata.
	 * @return bool|WP_Error
	 */
	public function upsert( string $id, array $vector, array $metadata = array() ) {
		$url = $this->get_api_base_url() . '/vectors/upsert';

		$body = array(
			'vectors' => array(
				array(
					'id'       => $id,
					'values'   => $vector,
					'metadata' => $metadata,
				),
			),
		);

		$response = $this->make_request(
			$url,
			'POST',
			array(
				'Api-Key'      => $this->get_setting( 'api_key' ),
				'Content-Type' => 'application/json',
			),
			$body
		);

		return is_wp_error( $response ) ? $response : true;
	}

	/**
	 * Batch upsert vectors
	 *
	 * @param array $vectors Array of vectors with id, vector, and metadata.
	 * @return bool|WP_Error
	 */
	public function batch_upsert( array $vectors ) {
		$url = $this->get_api_base_url() . '/vectors/upsert';

		$pinecone_vectors = array();
		foreach ( $vectors as $vector ) {
			$pinecone_vectors[] = array(
				'id'       => $vector['id'],
				'values'   => $vector['vector'],
				'metadata' => $vector['metadata'] ?? array(),
			);
		}

		$body = array(
			'vectors' => $pinecone_vectors,
		);

		$response = $this->make_request(
			$url,
			'POST',
			array(
				'Api-Key'      => $this->get_setting( 'api_key' ),
				'Content-Type' => 'application/json',
			),
			$body
		);

		return is_wp_error( $response ) ? $response : true;
	}

	/**
	 * Delete a vector by ID
	 *
	 * @param string $id Vector ID.
	 * @return bool|WP_Error
	 */
	public function delete( string $id ) {
		$url = $this->get_api_base_url() . '/vectors/delete';

		$body = array(
			'ids' => array( $id ),
		);

		$response = $this->make_request(
			$url,
			'POST',
			array(
				'Api-Key'      => $this->get_setting( 'api_key' ),
				'Content-Type' => 'application/json',
			),
			$body
		);

		return is_wp_error( $response ) ? $response : true;
	}

	/**
	 * Batch delete vectors
	 *
	 * @param array $ids Array of vector IDs.
	 * @return bool|WP_Error
	 */
	public function batch_delete( array $ids ) {
		$url = $this->get_api_base_url() . '/vectors/delete';

		$body = array(
			'ids' => $ids,
		);

		$response = $this->make_request(
			$url,
			'POST',
			array(
				'Api-Key'      => $this->get_setting( 'api_key' ),
				'Content-Type' => 'application/json',
			),
			$body
		);

		return is_wp_error( $response ) ? $response : true;
	}

	/**
	 * Query vectors
	 *
	 * @param array $query_vector Query vector.
	 * @param int   $top_k Number of results.
	 * @param array $filter Optional filter.
	 * @return array|WP_Error
	 */
	public function query( array $query_vector, int $top_k = 5, array $filter = array() ) {
		$url = $this->get_api_base_url() . '/query';

		$body = array(
			'vector'   => $query_vector,
			'topK'     => $top_k,
			'includeMetadata' => true,
		);

		if ( ! empty( $filter ) ) {
			$body['filter'] = $filter;
		}

		$response = $this->make_request(
			$url,
			'POST',
			array(
				'Api-Key'      => $this->get_setting( 'api_key' ),
				'Content-Type' => 'application/json',
			),
			$body
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Transform Pinecone response to standard format
		$results = array();
		if ( isset( $response['matches'] ) && is_array( $response['matches'] ) ) {
			foreach ( $response['matches'] as $match ) {
				$results[] = array(
					'id'       => $match['id'] ?? '',
					'score'    => $match['score'] ?? 0,
					'metadata' => $match['metadata'] ?? array(),
				);
			}
		}

		return $results;
	}

	/**
	 * Query by text (Pinecone doesn't support text query directly, needs embedding)
	 *
	 * @param string $text Query text.
	 * @param int    $top_k Number of results.
	 * @param array  $filter Optional filter.
	 * @return array|WP_Error
	 */
	public function query_by_text( string $text, int $top_k = 5, array $filter = array() ) {
		// Pinecone requires vector embeddings, not text
		// This would need to call an embedding service first
		return new \WP_Error(
			'not_supported',
			__( 'Pinecone does not support direct text queries. Please use query() with a vector embedding.', 'directorist-smart-assistant' )
		);
	}
}

