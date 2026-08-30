<?php
/**
 * Field scope.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Fields;

/**
 * Where a field's value lives.
 *
 * Page-scoped values belong to a single post/object; global values are
 * site-wide (object_id 0). The scope also decides the default object id used
 * during resolution.
 */
enum Scope: string {

	case Page   = 'page';
	case Global = 'global';

	/**
	 * Coerce a loose value (string|enum|null) into a Scope, defaulting to Page.
	 */
	public static function fromLoose( string|self|null $value ): self {
		if ( $value instanceof self ) {
			return $value;
		}

		return self::tryFrom( (string) $value ) ?? self::Page;
	}
}
