<?php
/**
 * Embedding Service Interface
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Embedding\Services;

/**
 * Embedding Service Interface
 */
interface Embedding_Service_Interface {

	/**
	 * Initialize the service with settings
	 *
	 * @param array $settings Service settings.
	 * @return bool|WP_Error
	 */
	public function initialize( array $settings );

	/**
	 * Generate embedding for a single text
	 *
	 * @param string $text Text to embed.
	 * @return array|WP_Error Embedding vector or error.
	 */
	public function embed( string $text );

	/**
	 * Generate embeddings for multiple texts
	 *
	 * @param array $texts Array of texts to embed.
	 * @return array|WP_Error Array of embedding vectors or error.
	 */
	public function batch_embed( array $texts );

	/**
	 * Get embedding dimensions
	 *
	 * @return int
	 */
	public function get_dimensions(): int;

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

