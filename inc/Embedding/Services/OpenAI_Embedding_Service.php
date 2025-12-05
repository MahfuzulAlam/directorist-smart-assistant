<?php
/**
 * OpenAI Embedding Service
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Embedding\Services;

/**
 * OpenAI Embedding Service
 */
class OpenAI_Embedding_Service extends Abstract_Embedding_Service {

	/**
	 * Get service name
	 *
	 * @return string
	 */
	public function get_service_name(): string {
		return 'OpenAI';
	}

	/**
	 * Get required settings fields
	 *
	 * @return array
	 */
	public function get_required_settings(): array {
		return array( 'api_key', 'model' );
	}

	/**
	 * Get embedding dimensions based on model
	 *
	 * @return int
	 */
	public function get_dimensions(): int {
		$model = $this->get_setting( 'model', 'text-embedding-ada-002' );
		
		// OpenAI model dimensions
		$dimensions_map = array(
			'text-embedding-ada-002' => 1536,
			'text-embedding-3-small' => 1536,
			'text-embedding-3-large' => 3072,
		);

		return $dimensions_map[ $model ] ?? 1536;
	}

	/**
	 * Generate embedding for a single text
	 *
	 * @param string $text Text to embed.
	 * @return array|WP_Error Embedding vector or error.
	 */
	public function embed( string $text ) {
		$result = $this->batch_embed( array( $text ) );
		
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		return isset( $result[0] ) ? $result[0] : new \WP_Error( 'no_embedding', __( 'No embedding generated.', 'directorist-smart-assistant' ) );
	}

	/**
	 * Generate embeddings for multiple texts
	 *
	 * @param array $texts Array of texts to embed.
	 * @return array|WP_Error Array of embedding vectors or error.
	 */
	public function batch_embed( array $texts ) {
		if ( empty( $texts ) ) {
			return array();
		}

		$api_key = $this->get_setting( 'api_key' );
		$model = $this->get_setting( 'model', 'text-embedding-ada-002' );

		$url = 'https://api.openai.com/v1/embeddings';

		$body = array(
			'input' => $texts,
			'model' => $model,
		);

		$response = $this->make_request(
			$url,
			'POST',
			array(
				'Authorization' => 'Bearer ' . $api_key,
				'Content-Type'  => 'application/json',
			),
			$body
		);

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		// Extract embeddings from response
		$embeddings = array();
		if ( isset( $response['data'] ) && is_array( $response['data'] ) ) {
			foreach ( $response['data'] as $item ) {
				if ( isset( $item['embedding'] ) ) {
					$embeddings[] = $item['embedding'];
				}
			}
		}

		return $embeddings;
	}
}

