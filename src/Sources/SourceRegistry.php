<?php
/**
 * Value source registry.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Sources;

use Bindery\Contracts\ValueSource;

/**
 * Holds the available value sources keyed by id.
 */
final class SourceRegistry {

	/**
	 * @var array<string, ValueSource>
	 */
	private array $sources = array();

	public function register( ValueSource $source ): void {
		$this->sources[ $source->id() ] = $source;
	}

	public function get( string $id ): ?ValueSource {
		return $this->sources[ $id ] ?? null;
	}

	public function has( string $id ): bool {
		return isset( $this->sources[ $id ] );
	}

	/**
	 * @return array<string, ValueSource>
	 */
	public function all(): array {
		return $this->sources;
	}
}
