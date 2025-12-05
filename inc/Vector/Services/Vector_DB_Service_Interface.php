<?php
/**
 * Vector DB Service Interface
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Vector\Services;

/**
 * Vector DB Service Interface
 */
interface Vector_DB_Service_Interface {

	/**
	 * Initialize the service with settings
	 *
	 * @param array $settings Service settings.
	 * @return bool|WP_Error
	 */
	public function initialize( array $settings );

	/**
	 * Upsert a single vector
	 *
	 * @param string $id Vector ID.
	 * @param array  $vector Vector data.
	 * @param array  $metadata Optional metadata.
	 * @return bool|WP_Error
	 */
	public function upsert( string $id, array $vector, array $metadata = array() );

	/**
	 * Batch upsert vectors
	 *
	 * @param array $vectors Array of vectors with id, vector, and metadata.
	 * @return bool|WP_Error
	 */
	public function batch_upsert( array $vectors );

	/**
	 * Delete a vector by ID
	 *
	 * @param string $id Vector ID.
	 * @return bool|WP_Error
	 */
	public function delete( string $id );

	/**
	 * Batch delete vectors
	 *
	 * @param array $ids Array of vector IDs.
	 * @return bool|WP_Error
	 */
	public function batch_delete( array $ids );

	/**
	 * Query vectors
	 *
	 * @param array $query_vector Query vector.
	 * @param int   $top_k Number of results.
	 * @param array $filter Optional filter.
	 * @return array|WP_Error
	 */
	public function query( array $query_vector, int $top_k = 5, array $filter = array() );

	/**
	 * Query by text (if service supports it)
	 *
	 * @param string $text Query text.
	 * @param int    $top_k Number of results.
	 * @param array  $filter Optional filter.
	 * @return array|WP_Error
	 */
	public function query_by_text( string $text, int $top_k = 5, array $filter = array() );

	/**
	 * Get service name
	 *
	 * @return string
	 */
	public function get_service_name(): string;

	/**
	 * Get required settings fields
	 *
	 * @return array
	 */
	public function get_required_settings(): array;
}

