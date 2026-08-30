<?php
/**
 * Plain-text field type.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Fields\Types;

use Bindery\Contracts\FieldType;
use Bindery\Fields\FieldDefinition;

/**
 * Single-line plain text, optionally wrapped in a developer-chosen HTML tag
 * (h1–h6, p, span, …). This is the type the `['type' => 'h1']` shorthand maps to.
 */
final class TextField implements FieldType {

	public function id(): string {
		return 'text';
	}

	public function sanitize( mixed $value ): string {
		return sanitize_text_field( (string) ( is_scalar( $value ) ? $value : '' ) );
	}

	public function normalizeDefault( mixed $default ): string {
		return is_scalar( $default ) ? (string) $default : '';
	}

	public function render( mixed $value, FieldDefinition $definition ): string {
		$text = is_scalar( $value ) ? (string) $value : '';
		$tag  = $definition->tag;

		if ( null === $tag || '' === $tag ) {
			return esc_html( $text );
		}

		return sprintf(
			'<%1$s%3$s>%2$s</%1$s>',
			tag_escape( $tag ),
			esc_html( $text ),
			$definition->renderAttributes()
		);
	}
}
