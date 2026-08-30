<?php
/**
 * Declared-field registry.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Fields;

/**
 * Records every field a theme has declared this request, keyed by field key.
 *
 * The editor and the block-bindings source read this registry to know which
 * regions exist, their types, defaults and capabilities.
 */
final class FieldRegistry {

	/**
	 * @var array<string, FieldDefinition>
	 */
	private array $fields = array();

	/**
	 * Register (or replace) a definition; returns it for fluent use.
	 */
	public function register( FieldDefinition $definition ): FieldDefinition {
		$this->fields[ $definition->key ] = $definition;

		return $definition;
	}

	public function get( string $key ): ?FieldDefinition {
		return $this->fields[ $key ] ?? null;
	}

	public function has( string $key ): bool {
		return isset( $this->fields[ $key ] );
	}

	/**
	 * @return array<string, FieldDefinition>
	 */
	public function all(): array {
		return $this->fields;
	}
}
