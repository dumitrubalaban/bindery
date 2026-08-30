<?php
/**
 * REST controller for public form submissions.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Rest;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;

/**
 * Handles `bindery/v1/submit`: a public endpoint protected by a honeypot, a
 * nonce and a per-IP rate limit. Valid submissions are stored as a
 * `bindery_submission` post and emailed to the site admin.
 */
final class FormController {

	private const NAMESPACE   = 'bindery/v1';
	public const POST_TYPE    = 'bindery_submission';
	private const NONCE       = 'bindery_form';
	private const RATE_MAX    = 5;
	private const RATE_WINDOW = 600; // 10 minutes.

	public function registerRoutes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/submit',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'submit' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	public function submit( WP_REST_Request $request ): WP_REST_Response {
		// Honeypot: bots fill the hidden "company" field. Pretend success.
		if ( '' !== trim( (string) $request->get_param( 'company' ) ) ) {
			return rest_ensure_response( array( 'ok' => true ) );
		}

		if ( ! wp_verify_nonce( (string) $request->get_param( 'nonce' ), self::NONCE ) ) {
			return new WP_REST_Response(
				array(
					'ok'    => false,
					'error' => __( 'Security check failed — please reload the page and try again.', 'bindery' ),
				),
				403
			);
		}

		if ( $this->rateLimited() ) {
			return new WP_REST_Response(
				array(
					'ok'    => false,
					'error' => __( 'Too many submissions. Please try again later.', 'bindery' ),
				),
				429
			);
		}

		$name    = sanitize_text_field( (string) $request->get_param( 'name' ) );
		$email   = sanitize_email( (string) $request->get_param( 'email' ) );
		$message = sanitize_textarea_field( (string) $request->get_param( 'message' ) );
		$source  = absint( $request->get_param( 'post' ) );

		$errors = array();
		if ( '' === $name ) {
			$errors['name'] = __( 'Please enter your name.', 'bindery' );
		}
		if ( ! is_email( $email ) ) {
			$errors['email'] = __( 'Please enter a valid email.', 'bindery' );
		}
		if ( '' === $message ) {
			$errors['message'] = __( 'Please enter a message.', 'bindery' );
		}
		if ( array() !== $errors ) {
			return new WP_REST_Response(
				array(
					'ok'     => false,
					'errors' => $errors,
				),
				422
			);
		}

		$submission_id = wp_insert_post(
			array(
				'post_type'    => self::POST_TYPE,
				'post_status'  => 'private',
				// translators: %1$s name, %2$s email.
				'post_title'   => sprintf( '%1$s <%2$s>', $name, $email ),
				'post_content' => $message,
			),
			true
		);

		if ( ! is_wp_error( $submission_id ) ) {
			update_post_meta( $submission_id, '_bindery_name', $name );
			update_post_meta( $submission_id, '_bindery_email', $email );
			update_post_meta( $submission_id, '_bindery_source', $source );

			/**
			 * Fires after a form submission is stored.
			 *
			 * @param int   $submission_id The submission post id.
			 * @param array $data          name, email, message, source.
			 */
			do_action( 'bindery/form_submitted', $submission_id, compact( 'name', 'email', 'message', 'source' ) );
		}

		$recipient = (string) apply_filters( 'bindery/form_recipient', get_option( 'admin_email' ), $source );
		// translators: %s submitter name.
		$subject = sprintf( __( 'New form submission from %s', 'bindery' ), $name );
		$body    = sprintf( "Name: %s\nEmail: %s\n\n%s", $name, $email, $message );
		wp_mail( $recipient, $subject, $body, array( 'Reply-To: ' . $name . ' <' . $email . '>' ) );

		$this->bumpRate();

		return rest_ensure_response( array( 'ok' => true ) );
	}

	private function rateKey(): string {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( (string) $_SERVER['REMOTE_ADDR'] ) ) : 'unknown';
		return 'bindery_rl_' . md5( $ip );
	}

	private function rateLimited(): bool {
		return (int) get_transient( $this->rateKey() ) >= self::RATE_MAX;
	}

	private function bumpRate(): void {
		$key   = $this->rateKey();
		$count = (int) get_transient( $key );
		set_transient( $key, $count + 1, self::RATE_WINDOW );
	}
}
