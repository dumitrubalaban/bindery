<?php
/**
 * Settings service provider.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Providers;

use Bindery\Admin\ContentOverviewPage;
use Bindery\Admin\SettingsPage;
use Bindery\Container;
use Bindery\Contracts\ServiceProvider;
use Bindery\Data\ValuePorter;
use Bindery\Fields\FieldDefinitionFactory;
use Bindery\Locale\LocaleManager;
use Bindery\Rest\SettingsController;
use Bindery\Settings\Settings;
use Bindery\Storage\HistoryStorageDecorator;
use Bindery\Storage\StorageManager;

/**
 * Wires the settings store, its REST API and the admin page.
 */
final class SettingsServiceProvider implements ServiceProvider {

	public function register( Container $container ): void {
		$container->singleton( Settings::class, static fn (): Settings => new Settings() );

		$container->singleton(
			ValuePorter::class,
			static fn ( Container $c ): ValuePorter => new ValuePorter(
				$c->get( StorageManager::class ),
				$c->get( HistoryStorageDecorator::class )
			)
		);

		$container->singleton(
			SettingsController::class,
			static fn ( Container $c ): SettingsController => new SettingsController(
				$c->get( Settings::class ),
				$c->get( LocaleManager::class ),
				$c->get( ValuePorter::class )
			)
		);

		$container->singleton( SettingsPage::class, static fn (): SettingsPage => new SettingsPage() );

		$container->singleton(
			ContentOverviewPage::class,
			static fn ( Container $c ): ContentOverviewPage => new ContentOverviewPage(
				$c->get( StorageManager::class ),
				$c->get( LocaleManager::class ),
				$c->get( FieldDefinitionFactory::class )
			)
		);
	}

	public function boot( Container $container ): void {
		add_action(
			'rest_api_init',
			static function () use ( $container ): void {
				$container->get( SettingsController::class )->registerRoutes();
			}
		);

		add_action(
			'admin_menu',
			static function () use ( $container ): void {
				$container->get( SettingsPage::class )->registerMenu();
				$container->get( ContentOverviewPage::class )->registerMenu();
			}
		);

		add_action(
			'admin_enqueue_scripts',
			static function ( string $hook_suffix ) use ( $container ): void {
				$container->get( SettingsPage::class )->enqueue( $hook_suffix );
				$container->get( ContentOverviewPage::class )->enqueue( $hook_suffix );
			}
		);

		// Bridge settings into the existing developer-filter layer, so history and
		// caching consumers stay decoupled from the settings store. Developer
		// filters added at a later priority still win.
		$settings = $container->get( Settings::class );
		add_filter( 'bindery/record_history', static fn (): bool => (bool) $settings->get( 'history.enabled' ) );
		add_filter( 'bindery/history_cap', static fn (): int => (int) $settings->get( 'history.cap', 30 ) );
	}
}
