<?php
/**
 * Callback value source.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Sources;

use Bindery\Contracts\ValueSource;
use Bindery\Fields\FieldContext;
use Bindery\Fields\FieldDefinition;

/**
 * Resolves a value from a developer-supplied callable in `args['callback']`,
 * invoked as `fn( FieldDefinition $definition, FieldContext $context ): mixed`.
 * This is the escape hatch for dynamic data (WooCommerce, an API, a computed
 * value) without writing a full source class.
 */
final class CallbackSource implements ValueSource {

	public function id(): string {
		return 'callback';
	}

	public function resolve( FieldDefinition $definition, FieldContext $context ): mixed {
		$callback = $definition->arg( 'callback' );

		// A Closure can only originate from server-side PHP — block-binding metadata
		// (JSON) cannot carry one — so it is trusted and run as-is. Any other form
		// (a string or array "callable") could arrive from untrusted metadata, so it
		// must be explicitly registered; we never invoke an arbitrary named callable.
		if ( ! $callback instanceof \Closure ) {
			/**
			 * Registry of named callbacks this source may invoke, as `name => callable`.
			 *
			 * @param array<string, callable> $registry Registered callbacks.
			 */
			$registry = (array) apply_filters( 'bindery/callback_sources', array() );
			$name     = is_string( $callback ) ? $callback : '';
			$callback = ( '' !== $name && isset( $registry[ $name ] ) ) ? $registry[ $name ] : null;
		}

		if ( ! is_callable( $callback ) ) {
			return null;
		}

		return $callback( $definition, $context );
	}
}
