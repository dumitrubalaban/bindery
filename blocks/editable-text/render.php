<?php
/**
 * Server render for bindery/editable-text.
 *
 * Provided by WordPress: $attributes, $content, $block (WP_Block).
 *
 * The block class is placed on the tag itself (not a wrapping div) so the
 * front-end DOM is identical to the editor's RichText element — letting one
 * shared stylesheet style both contexts the same way.
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

$bindery_tag     = isset( $attributes['tag'] ) ? (string) $attributes['tag'] : 'p';
$bindery_default = isset( $attributes['placeholder'] ) ? (string) $attributes['placeholder'] : '';

$bindery_object_id = ( isset( $block ) && isset( $block->context['postId'] ) )
	? (int) $block->context['postId']
	: null;

$bindery_value = bindery_value(
	$bindery_key,
	array(
		'type'    => $bindery_tag,
		'default' => $bindery_default,
	),
	$bindery_object_id
);

$bindery_text  = is_scalar( $bindery_value ) ? (string) $bindery_value : '';
$bindery_empty = ( '' === trim( $bindery_text ) ) ? '1' : '0';

$bindery_attrs = array( 'data-bindery-empty' => $bindery_empty );

$bindery_locked = ! empty( $attributes['locked'] );

// Expose the field + object to the front-end edit overlay, but only to users
// who can edit (so field keys aren't leaked to anonymous visitors) and only for
// fields the developer left unlocked. Locked fields stay visible but uneditable,
// mirroring the in-editor lock — the overlay never sees them as editable regions.
if ( ! $bindery_locked && current_user_can( 'bindery_edit_content' ) ) {
	$bindery_attrs['data-bindery-field']  = $bindery_key;
	$bindery_attrs['data-bindery-object'] = (string) ( $bindery_object_id ?? ( get_the_ID() ? (int) get_the_ID() : 0 ) );
}

$bindery_wrapper = get_block_wrapper_attributes( $bindery_attrs );

printf(
	'<%1$s %2$s>%3$s</%1$s>',
	tag_escape( $bindery_tag ),
	$bindery_wrapper, // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped wrapper attributes.
	esc_html( $bindery_text )
);
