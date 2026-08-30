<?php
/**
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Tests\Unit;

use Bindery\Contracts\StorageAdapter;
use Bindery\Storage\CachingStorageAdapter;
use Brain\Monkey\Functions;

final class CachingStorageAdapterTest extends TestCase {

	/**
	 * A spying in-memory adapter that counts how often each method is hit, so we
	 * can assert the decorator collapses reads to a single all() per object.
	 */
	private function spy(): StorageAdapter {
		return new class() implements StorageAdapter {
			/** @var array<int, array<string, array<string, mixed>>> */
			public array $store = array(
				9 => array(
					'hero_title' => array( 'en_US' => 'Hello', 'ro_RO' => 'Salut' ),
					'hero_sub'   => array( 'en_US' => 'Sub' ),
				),
			);
			public int $allCalls = 0;
			public int $getCalls = 0;
			public int $setCalls = 0;
			public int $deleteCalls = 0;

			public function get( int $object_id, string $key, string $locale ): mixed {
				++$this->getCalls;
				return $this->store[ $object_id ][ $key ][ $locale ] ?? null;
			}
			public function set( int $object_id, string $key, string $locale, mixed $value ): void {
				++$this->setCalls;
				$this->store[ $object_id ][ $key ][ $locale ] = $value;
			}
			public function delete( int $object_id, string $key, string $locale ): void {
				++$this->deleteCalls;
				unset( $this->store[ $object_id ][ $key ][ $locale ] );
			}
			public function all( int $object_id ): array {
				++$this->allCalls;
				return $this->store[ $object_id ] ?? array();
			}
		};
	}

	/**
	 * Stub the object-cache functions to a simple request-local array so the
	 * decorator's wp_cache_* calls behave like a non-persistent cache.
	 */
	private function stubObjectCache(): void {
		$cache = array();
		Functions\when( 'wp_cache_get' )->alias(
			function ( $key, $group ) use ( &$cache ) {
				return array_key_exists( $group . ':' . $key, $cache ) ? $cache[ $group . ':' . $key ] : false;
			}
		);
		Functions\when( 'wp_cache_set' )->alias(
			function ( $key, $value, $group ) use ( &$cache ) {
				$cache[ $group . ':' . $key ] = $value;
				return true;
			}
		);
		Functions\when( 'wp_cache_delete' )->alias(
			function ( $key, $group ) use ( &$cache ) {
				unset( $cache[ $group . ':' . $key ] );
				return true;
			}
		);
	}

	public function test_primes_once_then_serves_reads_from_memory(): void {
		$this->stubObjectCache();
		$spy   = $this->spy();
		$cache = new CachingStorageAdapter( $spy );

		$this->assertSame( 'Hello', $cache->get( 9, 'hero_title', 'en_US' ) );
		$this->assertSame( 'Salut', $cache->get( 9, 'hero_title', 'ro_RO' ) );
		$this->assertSame( 'Sub', $cache->get( 9, 'hero_sub', 'en_US' ) );

		// Three field reads, but the inner adapter is primed with a single all().
		$this->assertSame( 1, $spy->allCalls );
		$this->assertSame( 0, $spy->getCalls );
	}

	public function test_missing_key_resolves_null_without_extra_query(): void {
		$this->stubObjectCache();
		$spy   = $this->spy();
		$cache = new CachingStorageAdapter( $spy );

		$this->assertNull( $cache->get( 9, 'does_not_exist', 'en_US' ) );
		$this->assertNull( $cache->get( 9, 'hero_sub', 'ro_RO' ) ); // key present, locale absent
		$this->assertSame( 1, $spy->allCalls );
	}

	public function test_set_invalidates_and_reflects_new_value(): void {
		$this->stubObjectCache();
		$spy   = $this->spy();
		$cache = new CachingStorageAdapter( $spy );

		$this->assertSame( 'Hello', $cache->get( 9, 'hero_title', 'en_US' ) ); // primes
		$cache->set( 9, 'hero_title', 'en_US', 'Changed' );

		$this->assertSame( 1, $spy->setCalls );
		$this->assertSame( 'Changed', $cache->get( 9, 'hero_title', 'en_US' ) );
		// Re-primed after invalidation: two all() calls total (before + after write).
		$this->assertSame( 2, $spy->allCalls );
	}

	public function test_delete_invalidates(): void {
		$this->stubObjectCache();
		$spy   = $this->spy();
		$cache = new CachingStorageAdapter( $spy );

		$this->assertSame( 'Hello', $cache->get( 9, 'hero_title', 'en_US' ) );
		$cache->delete( 9, 'hero_title', 'en_US' );

		$this->assertSame( 1, $spy->deleteCalls );
		$this->assertNull( $cache->get( 9, 'hero_title', 'en_US' ) );
		$this->assertSame( 2, $spy->allCalls );
	}
}
