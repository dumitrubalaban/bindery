<?php
/**
 * Fields service provider — wires the value engine.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Providers;

use Bindery\Container;
use Bindery\Contracts\LocaleProvider;
use Bindery\Contracts\ServiceProvider;
use Bindery\Contracts\StorageAdapter;
use Bindery\Fields\FieldDefinitionFactory;
use Bindery\Fields\FieldRegistry;
use Bindery\Fields\FieldRenderer;
use Bindery\Fields\FieldTypeRegistry;
use Bindery\Fields\Types\ImageField;
use Bindery\Fields\Types\NumberField;
use Bindery\Fields\Types\RepeaterField;
use Bindery\Fields\Types\RichTextField;
use Bindery\Fields\Types\TextField;
use Bindery\Fields\Types\UrlField;
use Bindery\Fields\ValueResolver;
use Bindery\History\ValueHistory;
use Bindery\Locale\LocaleManager;
use Bindery\Locale\Providers\DefaultLocaleProvider;
use Bindery\Sources\AcfSource;
use Bindery\Sources\CallbackSource;
use Bindery\Sources\OptionSource;
use Bindery\Sources\SourceRegistry;
use Bindery\Sources\StoredSource;
use Bindery\Storage\CachingStorageAdapter;
use Bindery\Storage\HistoryStorageDecorator;
use Bindery\Storage\StorageManager;
use Bindery\Storage\TableStorageAdapter;
use Bindery\Support\Sanitizer;

/**
 * Registers every service that turns a declaration into a resolved, rendered
 * value: field types, sources, storage, locales, factory, resolver, renderer.
 */
final class FieldsServiceProvider implements ServiceProvider {

	public function register( Container $container ): void {
		$container->singleton(
			FieldTypeRegistry::class,
			static function (): FieldTypeRegistry {
				$registry = new FieldTypeRegistry();
				foreach ( array( new TextField(), new RichTextField(), new UrlField(), new ImageField(), new NumberField(), new RepeaterField() ) as $type ) {
					$registry->register( $type );
				}

				/**
				 * Fires so extensions can register custom field types.
				 *
				 * @param FieldTypeRegistry $registry The field-type registry.
				 */
				do_action( 'bindery/register_field_types', $registry );

				return $registry;
			}
		);

		$container->singleton(
			ValueHistory::class,
			static fn (): ValueHistory => new ValueHistory()
		);

		// The history-recording decorator is the outermost storage layer and a
		// retrievable singleton so the importer can suppress recording on it.
		$container->singleton(
			HistoryStorageDecorator::class,
			static function ( Container $c ): HistoryStorageDecorator {
				$default = new TableStorageAdapter();

				/**
				 * Filters the storage adapter (e.g. swap to meta or a remote store).
				 *
				 * @param StorageAdapter $adapter   Default table adapter.
				 * @param Container      $container DI container.
				 */
				$adapter = apply_filters( 'bindery/storage_adapter', $default, $c );

				$adapter = $adapter instanceof StorageAdapter ? $adapter : $default;

				/**
				 * Filters whether to wrap the storage adapter in the per-request
				 * caching decorator. Enabled by default: it collapses the
				 * one-query-per-field read pattern into one query per object and
				 * uses the WP object cache when a persistent backend is present.
				 *
				 * @param bool           $enabled Whether to cache. Default true.
				 * @param StorageAdapter $adapter The resolved adapter.
				 */
				if ( apply_filters( 'bindery/cache_storage', true, $adapter ) ) {
					$adapter = new CachingStorageAdapter( $adapter );
				}

				return new HistoryStorageDecorator( $adapter, $c->get( ValueHistory::class ) );
			}
		);

		$container->singleton(
			StorageManager::class,
			static fn ( Container $c ): StorageManager => new StorageManager(
				$c->get( HistoryStorageDecorator::class )
			)
		);

		$container->singleton(
			SourceRegistry::class,
			static function ( Container $c ): SourceRegistry {
				$registry = new SourceRegistry();
				$registry->register( new StoredSource( $c->get( StorageManager::class ) ) );
				$registry->register( new OptionSource() );
				$registry->register( new CallbackSource() );
				$registry->register( new AcfSource() );

				/**
				 * Fires so extensions can register custom value sources.
				 *
				 * @param SourceRegistry $registry  The source registry.
				 * @param Container      $container DI container.
				 */
				do_action( 'bindery/register_sources', $registry, $c );

				return $registry;
			}
		);

		$container->singleton(
			LocaleManager::class,
			static function ( Container $c ): LocaleManager {
				$default = new DefaultLocaleProvider();

				/**
				 * Filters the locale provider (e.g. a WPML/Polylang adapter).
				 *
				 * @param LocaleProvider $provider  Default provider.
				 * @param Container      $container DI container.
				 */
				$provider = apply_filters( 'bindery/locale_provider', $default, $c );

				return new LocaleManager( $provider instanceof LocaleProvider ? $provider : $default );
			}
		);

		$container->singleton( FieldRegistry::class, static fn (): FieldRegistry => new FieldRegistry() );

		$container->singleton(
			FieldDefinitionFactory::class,
			static fn ( Container $c ): FieldDefinitionFactory => new FieldDefinitionFactory( $c->get( FieldTypeRegistry::class ) )
		);

		$container->singleton(
			ValueResolver::class,
			static fn ( Container $c ): ValueResolver => new ValueResolver(
				$c->get( SourceRegistry::class ),
				$c->get( LocaleManager::class )
			)
		);

		$container->singleton(
			FieldRenderer::class,
			static fn ( Container $c ): FieldRenderer => new FieldRenderer(
				$c->get( FieldTypeRegistry::class ),
				$c->get( ValueResolver::class )
			)
		);

		$container->singleton(
			Sanitizer::class,
			static fn ( Container $c ): Sanitizer => new Sanitizer( $c->get( FieldTypeRegistry::class ) )
		);
	}

	public function boot( Container $container ): void {
		/**
		 * Fires once the value engine is available. Preferred hook for
		 * extensions that need to register types/sources/locales eagerly.
		 *
		 * @param Container $container DI container.
		 */
		do_action( 'bindery/extensions_ready', $container );
	}
}
