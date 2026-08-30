<?php
/**
 * Bindings service provider.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Providers;

use Bindery\Bindings\FieldBindingSource;
use Bindery\Container;
use Bindery\Contracts\ServiceProvider;
use Bindery\Fields\FieldDefinitionFactory;
use Bindery\Fields\FieldRegistry;
use Bindery\Fields\ValueResolver;

/**
 * Registers the `bindery/field` block-bindings source on init.
 */
final class BindingsServiceProvider implements ServiceProvider {

	public function register( Container $container ): void {
		$container->singleton(
			FieldBindingSource::class,
			static fn ( Container $c ): FieldBindingSource => new FieldBindingSource(
				$c->get( FieldRegistry::class ),
				$c->get( FieldDefinitionFactory::class ),
				$c->get( ValueResolver::class )
			)
		);
	}

	public function boot( Container $container ): void {
		add_action(
			'init',
			static function () use ( $container ): void {
				$container->get( FieldBindingSource::class )->register();
			}
		);
	}
}
