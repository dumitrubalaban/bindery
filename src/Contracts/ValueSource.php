<?php
/**
 * Value source contract.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Contracts;

use Bindery\Fields\FieldContext;
use Bindery\Fields\FieldDefinition;

/**
 * A value source resolves where a field's stored override comes from.
 *
 * The default `stored` source reads Bindery's own table. Integrations register
 * additional sources (`option`, `callback`, `acf`, `woocommerce`, …) via the
 * `bindery/register_sources` action. A source returns `null` to signal "no
 * override" so the resolver can fall back to the code default.
 */
interface ValueSource {

	/**
	 * Stable identifier, e.g. "stored".
	 */
	public function id(): string;

	/**
	 * Resolve the stored override, or null when none exists.
	 */
	public function resolve( FieldDefinition $definition, FieldContext $context ): mixed;
}
