<?php
/**
 * Repeater field type.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Fields\Types;

use Bindery\Contracts\FieldType;
use Bindery\Fields\FieldDefinition;

/**
 * A list of rows, each an associative map of sub-field values. Sub-fields are
 * declared in `args['fields']` as `[ subKey => typeOrTag ]`.
 *
 * The primary consumption path is the `bindery_rows()` template helper, which
 * returns the raw rows for the theme to loop and escape with its own markup.
 * Deep, type-correct sanitisation on save lives in {@see \Bindery\Support\Sanitizer}
 * (it has the definition, and therefore the sub-field type map). This render()
 * is only a safe fallback for the rare case a repeater is bound directly.
 */
final class RepeaterField implements FieldType {

	public function id(): string {
		return 'repeater';
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function sanitize( mixed $value ): array {
		return $this->toRows( $value );
	}

	/**
	 * @return list<array<string, mixed>>
	 */
	public function normalizeDefault( mixed $default ): array {
		return $this->toRows( $default );
	}

	public function render( mixed $value, FieldDefinition $definition ): string {
		$rows = $this->toRows( $value );
		if ( array() === $rows ) {
			return '';
		}

		$items = '';
		foreach ( $rows as $row ) {
			$parts = array();
			foreach ( $row as $cell ) {
				if ( is_scalar( $cell ) ) {
					$parts[] = esc_html( (string) $cell );
				}
			}
			$items .= '<li>' . implode( ' ', $parts ) . '</li>';
		}

		return '<ul class="bindery-repeater">' . $items . '</ul>';
	}

	/**
	 * Coerce a loose value into a list of associative rows.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function toRows( mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		$rows = array();
		foreach ( $value as $row ) {
			if ( is_array( $row ) ) {
				$rows[] = $row;
			}
		}

		return $rows;
	}
}
