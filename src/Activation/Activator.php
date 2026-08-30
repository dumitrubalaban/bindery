<?php
/**
 * Activation handler.
 *
 * @package Bindery
 */

declare(strict_types=1);

namespace Bindery\Activation;

use Bindery\Storage\Schema\Migrator;
use Bindery\Support\Capabilities;
use WP_Site;

/**
 * Runs once when the plugin is activated.
 *
 * Kept self-contained (no container) because activation executes in its own
 * request before the normal boot cycle. Multisite-aware: a network activation
 * provisions every existing site, and {@see onNewSite()} provisions any site
 * created later while the plugin stays network-active.
 */
final class Activator {

	/**
	 * Option recording the last activated plugin version.
	 */
	private const VERSION_OPTION = 'bindery_version';

	/**
	 * Provision schema and capabilities.
	 *
	 * @param bool $network_wide True when network-activated across a multisite.
	 */
	public static function activate( bool $network_wide = false ): void {
		if ( $network_wide && is_multisite() ) {
			foreach ( get_sites(
				array(
					'fields' => 'ids',
					'number' => 0,
				)
			) as $site_id ) {
				switch_to_blog( (int) $site_id );
				self::provision();
				restore_current_blog();
			}

			return;
		}

		self::provision();
	}

	/**
	 * Provision a single, freshly created site while the plugin is network-active.
	 *
	 * @param WP_Site $new_site The site just created.
	 */
	public static function onNewSite( WP_Site $new_site ): void {
		if ( ! function_exists( 'is_plugin_active_for_network' ) ) {
			require_once ABSPATH . 'wp-admin/includes/plugin.php';
		}

		if ( ! is_plugin_active_for_network( plugin_basename( BINDERY_FILE ) ) ) {
			return;
		}

		switch_to_blog( (int) $new_site->blog_id );
		self::provision();
		restore_current_blog();
	}

	/**
	 * The actual per-site provisioning: table, capabilities, version stamp.
	 */
	private static function provision(): void {
		( new Migrator() )->migrate();

		Capabilities::grantDefaults();

		update_option( self::VERSION_OPTION, BINDERY_VERSION, true );

		/**
		 * Fires after Bindery has finished provisioning a site.
		 */
		do_action( 'bindery/activated' );
	}
}
