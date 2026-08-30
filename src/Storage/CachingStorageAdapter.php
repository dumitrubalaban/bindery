<?php
/**
 * Caching storage decorator.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Storage;

use Bindery\Contracts\StorageAdapter;

/**
 * Decorates any {@see StorageAdapter} to collapse the per-field read pattern
 * into one query per object.
 *
 * A front-end render calls {@see get()} once per field; on a page with dozens of
 * bound blocks that is dozens of round-trips. This decorator primes a whole
 * object's values with a single {@see all()} call on first access, then serves
 * every subsequent field from memory. The primed map is also stored in the WP
 * object cache (group {@see self::GROUP}), so on installs with a persistent
 * backend (Redis/Memcached) the object survives across requests; on the default
 * non-persistent cache it simply mirrors the request-local map at no cost.
 *
 * Writes are forwarded to the inner adapter and the affected object is purged
 * from both caches, so the next read re-primes from the source of truth.
 */
final class CachingStorageAdapter implements StorageAdapter {

	private const GROUP = 'bindery_values';

	/**
	 * Request-local primed maps, keyed by object id: [ key ][ locale ] => value.
	 *
	 * @var array<int, array<string, array<string, mixed>>>
	 */
	private array $primed = array();

	public function __construct(
		private readonly StorageAdapter $inner
	) {
	}

	public function get( int $object_id, string $key, string $locale ): mixed {
		$map = $this->map( $object_id );

		// A primed map is authoritative: a missing key means "no stored override",
		// which resolves to null without ever touching the database again.
		return $map[ $key ][ $locale ] ?? null;
	}

	public function set( int $object_id, string $key, string $locale, mixed $value ): void {
		$this->inner->set( $object_id, $key, $locale, $value );
		$this->forget( $object_id );
	}

	public function delete( int $object_id, string $key, string $locale ): void {
		$this->inner->delete( $object_id, $key, $locale );
		$this->forget( $object_id );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function all( int $object_id ): array {
		return $this->map( $object_id );
	}

	/**
	 * Resolve (and cache) the full value map for an object: request-local first,
	 * then object cache, finally a single query through the inner adapter.
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function map( int $object_id ): array {
		if ( isset( $this->primed[ $object_id ] ) ) {
			return $this->primed[ $object_id ];
		}

		// A primed map is always an array (possibly empty); the object cache
		// returns false only on a miss, so is_array() cleanly tells hit from miss
		// without needing the by-reference $found out-parameter.
		$cached = wp_cache_get( (string) $object_id, self::GROUP );

		if ( is_array( $cached ) ) {
			/** @var array<string, array<string, mixed>> $cached */
			$this->primed[ $object_id ] = $cached;

			return $cached;
		}

		$map                        = $this->inner->all( $object_id );
		$this->primed[ $object_id ] = $map;
		wp_cache_set( (string) $object_id, $map, self::GROUP );

		return $map;
	}

	/**
	 * Drop one object from both the request-local map and the object cache.
	 */
	private function forget( int $object_id ): void {
		unset( $this->primed[ $object_id ] );
		wp_cache_delete( (string) $object_id, self::GROUP );
	}
}
