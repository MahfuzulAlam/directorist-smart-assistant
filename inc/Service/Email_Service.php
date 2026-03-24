<?php
/**
 * Email Service
 *
 * Handles email sending for contact and notification triggers.
 *
 * @package DirectoristSmartAssistant
 */

namespace DirectoristSmartAssistant\Service;

/**
 * Email_Service class
 */
class Email_Service {

	/**
	 * Instance.
	 *
	 * @var Email_Service|null
	 */
	private static $instance = null;

	/**
	 * Get instance.
	 *
	 * @return Email_Service
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
	 * Send email to listing owner.
	 *
	 * @param int    $listing_id Listing post ID.
	 * @param string $message    User message to send.
	 * @param string $from_email Sender email (optional, uses default if empty).
	 * @param string $from_name  Sender name (optional).
	 * @return bool True on success, false on failure.
	 */
	public function send_to_listing_owner( int $listing_id, string $message, string $from_email = '', string $from_name = '' ): bool {
		$owner_email = $this->get_listing_owner_email( $listing_id );

		if ( empty( $owner_email ) ) {
			error_log( "Email Service: No owner email found for listing ID {$listing_id}" );
			return false;
		}

		$listing = get_post( $listing_id );
		if ( ! $listing ) {
			return false;
		}

		$subject = sprintf(
			/* translators: %s: Listing title */
			__( 'New inquiry about: %s', 'directorist-smart-assistant' ),
			$listing->post_title
		);

		$body = $this->build_email_body(
			$subject,
			$message,
			array(
				'listing_title' => $listing->post_title,
				'listing_url'   => get_permalink( $listing_id ),
			)
		);

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( ! empty( $from_email ) ) {
			$reply_to = $from_name
				? sprintf( '%s <%s>', $from_name, $from_email )
				: $from_email;
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		/** Filter the email headers before sending to listing owner. */
		$headers = apply_filters( 'dsa_listing_owner_email_headers', $headers, $listing_id, $message );

		/** Filter the email subject before sending to listing owner. */
		$subject = apply_filters( 'dsa_listing_owner_email_subject', $subject, $listing_id );

		/** Filter the email body before sending to listing owner. */
		$body = apply_filters( 'dsa_listing_owner_email_body', $body, $listing_id, $message );

		$result = wp_mail( $owner_email, $subject, $body, $headers );

		if ( $result ) {
			/** Fires after successfully sending email to listing owner. */
			do_action( 'dsa_listing_owner_email_sent', $listing_id, $owner_email, $message );
		} else {
			error_log( "Email Service: Failed to send email to listing owner for listing ID {$listing_id}" );
		}

		return $result;
	}

	/**
	 * Send email to site admin.
	 *
	 * @param string $message    User message to send.
	 * @param string $from_email Sender email (optional).
	 * @param string $from_name  Sender name (optional).
	 * @return bool True on success, false on failure.
	 */
	public function send_to_admin( string $message, string $from_email = '', string $from_name = '' ): bool {
		$admin_email = $this->get_admin_email();

		if ( empty( $admin_email ) ) {
			error_log( 'Email Service: No admin email configured.' );
			return false;
		}

		$subject = __( 'New message from Smart Assistant', 'directorist-smart-assistant' );

		$body = $this->build_email_body(
			$subject,
			$message,
			array( 'site_name' => get_bloginfo( 'name' ) )
		);

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( ! empty( $from_email ) ) {
			$reply_to = $from_name
				? sprintf( '%s <%s>', $from_name, $from_email )
				: $from_email;
			$headers[] = 'Reply-To: ' . $reply_to;
		}

		/** Filter the email headers before sending to admin. */
		$headers = apply_filters( 'dsa_admin_email_headers', $headers, $message );

		/** Filter the email subject before sending to admin. */
		$subject = apply_filters( 'dsa_admin_email_subject', $subject );

		/** Filter the email body before sending to admin. */
		$body = apply_filters( 'dsa_admin_email_body', $body, $message );

		$result = wp_mail( $admin_email, $subject, $body, $headers );

		if ( $result ) {
			/** Fires after successfully sending email to admin. */
			do_action( 'dsa_admin_email_sent', $admin_email, $message );
		} else {
			error_log( 'Email Service: Failed to send email to admin.' );
		}

		return $result;
	}

	/**
	 * Get listing owner email address.
	 *
	 * @param int $listing_id Listing post ID.
	 * @return string Owner email or empty string.
	 */
	private function get_listing_owner_email( int $listing_id ): string {
		// Check for Directorist-specific author email meta.
		$author_email = get_post_meta( $listing_id, '_author_email', true );
		if ( ! empty( $author_email ) && is_email( $author_email ) ) {
			return sanitize_email( $author_email );
		}

		// Fallback: get post author's email.
		$post = get_post( $listing_id );
		if ( $post && $post->post_author ) {
			$author = get_userdata( $post->post_author );
			if ( $author && ! empty( $author->user_email ) ) {
				return sanitize_email( $author->user_email );
			}
		}

		/** Filter the listing owner email (allows custom logic). */
		return apply_filters( 'dsa_listing_owner_email', '', $listing_id );
	}

	/**
	 * Get admin email address.
	 *
	 * @return string Admin email or empty string.
	 */
	private function get_admin_email(): string {
		$admin_email = get_option( 'admin_email' );

		if ( empty( $admin_email ) || ! is_email( $admin_email ) ) {
			return '';
		}

		/** Filter the admin email for notifications. */
		return apply_filters( 'dsa_admin_notification_email', sanitize_email( $admin_email ) );
	}

	/**
	 * Build HTML email body.
	 *
	 * @param string $subject Email subject.
	 * @param string $message User message.
	 * @param array  $context Additional context data.
	 * @return string HTML email body.
	 */
	private function build_email_body( string $subject, string $message, array $context = array() ): string {
		ob_start();
		?>
		<!DOCTYPE html>
		<html>
		<head>
			<meta charset="UTF-8">
			<meta name="viewport" content="width=device-width, initial-scale=1.0">
			<style>
				body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; line-height: 1.6; color: #333; }
				.container { max-width: 600px; margin: 0 auto; padding: 20px; }
				.header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: #fff; padding: 20px; text-align: center; border-radius: 8px 8px 0 0; }
				.content { background: #f9f9f9; padding: 30px; border-radius: 0 0 8px 8px; }
				.message { background: #fff; padding: 20px; border-left: 4px solid #667eea; margin: 20px 0; border-radius: 4px; }
				.footer { text-align: center; margin-top: 20px; color: #999; font-size: 12px; }
				.button { display: inline-block; padding: 12px 24px; background: #667eea; color: #fff; text-decoration: none; border-radius: 6px; margin-top: 15px; }
			</style>
		</head>
		<body>
			<div class="container">
				<div class="header">
					<h2><?php echo esc_html( $subject ); ?></h2>
				</div>
				<div class="content">
					<?php if ( ! empty( $context['listing_title'] ) ) : ?>
						<p><strong><?php esc_html_e( 'Listing:', 'directorist-smart-assistant' ); ?></strong> <?php echo esc_html( $context['listing_title'] ); ?></p>
					<?php endif; ?>

					<div class="message">
						<p><?php echo nl2br( esc_html( $message ) ); ?></p>
					</div>

					<?php if ( ! empty( $context['listing_url'] ) ) : ?>
						<a href="<?php echo esc_url( $context['listing_url'] ); ?>" class="button">
							<?php esc_html_e( 'View Listing', 'directorist-smart-assistant' ); ?>
						</a>
					<?php endif; ?>
				</div>
				<div class="footer">
					<p>
						<?php
						printf(
							/* translators: %s: Site name */
							esc_html__( 'Sent from %s', 'directorist-smart-assistant' ),
							esc_html( $context['site_name'] ?? get_bloginfo( 'name' ) )
						);
						?>
					</p>
				</div>
			</div>
		</body>
		</html>
		<?php
		return ob_get_clean();
	}
}
