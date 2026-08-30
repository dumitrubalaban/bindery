<?php
/**
 * Number field type.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Fields\Types;

use Bindery\Contracts\FieldType;
use Bindery\Fields\FieldDefinition;

/**
 * A numeric value (int or float). Rendered as escaped text, optionally wrapped
 * in a tag.
 */
final class NumberField implements FieldType {

	public function id(): string {
		return 'number';
	}

	public function sanitize( mixed $value ): int|float {
		return $this->normalizeDefault( $value );
	}

	public function normalizeDefault( mixed $default ): int|float {
		if ( ! is_numeric( $default ) ) {
			return 0;
		}

		$number = $default + 0;

		return is_int( $number ) ? $number : (float) $number;
	}

	public function render( mixed $value, FieldDefinition $definition ): string {
		$number = is_numeric( $value ) ? (string) ( $value + 0 ) : '0';
		$tag    = $definition->tag;

		if ( null === $tag || '' === $tag ) {
			return esc_html( $number );
		}

		return sprintf(
			'<%1$s%3$s>%2$s</%1$s>',
			tag_escape( $tag ),
			esc_html( $number ),
			$definition->renderAttributes()
		);
	}
}
