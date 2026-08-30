<?php
/**
 * Field type registry.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Fields;

use Bindery\Contracts\FieldType;

/**
 * Holds the available field types keyed by id. Built-ins are registered at boot;
 * extensions add their own via the `bindery/register_field_types` action.
 */
final class FieldTypeRegistry {

	/**
	 * @var array<string, FieldType>
	 */
	private array $types = array();

	public function register( FieldType $type ): void {
		$this->types[ $type->id() ] = $type;
	}

	public function get( string $id ): ?FieldType {
		return $this->types[ $id ] ?? null;
	}

	public function has( string $id ): bool {
		return isset( $this->types[ $id ] );
	}

	/**
	 * @return array<string, FieldType>
	 */
	public function all(): array {
		return $this->types;
	}
}
