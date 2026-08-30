<?php
/**
 * Field resolution context.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Fields;

/**
 * The runtime coordinates a value is resolved against: which object and which
 * locale. An empty locale means the field is not locale-aware.
 */
final readonly class FieldContext {

	public function __construct(
		public int $objectId,
		public string $locale = ''
	) {
	}
}
