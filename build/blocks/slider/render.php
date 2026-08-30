<?php
/**
 * Server render for bindery/slider.
 *
 * Outputs the slides (a Bindery repeater, resolved per locale) plus nav + dots;
 * the block's view.js turns it into an auto-advancing carousel on the front end.
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
	: array( 'title' => 'h3', 'text' => 'p' );

$bindery_autoplay = ! empty( $attributes['autoplay'] );
$bindery_interval = isset( $attributes['interval'] ) ? max( 1500, (int) $attributes['interval'] ) : 5000;

$bindery_object_id = ( isset( $block ) && isset( $block->context['postId'] ) )
	? (int) $block->context['postId']
	: null;

$bindery_rows = bindery_rows( $bindery_key, array( 'fields' => $bindery_subfields ), $bindery_object_id );

if ( array() === $bindery_rows ) {
	return;
}

$bindery_wrapper = get_block_wrapper_attributes(
	array(
		'class'         => 'bindery-slider',
		'data-autoplay' => $bindery_autoplay ? '1' : '0',
		'data-interval' => (string) $bindery_interval,
	)
);

echo '<div ' . $bindery_wrapper . '>'; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core-escaped wrapper.
echo '<div class="bindery-slider__viewport"><div class="bindery-slider__track">';

foreach ( $bindery_rows as $bindery_row ) {
	echo '<div class="bindery-slide">';
	foreach ( $bindery_subfields as $bindery_sub => $bindery_tag ) {
		$bindery_tag_name = preg_replace( '/[^a-z0-9]/i', '', (string) $bindery_tag );
		$bindery_tag_name = '' !== $bindery_tag_name ? $bindery_tag_name : 'p';
		$bindery_val      = ( isset( $bindery_row[ $bindery_sub ] ) && is_scalar( $bindery_row[ $bindery_sub ] ) )
			? (string) $bindery_row[ $bindery_sub ]
			: '';
		printf(
			'<%1$s class="bindery-slide__%2$s">%3$s</%1$s>',
			tag_escape( $bindery_tag_name ),
			esc_attr( (string) $bindery_sub ),
			esc_html( $bindery_val )
		);
	}
	echo '</div>';
}

echo '</div></div>';

echo '<button class="bindery-slider__nav bindery-slider__prev" type="button" aria-label="' . esc_attr__( 'Previous slide', 'bindery' ) . '">&lsaquo;</button>';
echo '<button class="bindery-slider__nav bindery-slider__next" type="button" aria-label="' . esc_attr__( 'Next slide', 'bindery' ) . '">&rsaquo;</button>';

echo '<div class="bindery-slider__dots">';
foreach ( array_keys( $bindery_rows ) as $bindery_i ) {
	printf(
		'<button class="bindery-slider__dot%s" type="button" data-index="%d" aria-label="%s"></button>',
		0 === $bindery_i ? ' is-active' : '',
		(int) $bindery_i,
		esc_attr( sprintf( /* translators: %d: slide number */ __( 'Go to slide %d', 'bindery' ), (int) $bindery_i + 1 ) )
	);
}
echo '</div>';

echo '</div>';
