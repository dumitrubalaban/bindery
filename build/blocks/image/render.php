<?php
/**
 * Server render for bindery/image.
 *
 * Resolves the per-locale attachment id from the Bindery store and outputs the
 * image. Provided by WordPress: $attributes, $content, $block.
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

if ( '' === $bindery_key || ! function_exists( 'bindery_get_field' ) ) {
	return;
}

$bindery_size = isset( $attributes['size'] ) ? (string) $attributes['size'] : 'large';
$bindery_alt  = isset( $attributes['alt'] ) ? (string) $attributes['alt'] : '';

$bindery_object_id = ( isset( $block ) && isset( $block->context['postId'] ) )
	? (int) $block->context['postId']
	: null;

$bindery_html = bindery_get_field(
	$bindery_key,
	array(
		'type' => 'image',
		'size' => $bindery_size,
		'alt'  => $bindery_alt,
	),
	$bindery_object_id
);

if ( '' === trim( $bindery_html ) ) {
	return;
}

$bindery_wrapper = get_block_wrapper_attributes( array( 'class' => 'bindery-image' ) );

printf(
	'<figure %s>%s</figure>',
	$bindery_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped wrapper.
	$bindery_html     // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- wp_get_attachment_image output.
);
