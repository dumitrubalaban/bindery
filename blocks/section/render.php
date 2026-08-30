<?php
/**
 * Server render for bindery/section.
 *
 * Wraps the inner blocks ($content) in a full-width section with a per-locale
 * background image (resolved from the Bindery store) and a darkening overlay.
 * Provided by WordPress: $attributes, $content, $block.
 *
 * @package Bindery
 */

declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}


/** @var array<string, mixed> $attributes */
/** @var string $content */
/** @var \WP_Block $block */

$bindery_overlay    = isset( $attributes['overlay'] ) ? max( 0, min( 100, (int) $attributes['overlay'] ) ) : 45;
$bindery_min_height = isset( $attributes['minHeight'] ) ? preg_replace( '/[^0-9a-z%.]/i', '', (string) $attributes['minHeight'] ) : '420px';
$bindery_key        = isset( $attributes['fieldKey'] ) ? (string) $attributes['fieldKey'] : '';

$bindery_object_id = ( isset( $block ) && isset( $block->context['postId'] ) )
	? (int) $block->context['postId']
	: null;

$bindery_bg = '';
if ( '' !== $bindery_key && function_exists( 'bindery_value' ) ) {
	$bindery_img_id = bindery_value( $bindery_key, array( 'type' => 'image', 'default' => 0 ), $bindery_object_id );
	if ( is_numeric( $bindery_img_id ) && (int) $bindery_img_id > 0 ) {
		$bindery_bg = (string) wp_get_attachment_image_url( (int) $bindery_img_id, 'full' );
	}
}

$bindery_style = 'min-height:' . $bindery_min_height . ';';
if ( '' !== $bindery_bg ) {
	$bindery_style .= 'background-image:url(' . esc_url( $bindery_bg ) . ');';
}

$bindery_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'bindery-section',
		'style' => $bindery_style,
	)
);

printf(
	'<div %1$s><div class="bindery-section__overlay" style="opacity:%2$s"></div><div class="bindery-section__inner">%3$s</div></div>',
	$bindery_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped wrapper.
	esc_attr( (string) ( $bindery_overlay / 100 ) ),
	$content // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- inner blocks are already rendered + escaped by WP.
);
