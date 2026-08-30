<?php
/**
 * Field value sanitiser.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Support;

use Bindery\Fields\FieldDefinition;
use Bindery\Fields\FieldDefinitionFactory;
use Bindery\Fields\FieldTypeRegistry;

/**
 * Sanitises an incoming value according to its field type. Used on save (REST)
 * so persistence is always type-correct regardless of the request source.
 *
 * Repeaters are handled here (not in the field type) because deep sanitisation
 * needs the definition's sub-field type map, which the {@see \Bindery\Contracts\FieldType}
 * sanitise signature does not carry.
 */
final class Sanitizer {

	public function __construct(
		private readonly FieldTypeRegistry $types
	) {
	}

	public function sanitize( FieldDefinition $definition, mixed $value ): mixed {
		if ( 'repeater' === $definition->type ) {
			return $this->sanitizeRepeater( $definition, $value );
		}

		$type = $this->types->get( $definition->type ) ?? $this->types->get( 'text' );

		return null === $type ? null : $type->sanitize( $value );
	}

	/**
	 * Sanitise each row's sub-fields by their declared sub-field type.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function sanitizeRepeater( FieldDefinition $definition, mixed $value ): array {
		if ( ! is_array( $value ) ) {
			return array();
		}

		/** @var array<string, mixed> $subfields */
		$subfields = (array) $definition->arg( 'fields', array() );
		$fallback  = $this->types->get( 'text' );
		$rows      = array();

		foreach ( $value as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$clean = array();
			foreach ( $subfields as $sub_key => $spec ) {
				$spec_type = is_array( $spec ) ? (string) ( $spec['type'] ?? 'text' ) : (string) $spec;
				$type      = $this->types->get( FieldDefinitionFactory::canonicalType( $spec_type ) ) ?? $fallback;

				$clean[ (string) $sub_key ] = null === $type ? null : $type->sanitize( $row[ $sub_key ] ?? null );
			}

			$rows[] = $clean;
		}

		return $rows;
	}
}
