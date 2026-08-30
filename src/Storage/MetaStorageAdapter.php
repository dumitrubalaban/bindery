<?php
/**
 * Post-meta / options storage adapter (alternative backend).
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Storage;

use Bindery\Contracts\StorageAdapter;

/**
 * A dependency-free alternative to {@see TableStorageAdapter} for sites that
 * prefer core storage. Page-scoped values live in post meta keyed
 * `_bindery_{key}` as a [ locale => value ] map; global values live in a single
 * `bindery_global_values` option. Selected via the `bindery/storage_adapter`
 * filter.
 */
final class MetaStorageAdapter implements StorageAdapter {

	private const META_PREFIX   = '_bindery_';
	private const GLOBAL_OPTION = 'bindery_global_values';

	public function get( int $object_id, string $key, string $locale ): mixed {
		$map = $this->map( $object_id, $key );

		return array_key_exists( $locale, $map ) ? $map[ $locale ] : null;
	}

	public function set( int $object_id, string $key, string $locale, mixed $value ): void {
		$map            = $this->map( $object_id, $key );
		$map[ $locale ] = $value;
		$this->saveMap( $object_id, $key, $map );
	}

	public function delete( int $object_id, string $key, string $locale ): void {
		$map = $this->map( $object_id, $key );
		unset( $map[ $locale ] );
		$this->saveMap( $object_id, $key, $map );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	public function all( int $object_id ): array {
		if ( 0 === $object_id ) {
			return $this->globalStore();
		}

		$out  = array();
		$meta = get_post_meta( $object_id );
		foreach ( (array) $meta as $meta_key => $values ) {
			if ( ! str_starts_with( (string) $meta_key, self::META_PREFIX ) ) {
				continue;
			}
			$key    = substr( (string) $meta_key, strlen( self::META_PREFIX ) );
			$stored = maybe_unserialize( is_array( $values ) ? ( $values[0] ?? '' ) : $values );
			if ( is_array( $stored ) ) {
				$out[ $key ] = $stored;
			}
		}

		return $out;
	}

	/**
	 * Read the [ locale => value ] map for one field.
	 *
	 * @return array<string, mixed>
	 */
	private function map( int $object_id, string $key ): array {
		if ( 0 === $object_id ) {
			$store = $this->globalStore();

			return $store[ $key ] ?? array();
		}

		$map = get_post_meta( $object_id, self::META_PREFIX . $key, true );

		return is_array( $map ) ? $map : array();
	}

	/**
	 * @param array<string, mixed> $map
	 */
	private function saveMap( int $object_id, string $key, array $map ): void {
		if ( 0 === $object_id ) {
			$store         = $this->globalStore();
			$store[ $key ] = $map;
			update_option( self::GLOBAL_OPTION, $store, false );

			return;
		}

		update_post_meta( $object_id, self::META_PREFIX . $key, $map );
	}

	/**
	 * @return array<string, array<string, mixed>>
	 */
	private function globalStore(): array {
		$store = get_option( self::GLOBAL_OPTION, array() );

		return is_array( $store ) ? $store : array();
	}
}
