<?php
/**
 * Listing Helper
 *
 * Shared utility methods used across multiple classes.
 *
 * @package DirectoristAIAgents
 */

namespace DirectoristAIAgents\Helpers;

use DirectoristAIAgents\Settings\Settings_Manager;

/**
 * Listing Helper class
 */
class Listing_Helper {

	/**
	 * Get the Directorist post type slug.
	 *
	 * @return string
	 */
	public static function get_post_type(): string {
		return defined( 'ATBDP_POST_TYPE' ) ? ATBDP_POST_TYPE : 'at_biz_dir';
	}

	/**
	 * Get the directory type taxonomy slug.
	 *
	 * @return string
	 */
	public static function get_type_taxonomy(): string {
		return defined( 'ATBDP_TYPE' ) ? ATBDP_TYPE : 'at_biz_dir_types';
	}

	/**
	 * Get the category taxonomy slug.
	 *
	 * @return string
	 */
	public static function get_category_taxonomy(): string {
		return defined( 'ATBDP_CATEGORY' ) ? ATBDP_CATEGORY : 'at_biz_dir-category';
	}

	/**
	 * Get the location taxonomy slug.
	 *
	 * @return string
	 */
	public static function get_location_taxonomy(): string {
		return defined( 'ATBDP_LOCATION' ) ? ATBDP_LOCATION : 'at_biz_dir-location';
	}

	/**
	 * Get the decrypted vector API secret key.
	 *
	 * @return string
	 */
	public static function get_decrypted_secret_key(): string {
		$settings   = Settings_Manager::get_instance()->get_settings();
		$secret_key = $settings['vector_api_secret_key'] ?? '';

		if ( empty( $secret_key ) ) {
			return '';
		}

		return Settings_Manager::get_instance()->decrypt_api_key( $secret_key );
	}

	/**
	 * Get submission form fields with values for a listing.
	 *
	 * @param int $post_id The listing post ID.
	 * @return string Fields in "Label: Value" format, separated by newlines.
	 */
	public static function get_submission_form_fields_with_values( int $post_id ): string {
		$output        = '';
		$type_taxonomy = self::get_type_taxonomy();
		$listing_types = wp_get_post_terms( $post_id, $type_taxonomy, array( 'fields' => 'ids' ) );

		if ( is_wp_error( $listing_types ) || empty( $listing_types ) ) {
			return $output;
		}

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
				if ( ! is_array( $field ) || empty( $field['field_key'] ) ) {
					continue;
				}

				$field_key   = $field['field_key'];
				$field_label = $field['label'] ?? $field_key;
				$value       = get_post_meta( $post_id, '_' . $field_key, true );

				if ( $value ) {
					$output .= $field_label . ': ' . $value . "\n";
				}
			}
		}

		return trim( $output );
	}

	/**
	 * Invalidate the listings transient cache.
	 *
	 * @return void
	 */
	public static function invalidate_listings_cache(): void {
		delete_transient( 'directorist_smart_assistant_listings' );
	}
}
