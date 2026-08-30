<?php
/**
 * Field renderer.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Fields;

/**
 * Resolves a field's value then delegates to its field type for safe rendering.
 * The returned HTML is output-ready (escaped by the field type).
 */
final class FieldRenderer {

	public function __construct(
		private readonly FieldTypeRegistry $types,
		private readonly ValueResolver $resolver
	) {
	}

	public function render( FieldDefinition $definition, ?int $object_id = null ): string {
		$value = $this->resolver->resolve( $definition, $object_id );
		$type  = $this->types->get( $definition->type ) ?? $this->types->get( 'text' );

		if ( null === $type ) {
			return '';
		}

		return $type->render( $value, $definition );
	}
}
