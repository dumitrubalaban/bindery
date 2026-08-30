<?php
/**
 * Storage adapter contract.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Contracts;

/**
 * Persists per-(object, key, locale) field values.
 *
 * The default {@see \Bindery\Storage\TableStorageAdapter} uses a custom table;
 * {@see \Bindery\Storage\MetaStorageAdapter} is a post-meta/options alternative.
 * Swap via the `bindery/storage_adapter` filter. Values round-trip as their
 * native PHP type (string, int, array …); a missing value resolves to `null`.
 */
interface StorageAdapter {

	/**
	 * Read a stored value, or null when absent.
	 */
	public function get( int $object_id, string $key, string $locale ): mixed;

	/**
	 * Write (insert or replace) a stored value.
	 */
	public function set( int $object_id, string $key, string $locale, mixed $value ): void;

	/**
	 * Remove a stored value.
	 */
	public function delete( int $object_id, string $key, string $locale ): void;

	/**
	 * All stored values for an object, shaped as [ key ][ locale ] => value.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function all( int $object_id ): array;
}
