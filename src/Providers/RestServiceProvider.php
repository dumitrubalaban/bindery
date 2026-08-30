<?php
/**
 * REST service provider.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Providers;

use Bindery\Container;
use Bindery\Contracts\ServiceProvider;
use Bindery\Editor\PostFieldScanner;
use Bindery\Editor\TemplateFieldCollector;
use Bindery\Fields\FieldDefinitionFactory;
use Bindery\Locale\LocaleManager;
use Bindery\Rest\FieldController;
use Bindery\Storage\StorageManager;
use Bindery\Support\Sanitizer;

/**
 * Registers the field REST controller on rest_api_init.
 */
final class RestServiceProvider implements ServiceProvider {

	public function register( Container $container ): void {
		$container->singleton(
			PostFieldScanner::class,
			static fn ( Container $c ): PostFieldScanner => new PostFieldScanner(
				$c->get( FieldDefinitionFactory::class )
			)
		);

		$container->singleton(
			TemplateFieldCollector::class,
			static fn ( Container $c ): TemplateFieldCollector => new TemplateFieldCollector(
				$c->get( FieldDefinitionFactory::class )
			)
		);

		$container->singleton(
			FieldController::class,
			static fn ( Container $c ): FieldController => new FieldController(
				$c->get( PostFieldScanner::class ),
				$c->get( StorageManager::class ),
				$c->get( Sanitizer::class ),
				$c->get( LocaleManager::class ),
				$c->get( TemplateFieldCollector::class )
			)
		);
	}

	public function boot( Container $container ): void {
		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				$container->get( FieldController::class )->registerRoutes();
			}
		);

		// Persist template-declared fields after the page has rendered so a later
		// REST save can whitelist them. wp_footer covers normal page loads;
		// shutdown is the catch-all for templates that exit before the footer.
		$persist = static function () use ( $container ): void {
			$container->get( TemplateFieldCollector::class )->persist();
		};
		add_action( 'wp_footer', $persist, 99 );
		add_action( 'shutdown', $persist, 1 );
	}
}
