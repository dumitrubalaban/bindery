<?php
/**
 * Plugin bootstrap and composition root.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery;

use Bindery\Contracts\ServiceProvider;
use Bindery\Providers\BindingsServiceProvider;
use Bindery\Providers\BlocksServiceProvider;
use Bindery\Providers\CliServiceProvider;
use Bindery\Providers\CoreServiceProvider;
use Bindery\Providers\EditorServiceProvider;
use Bindery\Providers\FieldsServiceProvider;
use Bindery\Providers\FormsServiceProvider;
use Bindery\Providers\PatternsServiceProvider;
use Bindery\Providers\RestServiceProvider;
use Bindery\Providers\SettingsServiceProvider;

/**
 * Owns the container and orchestrates service-provider registration/boot.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private Container $container;

	private bool $booted = false;

	/**
	 * Booted provider instances.
	 *
	 * @var list<ServiceProvider>
	 */
	private array $providers = array();

	private function __construct(
		private readonly string $file
	) {
		$this->container = new Container();
		$this->container->instance( Container::class, $this->container );
		$this->container->instance( 'plugin.file', $this->file );
		$this->container->instance( 'plugin.version', BINDERY_VERSION );
	}

	/**
	 * Resolve the shared plugin instance.
	 */
	public static function instance( string $file = '' ): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self( $file );
		}

		return self::$instance;
	}

	/**
	 * The DI container.
	 */
	public function container(): Container {
		return $this->container;
	}

	/**
	 * Register and boot all service providers exactly once.
	 */
	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		/**
		 * Filters the list of service-provider class names.
		 *
		 * Extensions append their own providers here to plug into the same
		 * register()/boot() lifecycle as the core.
		 *
		 * @param list<class-string<ServiceProvider>> $providers Provider class names.
		 */
		$provider_classes = (array) apply_filters(
			'bindery/service_providers',
			$this->coreProviders()
		);

		foreach ( $provider_classes as $class ) {
			if ( ! is_string( $class ) || ! class_exists( $class ) ) {
				continue;
			}
			$provider = new $class();
			if ( ! $provider instanceof ServiceProvider ) {
				continue;
			}
			$provider->register( $this->container );
			$this->providers[] = $provider;
		}

		foreach ( $this->providers as $provider ) {
			$provider->boot( $this->container );
		}

		/**
		 * Fires once Bindery has registered and booted all providers.
		 *
		 * @param Container $container The DI container.
		 */
		do_action( 'bindery/booted', $this->container );
	}

	/**
	 * Core providers shipped with the plugin.
	 *
	 * @return list<class-string<ServiceProvider>>
	 */
	private function coreProviders(): array {
		return array(
			CoreServiceProvider::class,
			FieldsServiceProvider::class,
			BindingsServiceProvider::class,
			BlocksServiceProvider::class,
			RestServiceProvider::class,
			EditorServiceProvider::class,
			FormsServiceProvider::class,
			PatternsServiceProvider::class,
			SettingsServiceProvider::class,
			CliServiceProvider::class,
		);
	}
}
