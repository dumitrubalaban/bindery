<?php
/**
 * WP-CLI service provider.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Providers;

use Bindery\Cli\BinderyCommand;
use Bindery\Container;
use Bindery\Contracts\ServiceProvider;
use Bindery\History\ValueHistory;
use Bindery\Storage\HistoryStorageDecorator;
use Bindery\Storage\StorageManager;
use WP_CLI;

/**
 * Registers the `wp bindery` command tree, but only under WP-CLI so the command
 * class and WP_CLI references never load on web requests.
 */
final class CliServiceProvider implements ServiceProvider {

	public function register( Container $container ): void {
		$container->singleton(
			BinderyCommand::class,
			static fn ( Container $c ): BinderyCommand => new BinderyCommand(
				$c->get( StorageManager::class ),
				$c->get( ValueHistory::class ),
				$c->get( HistoryStorageDecorator::class )
			)
		);
	}

	public function boot( Container $container ): void {
		if ( ! defined( 'WP_CLI' ) || ! WP_CLI ) {
			return;
		}

		WP_CLI::add_command( 'bindery', $container->get( BinderyCommand::class ) );
	}
}
