<?php
/**
 * Rich-text (limited HTML) field type.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Fields\Types;

use Bindery\Contracts\FieldType;
use Bindery\Fields\FieldDefinition;

/**
 * Multi-line content allowing the standard post HTML subset. Rendered inside a
 * wrapper element (default `div`).
 */
final class RichTextField implements FieldType {

	public function id(): string {
		return 'richtext';
	}

	public function sanitize( mixed $value ): string {
		return wp_kses_post( (string) ( is_scalar( $value ) ? $value : '' ) );
	}

	public function normalizeDefault( mixed $default ): string {
		return is_scalar( $default ) ? (string) $default : '';
	}

	public function render( mixed $value, FieldDefinition $definition ): string {
		$html = wp_kses_post( (string) ( is_scalar( $value ) ? $value : '' ) );
		$tag  = $definition->tag ?? 'div';

		if ( '' === $tag ) {
			return $html;
		}

		return sprintf(
			'<%1$s%3$s>%2$s</%1$s>',
			tag_escape( $tag ),
			$html,
			$definition->renderAttributes()
		);
	}
}
