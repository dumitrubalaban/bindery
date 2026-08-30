<?php
/**
 * Server render for bindery/button.
 *
 * Resolves the per-locale label ({key}) and URL ({key}__url) from the Bindery
 * store and outputs a real anchor. Provided by WordPress: $attributes, $block.
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

$bindery_label = bindery_value( $bindery_key, array( 'type' => 'text', 'default' => '' ), $bindery_object_id );
$bindery_url   = bindery_value( $bindery_key . '__url', array( 'type' => 'url', 'default' => '' ), $bindery_object_id );

$bindery_label = is_scalar( $bindery_label ) ? (string) $bindery_label : '';
$bindery_url   = is_scalar( $bindery_url ) ? (string) $bindery_url : '';

if ( '' === trim( $bindery_label ) ) {
	return;
}

$bindery_wrapper = get_block_wrapper_attributes( array( 'class' => 'bindery-button-block' ) );

printf(
	'<div %s><a class="bindery-button-link" href="%s">%s</a></div>',
	$bindery_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped wrapper.
	esc_url( '' !== $bindery_url ? $bindery_url : '#' ),
	esc_html( $bindery_label )
);
