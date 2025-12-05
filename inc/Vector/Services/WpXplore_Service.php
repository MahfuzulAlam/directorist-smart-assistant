<?php
/**
 * WpXplore Vector DB Service
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Vector\Services;

/**
 * WpXplore Vector DB Service
 */
class WpXplore_Service extends Abstract_Vector_DB_Service {

	/**
	 * Get service name
	 *
	 * @return string
	 */
	public function get_service_name(): string {
		return 'WpXplore';
	}

	/**
	 * Get required settings fields
	 *
	 * @return array
	 */
	public function get_required_settings(): array {
		return array( 'api_base_url', 'api_secret_key' );
	}

	/**
	 * Upsert a single vector
	 *
	 * @param string $id Vector ID.
	 * @param array  $vector Vector data (can be empty for WpXplore as it handles embedding).
	 * @param array  $metadata Optional metadata.
	 * @return bool|WP_Error
	 */
	public function upsert( string $id, array $vector, array $metadata = array() ) {
		$api_base_url = rtrim( $this->get_setting( 'api_base_url' ), '/' );
		$api_secret_key = $this->get_setting( 'api_secret_key' );

		$url = $api_base_url . '/api/v1/vectors/upsert';

		// WpXplore expects: post_id, text, metadata
		$body = array(
			'post_id'  => intval( $id ),
			'text'     => $metadata['text'] ?? '',
			'metadata' => $metadata,
		);

		$response = $this->make_request(
			$url,
			'POST',
			array(
				'X-API-Key'    => $api_secret_key,
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
		$api_base_url = rtrim( $this->get_setting( 'api_base_url' ), '/' );
		$api_secret_key = $this->get_setting( 'api_secret_key' );

		$url = $api_base_url . '/api/v1/vectors/batch-upsert';

		$body = array(
			'vectors' => $vectors,
		);

		$response = $this->make_request(
			$url,
			'POST',
			array(
				'X-API-Key'    => $api_secret_key,
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
		$api_base_url = rtrim( $this->get_setting( 'api_base_url' ), '/' );
		$api_secret_key = $this->get_setting( 'api_secret_key' );

		$url = $api_base_url . '/api/v1/vectors/' . urlencode( $id );

		$response = $this->make_request(
			$url,
			'DELETE',
			array(
				'X-API-Key'    => $api_secret_key,
				'Content-Type' => 'application/json',
			)
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
		$api_base_url = rtrim( $this->get_setting( 'api_base_url' ), '/' );
		$api_secret_key = $this->get_setting( 'api_secret_key' );

		$url = $api_base_url . '/api/v1/vectors/batch-delete';

		$body = array(
			'ids' => $ids,
		);

		$response = $this->make_request(
			$url,
			'POST',
			array(
				'X-API-Key'    => $api_secret_key,
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
		$api_base_url = rtrim( $this->get_setting( 'api_base_url' ), '/' );
		$api_secret_key = $this->get_setting( 'api_secret_key' );

		$url = $api_base_url . '/api/v1/vectors/query';

		$body = array(
			'vector' => $query_vector,
			'top_k'  => $top_k,
			'filter' => $filter,
		);

		$response = $this->make_request(
			$url,
			'POST',
			array(
				'X-API-Key'    => $api_secret_key,
				'Content-Type' => 'application/json',
			),
			$body
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['results'] ) ? $response['results'] : array();
	}

	/**
	 * Query by text (if service supports it)
	 *
	 * @param string $text Query text.
	 * @param int    $top_k Number of results.
	 * @param array  $filter Optional filter.
	 * @return array|WP_Error
	 */
	public function query_by_text( string $text, int $top_k = 5, array $filter = array() ) {
		$api_base_url = rtrim( $this->get_setting( 'api_base_url' ), '/' );
		$api_secret_key = $this->get_setting( 'api_secret_key' );

		$url = $api_base_url . '/api/v1/vectors/query';

		$body = array(
			'text'   => $text,
			'top_k'  => $top_k,
			'filter' => $filter,
		);

		$response = $this->make_request(
			$url,
			'POST',
			array(
				'X-API-Key'    => $api_secret_key,
				'Content-Type' => 'application/json',
			),
			$body
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		return isset( $response['results'] ) ? $response['results'] : array();
	}
}

