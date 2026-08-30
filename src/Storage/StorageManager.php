<?php
/**
 * Storage manager.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Storage;

use Bindery\Contracts\StorageAdapter;

/**
 * Thin facade over the active {@see StorageAdapter}. The adapter is chosen at
 * boot (table by default) and swappable via the `bindery/storage_adapter`
 * filter, so callers never depend on a concrete backend.
 */
final class StorageManager {

	public function __construct(
		private readonly StorageAdapter $adapter
	) {
	}

	public function get( int $object_id, string $key, string $locale ): mixed {
		return $this->adapter->get( $object_id, $key, $locale );
	}

	public function set( int $object_id, string $key, string $locale, mixed $value ): void {
		$this->adapter->set( $object_id, $key, $locale, $value );
	}

	public function delete( int $object_id, string $key, string $locale ): void {
		$this->adapter->delete( $object_id, $key, $locale );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function all( int $object_id ): array {
		return $this->adapter->all( $object_id );
	}

	public function adapter(): StorageAdapter {
		return $this->adapter;
	}
}
