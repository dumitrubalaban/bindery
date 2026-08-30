<?php
/**
 * Minimal dependency-injection container.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery;

use Closure;
use InvalidArgumentException;

/**
 * A tiny PSR-11-style container.
 *
 * Services are registered as factories. `singleton()` memoises the first
 * resolution; `bind()` resolves a fresh instance each time. This is the single
 * composition root for the plugin — no other global state should exist.
 */
final class Container {

	/**
	 * Registered factories keyed by id.
	 *
	 * @var array<string, array{factory: Closure, shared: bool}>
	 */
	private array $bindings = array();

	/**
	 * Resolved shared instances keyed by id.
	 *
	 * @var array<string, mixed>
	 */
	private array $instances = array();

	/**
	 * Register a shared (memoised) factory.
	 */
	public function singleton( string $id, Closure $factory ): void {
		$this->bindings[ $id ] = array(
			'factory' => $factory,
			'shared'  => true,
		);
		unset( $this->instances[ $id ] );
	}

	/**
	 * Register a non-shared factory (new instance per resolution).
	 */
	public function bind( string $id, Closure $factory ): void {
		$this->bindings[ $id ] = array(
			'factory' => $factory,
			'shared'  => false,
		);
		unset( $this->instances[ $id ] );
	}

	/**
	 * Store an already-constructed instance.
	 */
	public function instance( string $id, mixed $instance ): void {
		$this->instances[ $id ] = $instance;
	}

	/**
	 * Whether an id is resolvable.
	 */
	public function has( string $id ): bool {
		return isset( $this->instances[ $id ] ) || isset( $this->bindings[ $id ] );
	}

	/**
	 * Resolve a service by id.
	 *
	 * @throws InvalidArgumentException When the id is not registered.
	 */
	public function get( string $id ): mixed {
		if ( isset( $this->instances[ $id ] ) ) {
			return $this->instances[ $id ];
		}

		if ( ! isset( $this->bindings[ $id ] ) ) {
			throw new InvalidArgumentException(
				esc_html( sprintf( 'Bindery container has no binding for "%s".', $id ) )
			);
		}

		$binding  = $this->bindings[ $id ];
		$resolved = ( $binding['factory'] )( $this );

		if ( $binding['shared'] ) {
			$this->instances[ $id ] = $resolved;
		}

		return $resolved;
	}
}
