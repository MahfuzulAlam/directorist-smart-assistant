<?php
/**
 * Context Decider Service
 *
 * Analyzes user messages to determine the appropriate action and context.
 *
 * @package DirectoristAIAgents
 */

namespace DirectoristAIAgents\Service;

use DirectoristAIAgents\Settings\Settings_Manager;
use DirectoristAIAgents\Helpers\Listing_Helper;

/**
 * Context_Decider class
 */
class Context_Decider {

	/**
	 * Instance.
	 *
	 * @var Context_Decider|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return Context_Decider
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {}

	/**
	 * Analyze user message and determine context/action.
	 *
	 * @param string $message              User message text.
	 * @param array  $conversation_history Previous conversation messages.
	 * @return array|\WP_Error Context decision data or WP_Error.
	 */
	public function analyze_context( string $message, array $conversation_history = array() ) {
		$client = Vector_API_Client::from_settings();
		if ( ! $client ) {
			return new \WP_Error(
				'not_configured',
				__( 'Vector API is not configured.', 'directorist-ai-agents' )
			);
		}

		$response = $client->decide_context( $message, $conversation_history );

		if ( is_wp_error( $response ) ) {
			error_log( 'Context Decider Error: ' . $response->get_error_message() );
			// Fallback to search action on error.
			return array(
				'action'         => 'search',
				'trigger_type'   => null,
				'listing_id'     => null,
				'confidence'     => 0,
				'reason'         => 'Fallback due to API error',
				'tokens_used'    => 0,
			);
		}

		// Validate and normalize response.
		$action       = $response['action'] ?? 'search';
		$trigger_type = $response['trigger_type'] ?? null;
		$listing_id   = ! empty( $response['listing_id'] ) ? absint( $response['listing_id'] ) : null;
		$confidence   = isset( $response['confidence'] ) ? (float) $response['confidence'] : 0;
		$reason       = $response['reason'] ?? '';
		$tokens_used  = isset( $response['tokens_used'] ) ? absint( $response['tokens_used'] ) : 0;
		$model        = $response['model'] ?? '';
		$response_time = isset( $response['response_time_ms'] ) ? (float) $response['response_time_ms'] : 0;

		// Log context decision for debugging.
		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			error_log(
				sprintf(
					'Context Decision: action=%s, trigger=%s, listing_id=%d, confidence=%.2f, reason=%s, time=%.0fms, tokens=%d, model=%s',
					$action,
					$trigger_type ?? 'null',
					$listing_id ?? 0,
					$confidence,
					$reason,
					$response_time,
					$tokens_used,
					$model
				)
			);
		}

		$decision = array(
			'action'           => $action,
			'trigger_type'     => $trigger_type,
			'listing_id'       => $listing_id,
			'confidence'       => $confidence,
			'reason'           => $reason,
			'tokens_used'      => $tokens_used,
			'model'            => $model,
			'response_time_ms' => $response_time,
			'raw_response'     => $response,
		);

		// Apply filter for extensibility.
		return apply_filters( 'daia_context_decision', $decision, $message, $conversation_history );
	}
}
