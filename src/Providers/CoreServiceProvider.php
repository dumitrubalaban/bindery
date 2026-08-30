<?php
/**
 * Core service provider.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Providers;

use Bindery\Container;
use Bindery\Contracts\ServiceProvider;
use Bindery\Storage\Schema\Migrator;

/**
 * Registers foundational services and cross-cutting boot behaviour:
 * upgrade-safe schema migration. (Translations load automatically on WordPress
 * 4.6+ for plugins hosted on WordPress.org, so no manual text-domain loading.)
 */
final class CoreServiceProvider implements ServiceProvider {

	public function register( Container $container ): void {
		$container->singleton( Migrator::class, static fn (): Migrator => new Migrator() );
	}

	public function boot( Container $container ): void {
		// Upgrade-safe: if the plugin files were updated without reactivation,
		// bring the schema forward. Cheap no-op when already current.
		add_action(
			'admin_init',
			static function () use ( $container ): void {
				$container->get( Migrator::class )->migrate();
			}
		);
	}
}
