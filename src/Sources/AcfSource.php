<?php
/**
 * ACF value source (optional integration stub).
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Sources;

use Bindery\Contracts\ValueSource;
use Bindery\Fields\FieldContext;
use Bindery\Fields\FieldDefinition;
use Bindery\Fields\Scope;

/**
 * Resolves a value from Advanced Custom Fields when ACF is active. The ACF field
 * name comes from `args['acf_field']`, defaulting to the Bindery key. Global
 * fields read from the ACF "option" store; page fields from the object id.
 *
 * Degrades gracefully: if ACF is not installed, returns null so the code default
 * still applies. This is the reference pattern for "repeater from ACF" — an
 * optional adapter, never a hard dependency.
 */
final class AcfSource implements ValueSource {

	public function id(): string {
		return 'acf';
	}

	public function resolve( FieldDefinition $definition, FieldContext $context ): mixed {
		if ( ! function_exists( 'get_field' ) ) {
			return null;
		}

		$name = (string) $definition->arg( 'acf_field', $definition->key );

		if ( $context->objectId > 0 ) {
			$target = $context->objectId;
		} elseif ( Scope::Global === $definition->scope ) {
			$target = 'option';
		} else {
			$target = false;
		}

		$value = false !== $target ? get_field( $name, $target ) : get_field( $name );

		return ( false === $value || null === $value ) ? null : $value;
	}
}
