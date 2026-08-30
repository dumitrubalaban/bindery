<?php
/**
 * Deactivation handler.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Activation;

/**
 * Runs once when the plugin is deactivated.
 *
 * Deactivation is intentionally non-destructive: schema, stored values and
 * capabilities are preserved so deactivating then reactivating loses nothing.
 * Destructive cleanup lives in uninstall.php and is opt-in.
 */
final class Deactivator {

	/**
	 * Light, reversible teardown only.
	 */
	public static function deactivate(): void {
		/**
		 * Fires during Bindery deactivation. Use for transient/cron cleanup.
		 */
		do_action( 'bindery/deactivated' );
	}
}
