<?php
/**
 * Value resolution (precedence engine).
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Fields;

use Bindery\Locale\LocaleManager;
use Bindery\Sources\SourceRegistry;

/**
 * Resolves a field to its effective value using the precedence rule:
 *
 *   stored override (for the current locale) ?? code default
 *
 * A stored value of `null` means "no override"; any other value — including an
 * empty string the client deliberately saved — wins over the code default. The
 * default itself is never persisted, so improving it in code reaches every site
 * that has not overridden the field.
 */
final class ValueResolver {

	public function __construct(
		private readonly SourceRegistry $sources,
		private readonly LocaleManager $locales
	) {
	}

	public function resolve( FieldDefinition $definition, ?int $object_id = null ): mixed {
		$object_id ??= $this->defaultObjectId( $definition );
		$locale      = $this->locales->localeFor( $definition );
		$context     = new FieldContext( $object_id, $locale );

		$source = $this->sources->get( $definition->source ) ?? $this->sources->get( 'stored' );
		$stored = $source?->resolve( $definition, $context );

		$value = null !== $stored ? $stored : $definition->default;

		/**
		 * Filters a resolved field value just before use.
		 *
		 * @param mixed           $value      The resolved value.
		 * @param FieldDefinition $definition The field definition.
		 * @param FieldContext    $context    Object id + locale it was resolved for.
		 */
		return apply_filters( 'bindery/resolve_value', $value, $definition, $context );
	}

	/**
	 * Default object id: the current post for page scope, 0 for global.
	 */
	private function defaultObjectId( FieldDefinition $definition ): int {
		if ( Scope::Global === $definition->scope ) {
			return 0;
		}

		$id = function_exists( 'get_the_ID' ) ? get_the_ID() : 0;

		return is_int( $id ) && $id > 0 ? $id : 0;
	}
}
