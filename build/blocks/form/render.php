<?php
/**
 * Server render for bindery/form.
 *
 * Outputs a contact form with per-locale labels (from the Bindery store), a
 * honeypot, a nonce and the REST endpoint. view.js submits it via fetch.
 * Provided by WordPress: $attributes, $content, $block.
 *
 * @package Bindery
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/** @var array<string, mixed> $attributes */
/** @var \WP_Block $block */

$bindery_key = isset( $attributes['fieldKey'] ) ? (string) $attributes['fieldKey'] : '';

if ( '' === $bindery_key || ! function_exists( 'bindery_value' ) ) {
	return;
}

$bindery_object_id = ( isset( $block ) && isset( $block->context['postId'] ) )
	? (int) $block->context['postId']
	: null;

$bindery_label = static function ( string $suffix, string $default ) use ( $bindery_key, $bindery_object_id ): string {
	$value = bindery_value( $bindery_key . $suffix, array( 'type' => 'text', 'default' => $default ), $bindery_object_id );
	return is_scalar( $value ) ? (string) $value : $default;
};

$bindery_title   = $bindery_label( '_title', __( 'Get in touch', 'bindery' ) );
$bindery_l_name  = $bindery_label( '_name', __( 'Name', 'bindery' ) );
$bindery_l_email = $bindery_label( '_email', __( 'Email', 'bindery' ) );
$bindery_l_msg   = $bindery_label( '_message', __( 'Message', 'bindery' ) );
$bindery_submit  = $bindery_label( '_submit', __( 'Send', 'bindery' ) );
$bindery_success = $bindery_label( '_success', __( 'Thanks! We will be in touch shortly.', 'bindery' ) );

$bindery_wrapper = get_block_wrapper_attributes(
	array(
		'class'        => 'bindery-form',
		'data-rest'    => esc_url_raw( rest_url( 'bindery/v1/submit' ) ),
		'data-wpnonce' => wp_create_nonce( 'wp_rest' ),
		'data-success' => $bindery_success,
	)
);

?>
<form <?php echo $bindery_wrapper; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped wrapper. ?> method="post" novalidate>
	<h3 class="bindery-form__title"><?php echo esc_html( $bindery_title ); ?></h3>
	<label class="bindery-form__field">
		<span><?php echo esc_html( $bindery_l_name ); ?></span>
		<input type="text" name="name" required>
	</label>
	<label class="bindery-form__field">
		<span><?php echo esc_html( $bindery_l_email ); ?></span>
		<input type="email" name="email" required>
	</label>
	<label class="bindery-form__field">
		<span><?php echo esc_html( $bindery_l_msg ); ?></span>
		<textarea name="message" rows="4" required></textarea>
	</label>
	<input type="text" name="company" class="bindery-form__hp" tabindex="-1" autocomplete="off" aria-hidden="true">
	<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'bindery_form' ) ); ?>">
	<input type="hidden" name="post" value="<?php echo esc_attr( (string) ( $bindery_object_id ?? 0 ) ); ?>">
	<button type="submit" class="bindery-form__submit bindery-button-link"><?php echo esc_html( $bindery_submit ); ?></button>
	<p class="bindery-form__message" role="status" hidden></p>
</form>
<?php
