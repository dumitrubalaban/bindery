<?php
/**
 * Server render for bindery/cards.
 *
 * Renders a grid of cards from a Bindery repeater field (resolved per locale).
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

if ( '' === $bindery_key || ! function_exists( 'bindery_rows' ) ) {
	return;
}

$bindery_subfields = ( isset( $attributes['subfields'] ) && is_array( $attributes['subfields'] ) )
	? $attributes['subfields']
	: array( 'title' => 'h4', 'body' => 'p' );

$bindery_cols = isset( $attributes['columns'] ) ? max( 1, (int) $attributes['columns'] ) : 3;

$bindery_object_id = ( isset( $block ) && isset( $block->context['postId'] ) )
	? (int) $block->context['postId']
	: null;

$bindery_rows = bindery_rows( $bindery_key, array( 'fields' => $bindery_subfields ), $bindery_object_id );

$bindery_wrapper = get_block_wrapper_attributes(
	array(
		'class' => 'bindery-cards',
		'style' => '--bindery-cols:' . $bindery_cols,
	)
);

echo '<div ' . $bindery_wrapper . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped wrapper.

foreach ( $bindery_rows as $bindery_row ) {
	echo '<div class="bindery-card">';
	foreach ( $bindery_subfields as $bindery_sub => $bindery_tag ) {
		$bindery_tag_name = preg_replace( '/[^a-z0-9]/i', '', (string) $bindery_tag );
		$bindery_tag_name = '' !== $bindery_tag_name ? $bindery_tag_name : 'p';
		$bindery_val      = ( isset( $bindery_row[ $bindery_sub ] ) && is_scalar( $bindery_row[ $bindery_sub ] ) )
			? (string) $bindery_row[ $bindery_sub ]
			: '';
		printf(
			'<%1$s class="bindery-card__%2$s">%3$s</%1$s>',
			tag_escape( $bindery_tag_name ),
			esc_attr( (string) $bindery_sub ),
			esc_html( $bindery_val )
		);
	}
	echo '</div>';
}

echo '</div>';
