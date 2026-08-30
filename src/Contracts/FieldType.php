<?php
/**
 * Field type contract.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Contracts;

use Bindery\Fields\FieldDefinition;

/**
 * A field type owns how a kind of value is sanitised, normalised and rendered.
 *
 * Field types are render strategies: `text`, `richtext`, `url`, `image`,
 * `number`, `repeater`, … Extensions register their own via the
 * `bindery/register_field_types` action.
 */
interface FieldType {

	/**
	 * Stable identifier, e.g. "text".
	 */
	public function id(): string;

	/**
	 * Sanitise an incoming value before persistence.
	 */
	public function sanitize( mixed $value ): mixed;

	/**
	 * Normalise a developer-supplied default into this type's canonical form.
	 */
	public function normalizeDefault( mixed $default ): mixed;

	/**
	 * Render a resolved value to safe, output-ready HTML.
	 */
	public function render( mixed $value, FieldDefinition $definition ): string;
}
