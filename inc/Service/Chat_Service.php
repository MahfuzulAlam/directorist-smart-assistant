<?php
/**
 * Chat Service
 *
 * Handles chat message processing, prompt building, and context assembly.
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Service;

use DirectoristSmartAssistant\Settings\Settings_Manager;
use DirectoristSmartAssistant\Vector\Vector_Query;
use DirectoristSmartAssistant\Helpers\Listing_Helper;

/**
 * Chat Service class
 */
class Chat_Service {

	/**
	 * Instance.
	 *
	 * @var Chat_Service|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return Chat_Service
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
	 * Process a chat message and return the AI response.
	 *
	 * @param string $message      User message.
	 * @param array  $conversation Previous conversation messages.
	 * @return array|\WP_Error Response array with 'message' key, or WP_Error.
	 */
	public function process_message( string $message, array $conversation = array() ) {
		$settings = Settings_Manager::get_instance()->get_settings();

		$client = Vector_API_Client::from_settings();
		if ( ! $client ) {
			return new \WP_Error(
				'not_configured',
				__( 'Vector storage API credentials are not configured.', 'directorist-smart-assistant' )
			);
		}

		// Step 1: Analyze context to determine action (pass conversation history for better context).
		$context_decider = Context_Decider::get_instance();
		$context         = $context_decider->analyze_context( $message, $conversation );

		file_put_contents( __DIR__ . '/context.json', json_encode( $context ) );

		if ( is_wp_error( $context ) ) {
			// Fallback to search on error.
			$context = array(
				'action'       => 'search',
				'trigger_type' => null,
				'listing_id'   => null,
				'confidence'   => 0,
			);
		}

		$action       = $context['action'] ?? 'search';
		$trigger_type = $context['trigger_type'] ?? null;
		$listing_id   = $context['listing_id'] ?? null;
		$confidence   = $context['confidence'] ?? 0;

		/** Fires after context decision is made. */
		do_action( 'dsa_context_decided', $context, $message, $conversation );

		// Step 2: Handle triggers (email, visit listing).
		if ( 'trigger' === $action ) {
			$trigger_response = $this->handle_trigger( $trigger_type, $listing_id, $message );

			/** Fires after trigger action is handled. */
			do_action( 'dsa_trigger_handled', $trigger_type, $listing_id, $trigger_response );

			return $trigger_response;
		}

		// Step 3: Determine if vector search should be used.
		$use_vector_search = ( 'search' === $action );

		// Step 4: Get listings context (only if search action).
		$listings_context = '';
		if ( $use_vector_search ) {
			$listings_context = $this->get_listings_context( $message );

			/** Filter the listings context string before it is injected into the system prompt. */
			$listings_context = apply_filters( 'dsa_listings_context', $listings_context, $message );
		}

		$system_prompt = $this->build_system_prompt( $settings, $listings_context );

		/** Filter the full system prompt before it is sent to the AI. */
		$system_prompt = apply_filters( 'dsa_system_prompt', $system_prompt, $message );

		$messages = $this->build_messages( $system_prompt, $conversation, $message );

		// Step 5: Call chat API with use_vector_search parameter.
		$response = $client->chat(
			$message,
			$system_prompt,
			$settings['model'] ?? 'gpt-3.5-turbo',
			$messages,
			(float) ( $settings['temperature'] ?? 0.7 ),
			(int) ( $settings['max_tokens'] ?? 1000 ),
			$use_vector_search
		);

		if ( is_wp_error( $response ) ) {
			/** Fires when a chat API call fails. */
			do_action( 'dsa_chat_error', $message, $response );
			return $response;
		}

		/** Fires after a successful chat API response. */
		do_action( 'dsa_after_chat_response', $message, $response );

		return $response;
	}

	/**
	 * Handle trigger actions (contact owner, email admin, visit listing).
	 *
	 * @param string|null $trigger_type Type of trigger.
	 * @param int|null    $listing_id   Listing ID (if applicable).
	 * @param string      $message      User message.
	 * @return array Response with confirmation message.
	 */
	private function handle_trigger( $trigger_type, $listing_id, string $message ): array {
		$email_service = Email_Service::get_instance();

		switch ( $trigger_type ) {
			case 'contact_listing_owner':
				if ( empty( $listing_id ) ) {
					return array(
						'message' => __( 'I couldn\'t identify which listing you want to contact. Could you please specify the listing name?', 'directorist-smart-assistant' ),
					);
				}

				$result = $email_service->send_to_listing_owner( $listing_id, $message );

				if ( $result ) {
					$listing_title = get_the_title( $listing_id );
					return array(
						'message' => sprintf(
							/* translators: %s: Listing title */
							__( 'Great! I\'ve sent your message to the owner of "%s". They should get back to you soon.', 'directorist-smart-assistant' ),
							$listing_title
						),
					);
				}

				return array(
					'message' => __( 'Sorry, I couldn\'t send the email at this time. Please try again later or contact the listing owner directly.', 'directorist-smart-assistant' ),
				);

			case 'send_email_admin':
				$result = $email_service->send_to_admin( $message );

				if ( $result ) {
					return array(
						'message' => __( 'Thank you! I\'ve forwarded your message to our admin team. They will review it and get back to you shortly.', 'directorist-smart-assistant' ),
					);
				}

				return array(
					'message' => __( 'Sorry, I couldn\'t send your message to the admin at this time. Please try again later.', 'directorist-smart-assistant' ),
				);

			case 'visit_listing':
				if ( empty( $listing_id ) ) {
					return array(
						'message' => __( 'I couldn\'t identify which listing you want to visit. Could you please be more specific?', 'directorist-smart-assistant' ),
					);
				}

				$listing_url = $this->get_listing_url( $listing_id );

				if ( empty( $listing_url ) ) {
					return array(
						'message' => __( 'Sorry, I couldn\'t find that listing. It may have been removed or is no longer available.', 'directorist-smart-assistant' ),
					);
				}

				$listing_title = get_the_title( $listing_id );

				// Return special response with URL for frontend to open in new tab.
				return array(
					'message'     => sprintf(
						/* translators: 1: Listing title, 2: Listing URL */
						__( 'Here\'s the listing for "%1$s". <a href="%2$s" target="_blank" rel="noopener noreferrer">Click here to open it</a>.', 'directorist-smart-assistant' ),
						esc_html( $listing_title ),
						esc_url( $listing_url )
					),
					'action'      => 'open_url',
					'url'         => $listing_url,
					'listing_id'  => $listing_id,
				);

			default:
				// Unknown trigger type, fall back to regular chat.
				return array(
					'message' => __( 'I\'m not sure how to help with that. Could you please rephrase your request?', 'directorist-smart-assistant' ),
				);
		}
	}

	/**
	 * Get listing URL.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return string Listing URL or empty string.
	 */
	private function get_listing_url( int $listing_id ): string {
		$post = get_post( $listing_id );

		if ( ! $post || 'publish' !== $post->post_status ) {
			return '';
		}

		$url = get_permalink( $listing_id );

		/** Filter the listing URL for visit action. */
		return apply_filters( 'dsa_listing_visit_url', $url, $listing_id );
	}

	/**
	 * Build the system prompt from settings and listings context.
	 *
	 * @param array  $settings         Plugin settings.
	 * @param string $listings_context  Formatted listings context string.
	 * @return string
	 */
	private function build_system_prompt( array $settings, string $listings_context ): string {
		$system_prompt = ! empty( $settings['system_prompt'] )
			? $settings['system_prompt']
			: 'You are a helpful assistant for a business directory website. Answer questions about the listings available on this site.';

		$website_name = get_bloginfo( 'name' );
		if ( ! empty( $website_name ) ) {
			$system_prompt = sprintf(
				/* translators: %s: Website name */
				__( 'You are a helpful assistant for the website - %s. ', 'directorist-smart-assistant' ),
				$website_name
			) . $system_prompt . "\n";
		}

		$agent_name = ! empty( $settings['chat_agent_name'] ) ? trim( $settings['chat_agent_name'] ) : '';
		if ( ! empty( $agent_name ) ) {
			$system_prompt = sprintf(
				/* translators: %s: Agent name */
				__( 'Your name is %s. ', 'directorist-smart-assistant' ),
				$agent_name
			) . $system_prompt;
		}

		$system_prompt .= "\n\nAvailable listings:\n" . $listings_context;

		return $system_prompt;
	}

	/**
	 * Build the full messages array for the chat API.
	 *
	 * @param string $system_prompt System prompt.
	 * @param array  $conversation  Previous conversation messages.
	 * @param string $message       Current user message.
	 * @return array
	 */
	private function build_messages( string $system_prompt, array $conversation, string $message ): array {
		$messages = array();

		$messages[] = array(
			'role'    => 'system',
			'content' => $system_prompt,
		);

		$allowed_roles = array( 'user', 'assistant', 'system' );
		foreach ( $conversation as $conv ) {
			if ( ! isset( $conv['role'], $conv['content'] ) ) {
				continue;
			}
			if ( ! in_array( $conv['role'], $allowed_roles, true ) ) {
				continue;
			}
			$messages[] = array(
				'role'    => sanitize_text_field( $conv['role'] ),
				'content' => sanitize_textarea_field( $conv['content'] ),
			);
		}

		$messages[] = array(
			'role'    => 'user',
			'content' => $message,
		);

		return $messages;
	}

	/**
	 * Get listings context string, preferring vector search with DB fallback.
	 *
	 * @param string $query_text User query for semantic search.
	 * @return string
	 */
	private function get_listings_context( string $query_text ): string {
		$settings       = Settings_Manager::get_instance()->get_settings();
		$api_base_url   = $settings['vector_api_base_url'] ?? '';
		$api_secret_key = $settings['vector_api_secret_key'] ?? '';

		if ( empty( $api_base_url ) || empty( $api_secret_key ) ) {
			return $this->get_listings_context_fallback();
		}

		$vector_query = Vector_Query::get_instance();

		/** Filter the number of vector search results returned. */
		$top_k        = apply_filters( 'dsa_vector_query_top_k', 5 );
		$query_results = $vector_query->query( $query_text, $top_k );

		if ( is_wp_error( $query_results ) ) {
			return $this->get_listings_context_fallback();
		}

		$listings = $vector_query->get_listings_from_query_results( $query_results );

		$context = '';
		foreach ( $listings as $listing ) {
			$context .= sprintf(
				"Title: %s\nContent: %s\nURL: %s\nRelated Information: %s\n\n",
				$listing['title'],
				wp_strip_all_tags( $listing['content'] ),
				$listing['url'] ?? '',
				$listing['submission_form_fields'] ?? ''
			);
		}

		return $context;
	}

	/**
	 * Fallback: build context from recently modified published listings.
	 *
	 * @return string
	 */
	private function get_listings_context_fallback(): string {
		$listings = $this->get_fallback_listings_data();
		$context  = '';

		foreach ( $listings as $listing ) {
			$url = isset( $listing['id'] ) ? get_permalink( $listing['id'] ) : '';
			$context .= sprintf(
				"Title: %s\nContent: %s\nURL: %s\n\n",
				$listing['title'],
				wp_strip_all_tags( $listing['content'] ),
				$url
			);
		}

		return $context;
	}

	/**
	 * Get fallback listings data with caching and sensible limits.
	 *
	 * @return array
	 */
	private function get_fallback_listings_data(): array {
		$cache_key = 'directorist_smart_assistant_listings';
		$cached    = get_transient( $cache_key );

		if ( false !== $cached ) {
			return $cached;
		}

		$post_type = Listing_Helper::get_post_type();

		/** Filter the maximum number of listings loaded in fallback mode (default 50). */
		$limit = apply_filters( 'dsa_fallback_listings_limit', 50 );

		$args = array(
			'post_type'      => $post_type,
			'posts_per_page' => $limit,
			'post_status'    => 'publish',
			'orderby'        => 'modified',
			'order'          => 'DESC',
		);

		$query  = new \WP_Query( $args );
		$posts  = $query->get_posts();
		$result = array();

		foreach ( $posts as $post ) {
			$content = wp_strip_all_tags( $post->post_content );
			if ( strlen( $content ) > 500 ) {
				$content = substr( $content, 0, 500 ) . '...';
			}
			$result[] = array(
				'id'      => $post->ID,
				'title'   => $post->post_title,
				'content' => $content,
			);
		}

		set_transient( $cache_key, $result, HOUR_IN_SECONDS );

		return $result;
	}
}
