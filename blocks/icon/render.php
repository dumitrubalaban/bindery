<?php
/**
 * Server render for bindery/icon.
 *
 * Resolves the per-locale icon key from the Bindery store and outputs the
 * matching inline SVG from the shared icons.json. Provided by WordPress:
 * $attributes, $content, $block.
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

$bindery_icons_file = __DIR__ . '/icons.json';
$bindery_icons      = is_file( $bindery_icons_file ) ? json_decode( (string) file_get_contents( $bindery_icons_file ), true ) : array();
if ( ! is_array( $bindery_icons ) ) {
	return;
}

$bindery_object_id = ( isset( $block ) && isset( $block->context['postId'] ) )
	? (int) $block->context['postId']
	: null;

$bindery_name = bindery_value( $bindery_key, array( 'type' => 'text', 'default' => '' ), $bindery_object_id );
$bindery_name = is_string( $bindery_name ) ? $bindery_name : '';

if ( '' === $bindery_name || ! isset( $bindery_icons[ $bindery_name ] ) ) {
	return;
}

$bindery_size  = isset( $attributes['size'] ) ? max( 12, (int) $attributes['size'] ) : 48;
$bindery_color = isset( $attributes['color'] ) ? sanitize_hex_color( (string) $attributes['color'] ) : '#d8b75d';
$bindery_color = $bindery_color ?: '#d8b75d';

$bindery_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'bindery-icon',
		'style' => 'color:' . $bindery_color . ';',
	)
);

printf(
	'<span %1$s><svg width="%2$d" height="%2$d" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true" focusable="false"><path d="%3$s"></path></svg></span>',
	$bindery_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped wrapper.
	absint( $bindery_size ),
	esc_attr( (string) $bindery_icons[ $bindery_name ] )
);
